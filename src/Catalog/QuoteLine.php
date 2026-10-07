<?php

declare(strict_types=1);

namespace FiloAcademia\Catalog;

/**
 * Una línea del pedido: N cuchillos con el mismo servicio.
 */
final class QuoteLine
{
    /** @param list<string> $extraIds */
    public function __construct(
        public readonly string $removalId,
        public readonly array $extraIds,
        public readonly string $name,
        public readonly int $unitPrice,
        public readonly int $quantity,
    ) {
    }

    public function total(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    /** @return array{removal: string, extras: list<string>, name: string, unit_price: int, qty: int} */
    public function toArray(): array
    {
        return [
            'removal' => $this->removalId,
            'extras' => $this->extraIds,
            'name' => $this->name,
            'unit_price' => $this->unitPrice,
            'qty' => $this->quantity,
        ];
    }
}
