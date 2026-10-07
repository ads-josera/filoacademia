<?php

declare(strict_types=1);

namespace FiloAcademia\Support;

/**
 * Bitácora en archivo: storage/logs/app-AAAA-MM.log, una línea JSON por evento.
 *
 * Nunca registres tarjetas, tokens de pago ni contraseñas: el contexto se
 * limpia de llaves sensibles, pero lo correcto es no pasarlas.
 */
final class Logger
{
    private const SENSITIVE_KEYS = ['token', 'access_token', 'password', 'card', 'security_code', 'identification'];

    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $line = json_encode([
            'time' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $this->redact($context),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $file = $this->directory . '/app-' . date('Y-m') . '.log';
        // Si el log no se puede escribir, no se rompe la petición del cliente.
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $context[$key] = '[oculto]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redact($value);
            } elseif ($value instanceof \Throwable) {
                $context[$key] = $value::class . ': ' . $value->getMessage();
            }
        }

        return $context;
    }
}
