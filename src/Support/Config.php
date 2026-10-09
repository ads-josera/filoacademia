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
        $value = is_array($this->get("mercadopago.{$mode}"))
            ? $this->string("mercadopago.{$mode}.{$key}")
            : $this->string("mercadopago.{$key}");

        // Al pegar con nano es fácil arrastrar un espacio o salto de línea.
        return trim($value);
    }

    /**
     * Claves con las que se aceptan avisos del webhook, sin importar el modo:
     * la de la aplicación del cliente (mercadopago.webhook_secret o
     * production.webhook_secret) y la de la aplicación espejo de prueba
     * (test.webhook_secret). Así no se pierden avisos tardíos al cambiar de modo.
     *
     * @return list<string>
     */
    public function mercadoPagoWebhookSecrets(): array
    {
        $secrets = [];
        foreach (['mercadopago.webhook_secret', 'mercadopago.production.webhook_secret', 'mercadopago.test.webhook_secret'] as $key) {
            $value = trim($this->string($key));
            if ($value !== '' && !in_array($value, $secrets, true)) {
                $secrets[] = $value;
            }
        }

        return $secrets;
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
