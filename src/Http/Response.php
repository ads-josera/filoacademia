<?php

declare(strict_types=1);

namespace FiloAcademia\Http;

/**
 * Envío de respuestas con las cabeceras de seguridad comunes.
 * (Apache añade las mismas desde public/.htaccess; aquí se repiten para que
 * también estén en el servidor de desarrollo y en cualquier hosting.)
 */
final class Response
{
    public static function html(string $body, int $status = 200, bool $cacheable = false): never
    {
        self::baseHeaders($status, $cacheable);
        header('Content-Type: text/html; charset=UTF-8');
        echo $body;
        exit;
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): never
    {
        self::baseHeaders($status, false);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** 303: tras un POST, el navegador pide la nueva página con GET (no reenvía el formulario). */
    public static function redirect(string $url, int $status = 303): never
    {
        self::baseHeaders($status, false);
        header('Location: ' . $url);
        exit;
    }

    public static function text(string $body, int $status = 200): never
    {
        self::baseHeaders($status, false);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $body;
        exit;
    }

    private static function baseHeaders(int $status, bool $cacheable): void
    {
        http_response_code($status);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        // Las páginas de pedido llevan datos personales: nunca en caché compartida.
        header('Cache-Control: ' . ($cacheable ? 'public, max-age=300' : 'no-store, private'));
    }
}
