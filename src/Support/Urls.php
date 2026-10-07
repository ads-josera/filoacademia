<?php

declare(strict_types=1);

namespace FiloAcademia\Support;

use FiloAcademia\Order\Order;

/**
 * Construcción de URLs en un solo lugar.
 *
 * Las rutas sin extensión (/academia, /pagar/pedido) las resuelve
 * public/.htaccess en el servidor y bin/dev-router.php en local. El webhook
 * usa la ruta con .php para no depender de esa reescritura.
 */
final class Urls
{
    private readonly string $base;
    private readonly string $basePath;

    public function __construct(string $appUrl)
    {
        $this->base = rtrim($appUrl, '/');
        $this->basePath = rtrim((string) parse_url($this->base, PHP_URL_PATH), '/');
    }

    /** Ruta relativa al dominio, para enlaces internos. */
    public function path(string $path = '/'): string
    {
        return $this->basePath . '/' . ltrim($path, '/');
    }

    /** URL absoluta, para correos y para Mercado Pago. */
    public function absolute(string $path = '/'): string
    {
        return $this->base . '/' . ltrim($path, '/');
    }

    public function payStep(Order $order, bool $absolute = false): string
    {
        $path = '/pagar/pedido?' . http_build_query(['folio' => $order->folio, 't' => $order->accessToken]);

        return $absolute ? $this->absolute($path) : $this->path($path);
    }

    public function paymentResult(Order $order, bool $absolute = true): string
    {
        $path = '/pagar/resultado?' . http_build_query(['folio' => $order->folio, 't' => $order->accessToken]);

        return $absolute ? $this->absolute($path) : $this->path($path);
    }

    public function webhook(): string
    {
        return $this->absolute('/webhooks/mercadopago.php');
    }

    /**
     * ¿Puede Mercado Pago alcanzar esta URL? Solo con HTTPS y un dominio real.
     */
    public function isPublic(): bool
    {
        $host = (string) parse_url($this->base, PHP_URL_HOST);

        return str_starts_with($this->base, 'https://')
            && !in_array($host, ['localhost', '127.0.0.1'], true)
            && !str_ends_with($host, '.test')
            && !str_ends_with($host, '.local');
    }
}
