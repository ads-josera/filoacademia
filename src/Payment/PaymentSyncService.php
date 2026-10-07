<?php

declare(strict_types=1);

namespace FiloAcademia\Payment;

use FiloAcademia\Mail\OrderNotifier;
use FiloAcademia\Order\Order;
use FiloAcademia\Order\OrderRepository;
use FiloAcademia\Order\OrderStatus;
use FiloAcademia\Order\PaymentSnapshot;
use FiloAcademia\Support\Logger;

/**
 * Aplica a un pedido el estado REAL de un pago en Mercado Pago.
 *
 * Es el único lugar donde un pedido cambia de estado por un pago. Lo llaman
 * el webhook, la página de resultado y el cobro con Bricks; las tres vías
 * convergen aquí, así que llamarlo varias veces con el mismo pago es seguro.
 */
final class PaymentSyncService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderRepository $orders,
        private readonly OrderNotifier $notifier,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Consulta el pago en Mercado Pago y lo aplica.
     *
     * @param string|null $expectedFolio Si se indica, el pago debe ser de ese pedido
     *                                   (protege la página de resultado contra
     *                                   un payment_id ajeno en la URL).
     * @throws PaymentGatewayException
     */
    public function syncById(string $paymentId, ?string $expectedFolio = null): ?Order
    {
        return $this->apply($this->gateway->getPayment($paymentId), $expectedFolio);
    }

    /**
     * Revisa en Mercado Pago todos los pagos de un pedido (respaldo del webhook).
     * Se aplican del más antiguo al más reciente; la regla de no-regresión
     * evita que un intento rechazado deshaga uno aprobado.
     *
     * @throws PaymentGatewayException
     */
    public function syncByFolio(string $folio): ?Order
    {
        $order = null;
        foreach ($this->gateway->findPaymentsByReference($folio) as $payment) {
            $order = $this->apply($payment, $folio) ?? $order;
        }

        return $order;
    }

    /**
     * @return Order|null El pedido actualizado, o null si el pago no es de un pedido nuestro.
     */
    public function apply(PaymentSnapshot $payment, ?string $expectedFolio = null): ?Order
    {
        $folio = $payment->externalReference;
        if ($folio === null || ($expectedFolio !== null && $folio !== $expectedFolio)) {
            $this->logger->warning('Pago sin pedido correspondiente.', [
                'payment_id' => $payment->id,
                'external_reference' => $folio,
                'expected' => $expectedFolio,
            ]);

            return null;
        }

        $order = $this->orders->findByFolio($folio);
        if ($order === null) {
            $this->logger->warning('Pago con folio desconocido.', ['payment_id' => $payment->id, 'folio' => $folio]);

            return null;
        }

        if (!$this->amountMatches($order, $payment)) {
            // Nunca debería pasar: el monto lo fija el servidor. Si pasa, alguien
            // manipuló el cobro; el pedido NO se marca como pagado.
            $this->logger->error('El monto o la moneda del pago no coinciden con el pedido.', [
                'folio' => $order->folio,
                'payment_id' => $payment->id,
                'expected' => $order->total . ' ' . $order->currency,
                'received' => $payment->transactionAmount . ' ' . $payment->currency,
            ]);

            return $order;
        }

        // Un pedido pagado solo cambia por noticias de ESE mismo pago (por
        // ejemplo, un reembolso). Un intento anterior rechazado que llega
        // tarde no debe dejarlo como «rechazado».
        if ($order->status === OrderStatus::Approved && $order->mpPaymentId !== $payment->id) {
            $this->logger->info('Se ignora un pago distinto sobre un pedido ya pagado.', [
                'folio' => $order->folio,
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);

            return $order;
        }

        $this->orders->applyPayment($order->id, $payment);
        $updated = $this->orders->findById($order->id) ?? $order;

        if ($updated->status !== $order->status) {
            $this->logger->info('Cambio de estado del pedido.', [
                'folio' => $order->folio,
                'from' => $order->status->value,
                'to' => $updated->status->value,
                'payment_id' => $payment->id,
                'detail' => $payment->statusDetail,
            ]);
        }

        if ($updated->status === OrderStatus::Approved) {
            $this->notifier->notifyPaid($updated);
        }

        return $updated;
    }

    private function amountMatches(Order $order, PaymentSnapshot $payment): bool
    {
        return $payment->currency === $order->currency
            && abs($payment->transactionAmount - $order->total) < 0.01;
    }
}
