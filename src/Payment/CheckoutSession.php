<?php

declare(strict_types=1);

namespace FiloAcademia\Payment;

final class CheckoutSession
{
    public function __construct(
        public readonly string $preferenceId,
        /** URL de la ventana de pago de Mercado Pago (Checkout Pro). */
        public readonly string $initPoint,
    ) {
    }
}
