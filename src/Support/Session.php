<?php

declare(strict_types=1);

namespace FiloAcademia\Support;

/**
 * Sesión mínima: solo guarda el token CSRF. No hay usuarios ni carrito en
 * sesión (el carrito vive en el navegador y se valida al enviarlo).
 */
final class Session
{
    public static function start(bool $secure): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('filo_sid');
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => $secure,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(mixed $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf'])
            && is_string($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }
}
