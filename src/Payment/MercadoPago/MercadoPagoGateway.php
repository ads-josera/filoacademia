<?php

declare(strict_types=1);

namespace FiloAcademia\Payment\MercadoPago;

use FiloAcademia\Order\Order;
use FiloAcademia\Order\PaymentSnapshot;
use FiloAcademia\Payment\CheckoutSession;
use FiloAcademia\Payment\Http\HttpTransport;
use FiloAcademia\Payment\PaymentGateway;
use FiloAcademia\Payment\PaymentGatewayException;
use FiloAcademia\Support\Urls;

/**
 * Implementación de PaymentGateway sobre la API REST de Mercado Pago.
 *
 * Se usa la API REST directamente (no el SDK) para tener una sola dependencia
 * menos que mantener: solo se ocupan tres endpoints, documentados y estables.
 *
 * @see https://www.mercadopago.com.mx/developers/es/reference/preferences/_checkout_preferences/post
 * @see https://www.mercadopago.com.mx/developers/es/reference/payments/_payments/post
 */
final class MercadoPagoGateway implements PaymentGateway
{
    private const API = 'https://api.mercadopago.com';

    public function __construct(
        private readonly HttpTransport $transport,
        private readonly string $accessToken,
        private readonly Urls $urls,
        private readonly string $statementDescriptor,
        private readonly int $maxInstallments,
    ) {
    }

    public function createCheckout(Order $order): CheckoutSession
    {
        $resultUrl = $this->urls->paymentResult($order);

        $payload = [
            'items' => $this->items($order),
            // Sin 'payer' a propósito: Mercado Pago identifica al pagador en su
            // propia ventana. Enviar el correo del pedido lo amarra a un usuario:
            // con credenciales de prueba y un correo real bloquea el pago («una de
            // las partes es de prueba»), y en producción choca si el cliente paga
            // con una cuenta de otro correo. El correo para avisos ya está en el pedido.
            'external_reference' => $order->folio,
            'statement_descriptor' => $this->statementDescriptor,
            'back_urls' => [
                'success' => $resultUrl,
                'pending' => $resultUrl,
                'failure' => $resultUrl,
            ],
            'payment_methods' => [
                'installments' => $this->maxInstallments,
            ],
            'metadata' => ['order_id' => $order->id],
        ];

        // Mercado Pago rechaza auto_return con direcciones que no son públicas
        // (localhost). En local se omite; el pago se sincroniza igual al volver
        // a la página de resultado.
        if ($this->urls->isPublic()) {
            $payload['auto_return'] = 'approved';
        }

        // Sin notification_url a propósito: tendría prioridad sobre el webhook
        // configurado en el panel, y la documentación solo garantiza la firma
        // x-signature para el del panel. Ver docs/FLUJO-DE-PAGO.md § Webhook.

        $response = $this->request('POST', '/checkout/preferences', $payload);

        if (!isset($response['id'], $response['init_point'])) {
            throw new PaymentGatewayException('Respuesta de preferencia sin id o init_point.');
        }

        return new CheckoutSession((string) $response['id'], (string) $response['init_point']);
    }

    public function createPayment(Order $order, array $paymentData, string $idempotencyKey): PaymentSnapshot
    {
        // El monto y la referencia salen del pedido guardado, nunca del navegador.
        $payload = $paymentData + [
            'description' => sprintf('Pedido %s · %s', $order->folio, 'HERO Filo Academia'),
            'statement_descriptor' => $this->statementDescriptor,
            'metadata' => ['order_id' => $order->id],
        ];
        $payload['transaction_amount'] = (float) $order->total;
        $payload['external_reference'] = $order->folio;
        // Sin notification_url: ver createCheckout().

        $response = $this->request('POST', '/v1/payments', $payload, ['X-Idempotency-Key' => $idempotencyKey]);

        return PaymentSnapshot::fromApi($response);
    }

    public function getPayment(string $paymentId): PaymentSnapshot
    {
        if (!ctype_digit($paymentId)) {
            throw new PaymentGatewayException('Identificador de pago no válido.');
        }

        return PaymentSnapshot::fromApi($this->request('GET', '/v1/payments/' . $paymentId));
    }

    /**
     * @see https://www.mercadopago.com.mx/developers/es/docs/subscriptions/additional-content/payment-management
     */
    public function findPaymentsByReference(string $folio): array
    {
        $query = http_build_query([
            'external_reference' => $folio,
            'sort' => 'date_created',
            'criteria' => 'asc',
            'limit' => 50,
        ]);
        $response = $this->request('GET', '/v1/payments/search?' . $query);

        $payments = [];
        foreach (($response['results'] ?? []) as $payment) {
            // La búsqueda es por texto: se descarta cualquier coincidencia que no sea exacta.
            if (is_array($payment) && isset($payment['id']) && ($payment['external_reference'] ?? null) === $folio) {
                $payments[] = PaymentSnapshot::fromApi($payment);
            }
        }

        return $payments;
    }

    /** @return list<array<string, mixed>> */
    private function items(Order $order): array
    {
        $items = [];
        foreach ($order->lines as $index => $line) {
            $items[] = [
                'id' => 'servicio-' . ($index + 1),
                'title' => mb_substr($line['name'], 0, 250),
                'quantity' => $line['qty'],
                'unit_price' => (float) $line['unit_price'],
                'currency_id' => $order->currency,
            ];
        }
        if ($order->deliveryAmount > 0) {
            $items[] = [
                'id' => 'entrega-' . $order->deliveryId,
                'title' => $order->deliveryName,
                'quantity' => 1,
                'unit_price' => (float) $order->deliveryAmount,
                'currency_id' => $order->currency,
            ];
        }
        if ($order->insuranceAmount > 0) {
            $items[] = [
                'id' => 'seguro',
                'title' => 'Seguro de paquetería',
                'quantity' => 1,
                'unit_price' => (float) $order->insuranceAmount,
                'currency_id' => $order->currency,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $extraHeaders
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
    {
        if ($this->accessToken === '') {
            throw new PaymentGatewayException('Falta mercadopago.access_token en config/config.php.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ] + $extraHeaders;

        try {
            $response = $this->transport->send(
                $method,
                self::API . $path,
                $headers,
                $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            );
        } catch (\RuntimeException $exception) {
            throw new PaymentGatewayException('Mercado Pago no respondió: ' . $exception->getMessage(), null, $exception);
        }

        $data = json_decode($response['body'], true);

        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($data)) {
            $detail = is_array($data) ? (string) ($data['message'] ?? $data['error'] ?? '') : '';
            throw new PaymentGatewayException(
                sprintf('Mercado Pago respondió %d en %s %s: %s', $response['status'], $method, $path, $detail),
                $response['status'],
            );
        }

        return $data;
    }
}
