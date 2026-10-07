<?php

declare(strict_types=1);

namespace FiloAcademia\Order;

/**
 * Estado del pedido desde el punto de vista del negocio.
 *
 * Se deriva del estado del pago en Mercado Pago, pero es nuestro: Mercado Pago
 * tiene más estados de los que el taller necesita distinguir.
 */
enum OrderStatus: string
{
    /** Creado; el cliente todavía no paga (o pagará en OXXO). */
    case Pending = 'pending';
    /** Pago en revisión por Mercado Pago. */
    case InProcess = 'in_process';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * @see https://www.mercadopago.com.mx/developers/es/reference/payments/_payments_id/get
     */
    public static function fromMercadoPago(string $status): self
    {
        return match ($status) {
            'approved' => self::Approved,
            'in_process', 'in_mediation', 'authorized' => self::InProcess,
            'rejected' => self::Rejected,
            'cancelled', 'expired' => self::Cancelled,
            'refunded', 'charged_back' => self::Refunded,
            default => self::Pending,
        };
    }

    /** ¿Se puede (re)intentar pagar este pedido? */
    public function acceptsPayment(): bool
    {
        return in_array($this, [self::Pending, self::Rejected, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de pago',
            self::InProcess => 'Pago en revisión',
            self::Approved => 'Pagado',
            self::Rejected => 'Pago rechazado',
            self::Cancelled => 'Pago cancelado',
            self::Refunded => 'Reembolsado',
        };
    }
}
