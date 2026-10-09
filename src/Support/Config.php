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

    /**
     * Llave de Mercado Pago del modo activo (mercadopago.mode: test | production).
     *
     * Si el bloque del modo existe se usa aunque esté vacío: en «production»
     * sin llaves el sitio debe fallar a la vista, nunca caer en silencio a las
     * de prueba. Solo sin bloque (configuración anterior) se usan las llaves
     * sueltas mercadopago.public_key / access_token.
     *
     * @param 'public_key'|'access_token' $key
     */
    public function mercadoPagoCredential(string $key): string
    {
        $mode = $this->mercadoPagoMode();

        return is_array($this->get("mercadopago.{$mode}"))
            ? $this->string("mercadopago.{$mode}.{$key}")
            : $this->string("mercadopago.{$key}");
    }

    /** @return 'test'|'production' Cualquier valor distinto de «production» es «test». */
    public function mercadoPagoMode(): string
    {
        return $this->get('mercadopago.mode') === 'production' ? 'production' : 'test';
    }

    public function isProduction(): bool
    {
        return $this->get('app.env') === 'production';
    }
}
