<?php

declare(strict_types=1);

namespace FiloAcademia\Payment\Http;

final class CurlTransport implements HttpTransport
{
    public function __construct(
        private readonly int $connectTimeout = 10,
        private readonly int $timeout = 30,
    ) {
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('No se pudo iniciar cURL.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            throw new \RuntimeException('Error de red: ' . $error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return ['status' => $status, 'body' => (string) $response];
    }
}
