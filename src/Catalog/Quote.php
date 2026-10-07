<?php

declare(strict_types=1);

namespace FiloAcademia\Catalog;

/**
 * Resultado inmutable de una cotización ya validada. Montos en pesos enteros.
 */
final class Quote
{
    /** @param list<QuoteLine> $lines */
    public function __construct(
        public readonly array $lines,
        public readonly string $deliveryId,
        public readonly string $deliveryName,
        public readonly int $deliveryAmount,
        public readonly bool $needsAddress,
        public readonly int $insuranceAmount,
        public readonly string $currency,
    ) {
    }

    public function servicesSubtotal(): int
    {
        return array_sum(array_map(static fn (QuoteLine $line): int => $line->total(), $this->lines));
    }

    public function total(): int
    {
        return $this->servicesSubtotal() + $this->deliveryAmount + $this->insuranceAmount;
    }

    public function knifeCount(): int
    {
        return array_sum(array_map(static fn (QuoteLine $line): int => $line->quantity, $this->lines));
    }
}
