<?php

declare(strict_types=1);

namespace FiloAcademia\Order;

/**
 * Pedido tal como está guardado. Objeto de lectura: los cambios se hacen a
 * través de OrderRepository, que es quien garantiza las reglas de escritura.
 */
final class Order
{
    /** @param list<array{removal: string, extras: list<string>, name: string, unit_price: int, qty: int}> $lines */
    public function __construct(
        public readonly int $id,
        public readonly string $folio,
        public readonly string $accessToken,
        public readonly OrderStatus $status,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $customerPhone,
        public readonly ?string $customerAddress,
        public readonly ?string $customerNotes,
        public readonly array $lines,
        public readonly string $deliveryId,
        public readonly string $deliveryName,
        public readonly int $servicesAmount,
        public readonly int $deliveryAmount,
        public readonly int $insuranceAmount,
        public readonly int $total,
        public readonly string $currency,
        public readonly string $checkoutMode,
        public readonly ?string $mpPreferenceId,
        public readonly ?string $mpInitPoint,
        public readonly ?string $mpPaymentId,
        public readonly ?string $mpStatus,
        public readonly ?string $mpStatusDetail,
        public readonly ?string $mpPaymentMethod,
        public readonly ?string $mpTicketUrl,
        public readonly int $paymentAttempts,
        public readonly ?string $paidAt,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            folio: $row['folio'],
            accessToken: $row['access_token'],
            status: OrderStatus::from($row['status']),
            customerName: $row['customer_name'],
            customerEmail: $row['customer_email'],
            customerPhone: $row['customer_phone'],
            customerAddress: $row['customer_address'],
            customerNotes: $row['customer_notes'],
            lines: json_decode($row['lines_json'], true, 8, JSON_THROW_ON_ERROR),
            deliveryId: $row['delivery_id'],
            deliveryName: $row['delivery_name'],
            servicesAmount: (int) $row['services_amount'],
            deliveryAmount: (int) $row['delivery_amount'],
            insuranceAmount: (int) $row['insurance_amount'],
            total: (int) $row['total'],
            currency: $row['currency'],
            checkoutMode: $row['checkout_mode'],
            mpPreferenceId: $row['mp_preference_id'],
            mpInitPoint: $row['mp_init_point'],
            mpPaymentId: $row['mp_payment_id'],
            mpStatus: $row['mp_status'],
            mpStatusDetail: $row['mp_status_detail'],
            mpPaymentMethod: $row['mp_payment_method'],
            mpTicketUrl: $row['mp_ticket_url'],
            paymentAttempts: (int) $row['payment_attempts'],
            paidAt: $row['paid_at'],
            createdAt: (string) $row['created_at'],
        );
    }

    /** Comparación en tiempo constante: el token protege datos personales. */
    public function isAccessibleWith(string $token): bool
    {
        return $token !== '' && hash_equals($this->accessToken, $token);
    }

    public function knifeCount(): int
    {
        return array_sum(array_column($this->lines, 'qty'));
    }
}
