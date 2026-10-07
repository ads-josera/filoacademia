<?php

declare(strict_types=1);

namespace FiloAcademia\Catalog;

/**
 * Convierte lo que el cliente eligió (identificadores) en montos cobrables.
 *
 * Recibe datos sin confiar en ellos: valida cada identificador contra el
 * catálogo y descarta cualquier precio que venga del navegador.
 */
final class QuoteCalculator
{
    public const MAX_LINES = 10;

    public function __construct(private readonly Catalog $catalog)
    {
    }

    /**
     * @param mixed $rawLines Líneas del carrito tal como llegan (JSON decodificado).
     * @throws QuoteException Si algún dato no existe en el catálogo.
     */
    public function calculate(mixed $rawLines, string $deliveryId, bool $insurance): Quote
    {
        if (!is_array($rawLines) || $rawLines === []) {
            throw new QuoteException('El carrito está vacío.');
        }
        if (count($rawLines) > self::MAX_LINES) {
            throw new QuoteException('El carrito tiene demasiadas líneas.');
        }

        $lines = [];
        foreach (array_values($rawLines) as $raw) {
            $lines[] = $this->buildLine($raw);
        }

        $delivery = $this->catalog->findDelivery($deliveryId);
        if ($delivery === null) {
            throw new QuoteException('Elige una forma de recepción y entrega válida.');
        }

        // El seguro solo existe con paquetería; si la opción no lo admite se
        // ignora en lugar de fallar, para no bloquear un carrito antiguo.
        $insuranceApplies = $insurance && $delivery['insurable'];

        return new Quote(
            lines: $lines,
            deliveryId: $delivery['id'],
            deliveryName: $delivery['long'],
            deliveryAmount: (int) $delivery['price'],
            needsAddress: (bool) $delivery['needs_address'],
            insuranceAmount: $insuranceApplies ? (int) $this->catalog->insurance()['price'] : 0,
            currency: $this->catalog->currency(),
        );
    }

    private function buildLine(mixed $raw): QuoteLine
    {
        if (!is_array($raw)) {
            throw new QuoteException('Una línea del carrito no es válida.');
        }

        $base = $this->catalog->base();

        $removal = $this->catalog->findRemoval((string) ($raw['removal'] ?? 'ninguna'));
        if ($removal === null) {
            throw new QuoteException('Una opción de reparación no es válida.');
        }

        $extraIds = $raw['extras'] ?? [];
        if (!is_array($extraIds)) {
            throw new QuoteException('Una opción de reparación no es válida.');
        }

        $extras = [];
        foreach (array_unique(array_map('strval', $extraIds)) as $extraId) {
            $extra = $this->catalog->findExtra($extraId);
            if ($extra === null) {
                throw new QuoteException('Una opción de reparación no es válida.');
            }
            $extras[] = $extra;
        }

        $quantity = filter_var($raw['qty'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => $this->catalog->maxKnivesPerLine()],
        ]);
        if ($quantity === false) {
            throw new QuoteException(sprintf(
                'La cantidad de cuchillos debe estar entre 1 y %d.',
                $this->catalog->maxKnivesPerLine(),
            ));
        }

        $unitPrice = $base['price'] + $removal['price'] + array_sum(array_column($extras, 'price'));

        $nameParts = [$base['short']];
        if ($removal['price'] > 0) {
            $nameParts[] = $removal['name'];
        }
        foreach ($extras as $extra) {
            $nameParts[] = $extra['name'];
        }

        return new QuoteLine(
            removalId: $removal['id'],
            extraIds: array_column($extras, 'id'),
            name: implode(' + ', $nameParts),
            unitPrice: $unitPrice,
            quantity: $quantity,
        );
    }
}
