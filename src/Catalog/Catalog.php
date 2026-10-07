<?php

declare(strict_types=1);

namespace FiloAcademia\Catalog;

/**
 * Acceso de solo lectura al catálogo de precios (config/catalog.php).
 *
 * Todo lo que muestra o cobra un precio pasa por aquí, para que la lista de
 * precios, el cotizador y el cobro no puedan divergir.
 */
final class Catalog
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public static function fromFile(string $path): self
    {
        return new self(require $path);
    }

    public function currency(): string
    {
        return $this->data['currency'];
    }

    public function maxKnivesPerLine(): int
    {
        return (int) $this->data['max_knives_per_line'];
    }

    /** @return array{id: string, name: string, short: string, price: int} */
    public function base(): array
    {
        return $this->data['base'];
    }

    /** @return list<array{id: string, name: string, detail: string, price: int}> */
    public function removalOptions(): array
    {
        return $this->data['removal'];
    }

    /** @return list<array{id: string, name: string, price: int}> */
    public function extras(): array
    {
        return $this->data['extras'];
    }

    /** @return list<array<string, mixed>> */
    public function deliveryOptions(): array
    {
        return $this->data['delivery'];
    }

    /** @return array{id: string, name: string, detail: string, price: int} */
    public function insurance(): array
    {
        return $this->data['insurance'];
    }

    /** @return array{id: string, name: string, detail: string, price: int}|null */
    public function findRemoval(string $id): ?array
    {
        return $this->findById($this->removalOptions(), $id);
    }

    /** @return array{id: string, name: string, price: int}|null */
    public function findExtra(string $id): ?array
    {
        return $this->findById($this->extras(), $id);
    }

    /** @return array<string, mixed>|null Solo opciones disponibles. */
    public function findDelivery(string $id): ?array
    {
        $option = $this->findById($this->deliveryOptions(), $id);

        return ($option !== null && $option['available']) ? $option : null;
    }

    /**
     * Versión que se entrega al navegador para el cotizador. Es una copia de
     * presentación: el servidor vuelve a calcular con este mismo catálogo.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return $this->data;
    }

    /**
     * @param list<array<string, mixed>> $options
     * @return array<string, mixed>|null
     */
    private function findById(array $options, string $id): ?array
    {
        foreach ($options as $option) {
            if ($option['id'] === $id) {
                return $option;
            }
        }

        return null;
    }
}
