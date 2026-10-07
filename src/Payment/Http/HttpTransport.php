<?php

declare(strict_types=1);

namespace FiloAcademia\Payment\Http;

/**
 * Transporte HTTP mínimo. Existe para poder probar la integración sin red.
 */
interface HttpTransport
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     * @throws \RuntimeException Si la petición no llega a completarse (red, timeout).
     */
    public function send(string $method, string $url, array $headers, ?string $body): array;
}
