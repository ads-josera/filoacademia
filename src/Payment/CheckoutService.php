<?php

declare(strict_types=1);

namespace FiloAcademia\Payment;

use FiloAcademia\Catalog\QuoteCalculator;
use FiloAcademia\Catalog\QuoteException;
use FiloAcademia\Order\CustomerData;
use FiloAcademia\Order\Order;
use FiloAcademia\Order\OrderRepository;
use FiloAcademia\Order\PaymentSnapshot;
use FiloAcademia\Support\Logger;

/**
 * Del carrito al cobro: crea el pedido, prepara el pago en Mercado Pago y,
 * en modo Bricks, ejecuta el cobro con los datos del formulario embebido.
 */
final class CheckoutService
{
    public const MODE_PRO = 'pro';
    public const MODE_BRICKS = 'bricks';

    /** Pedidos por IP en la ventana de tiempo: frena abusos sin estorbar a nadie real. */
    private const MAX_ORDERS_PER_IP = 8;
    private const RATE_WINDOW_SECONDS = 600;

    /** Intentos de cobro con Bricks por pedido (tarjetas rechazadas, reintentos). */
    private const MAX_PAYMENT_ATTEMPTS = 6;

    /**
     * Campos del formulario de Bricks que se aceptan. Todo lo demás se
     * descarta: en especial transaction_amount, que lo pone el servidor.
     */
    private const BRICKS_FIELDS = ['token', 'issuer_id', 'payment_method_id', 'installments', 'payer', 'transaction_details'];

    public function __construct(
        private readonly QuoteCalculator $calculator,
        private readonly OrderRepository $orders,
        private readonly PaymentGateway $gateway,
        private readonly PaymentSyncService $sync,
        private readonly Logger $logger,
        private readonly string $checkoutMode,
        private readonly int $maxInstallments,
    ) {
    }

    public function mode(): string
    {
        return $this->checkoutMode === self::MODE_BRICKS ? self::MODE_BRICKS : self::MODE_PRO;
    }

    /**
     * @param array<string, mixed> $input Formulario de /pagar.
     * @return array{0: ?Order, 1: array<string, string>} [pedido, errores por campo]
     */
    public function placeOrder(array $input, string $clientIp): array
    {
        $cart = json_decode((string) ($input['cart'] ?? ''), true);

        try {
            $quote = $this->calculator->calculate(
                $cart,
                (string) ($input['delivery'] ?? ''),
                ($input['insurance'] ?? '') === '1',
            );
        } catch (QuoteException $exception) {
            return [null, ['cart' => $exception->getMessage()]];
        }

        [$customer, $errors] = CustomerData::fromInput($input, $quote->needsAddress);
        if ($customer === null) {
            return [null, $errors];
        }

        $ipHash = hash('sha256', $clientIp);
        if ($this->orders->countRecentByIp($ipHash, self::RATE_WINDOW_SECONDS) >= self::MAX_ORDERS_PER_IP) {
            $this->logger->warning('Límite de pedidos por IP alcanzado.', ['ip_hash' => $ipHash]);

            return [null, ['form' => 'Recibimos muchos pedidos desde tu conexión. Espera unos minutos o escríbenos por WhatsApp.']];
        }

        $order = $this->orders->create($quote, $customer, $this->mode(), $ipHash);
        $this->logger->info('Pedido creado.', ['folio' => $order->folio, 'total' => $order->total, 'mode' => $order->checkoutMode]);

        return [$order, []];
    }

    /**
     * Crea (una sola vez) la preferencia de Mercado Pago del pedido. La usa
     * Checkout Pro para redirigir y Bricks para ofrecer «pagar con cuenta MP».
     *
     * @throws PaymentGatewayException
     */
    public function prepareCheckout(Order $order): Order
    {
        if ($order->mpPreferenceId !== null) {
            return $order;
        }

        $session = $this->gateway->createCheckout($order);
        $this->orders->savePreference($order->id, $session->preferenceId, $session->initPoint);

        return $this->orders->findById($order->id) ?? $order;
    }

    /**
     * Cobra con los datos que entregó el Payment Brick.
     *
     * @param array<string, mixed> $formData
     * @throws PaymentGatewayException
     * @throws CheckoutException Si el pedido no admite el cobro.
     */
    public function payWithBricks(Order $order, array $formData): PaymentSnapshot
    {
        if (!$order->status->acceptsPayment()) {
            throw new CheckoutException('Este pedido ya no admite pagos.');
        }

        $attempt = $this->orders->reservePaymentAttempt($order->id, self::MAX_PAYMENT_ATTEMPTS);
        if ($attempt === null) {
            throw new CheckoutException('Se alcanzó el número máximo de intentos. Escríbenos por WhatsApp y lo resolvemos.');
        }

        $paymentData = array_intersect_key($formData, array_flip(self::BRICKS_FIELDS));
        if (isset($paymentData['installments'])) {
            $paymentData['installments'] = max(1, min($this->maxInstallments, (int) $paymentData['installments']));
        }
        // Sin correo del pagador Mercado Pago rechaza el cobro: se usa el del pedido.
        if (!isset($paymentData['payer']['email']) || !is_string($paymentData['payer']['email']) || $paymentData['payer']['email'] === '') {
            $paymentData['payer'] = (is_array($paymentData['payer'] ?? null) ? $paymentData['payer'] : []) + ['email' => $order->customerEmail];
        }

        // Misma llave para el mismo intento: un doble envío no cobra dos veces.
        $payment = $this->gateway->createPayment($order, $paymentData, $order->folio . '-' . $attempt);

        $this->sync->apply($payment, $order->folio);

        return $payment;
    }
}
