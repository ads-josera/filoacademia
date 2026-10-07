<?php

declare(strict_types=1);

namespace FiloAcademia\Order;

/**
 * Lo que necesitamos de un pago de Mercado Pago, leído SIEMPRE desde su API
 * (nunca del cuerpo de un webhook ni de los parámetros de retorno).
 */
final class PaymentSnapshot
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $statusDetail,
        public readonly ?string $externalReference,
        public readonly float $transactionAmount,
        public readonly string $currency,
        public readonly ?string $paymentMethodId,
        public readonly ?string $ticketUrl,
    ) {
    }

    /** @param array<string, mixed> $payment Respuesta de GET/POST /v1/payments. */
    public static function fromApi(array $payment): self
    {
        return new self(
            id: (string) $payment['id'],
            status: (string) ($payment['status'] ?? 'pending'),
            statusDetail: isset($payment['status_detail']) ? (string) $payment['status_detail'] : null,
            externalReference: isset($payment['external_reference']) ? (string) $payment['external_reference'] : null,
            transactionAmount: (float) ($payment['transaction_amount'] ?? 0),
            currency: (string) ($payment['currency_id'] ?? ''),
            paymentMethodId: isset($payment['payment_method_id']) ? (string) $payment['payment_method_id'] : null,
            // Ficha de OXXO / instrucciones de pago en efectivo o transferencia.
            ticketUrl: isset($payment['transaction_details']['external_resource_url'])
                ? (string) $payment['transaction_details']['external_resource_url']
                : null,
        );
    }
}
