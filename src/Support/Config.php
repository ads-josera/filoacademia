<?php

declare(strict_types=1);

namespace FiloAcademia\Support;

/**
 * Lectura de configuración con notación de puntos: get('mail.host').
 */
final class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) ($this->get($key) ?? $default);
    }

    public function isProduction(): bool
    {
        return $this->get('app.env') === 'production';
    }
}
