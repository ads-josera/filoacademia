<?php

declare(strict_types=1);

namespace FiloAcademia\Payment\MercadoPago;

/**
 * Verifica que una notificación la envió Mercado Pago (cabecera x-signature).
 *
 * Plantilla oficial: "id:[data.id_url];request-id:[x-request-id_header];ts:[ts_header];"
 * firmada con HMAC-SHA256 y la clave secreta del webhook.
 *
 * Se aceptan varias claves: los pagos de PRUEBA los procesa una aplicación
 * espejo del vendedor de prueba (TestApp-…), que firma con su propia clave,
 * distinta a la de la aplicación del cliente. Todas las claves son nuestras.
 *
 * Aun con firma válida, el webhook NO se cree: solo indica qué pago consultar
 * en la API. Esta verificación evita que cualquiera nos haga consultar en bucle.
 *
 * @see https://www.mercadopago.com.mx/developers/es/docs/your-integrations/notifications/webhooks
 */
final class WebhookSignature
{
    /** @var list<string> */
    private readonly array $secrets;

    /** @param string|list<string> $secrets */
    public function __construct(string|array $secrets)
    {
        $list = array_map('trim', is_array($secrets) ? $secrets : [$secrets]);
        $this->secrets = array_values(array_unique(array_filter($list, static fn (string $s): bool => $s !== '')));
    }

    public function isValid(string $signatureHeader, string $requestId, string $dataId): bool
    {
        if ($this->secrets === [] || $signatureHeader === '' || $dataId === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[trim($key)] = trim($value);
        }

        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';
        if ($ts === '' || $v1 === '' || !ctype_digit($ts)) {
            return false;
        }

        // No se rechaza por antigüedad de ts: los reintentos de Mercado Pago
        // pueden llegar horas después, y repetir una notificación es inofensivo
        // porque el estado se lee siempre de la API.

        // La documentación indica que un data.id alfanumérico va en minúsculas.
        $manifest = 'id:' . mb_strtolower($dataId) . ';';
        if ($requestId !== '') {
            $manifest .= 'request-id:' . $requestId . ';';
        }
        $manifest .= 'ts:' . $ts . ';';

        foreach ($this->secrets as $secret) {
            if (hash_equals(hash_hmac('sha256', $manifest, $secret), $v1)) {
                return true;
            }
        }

        return false;
    }
}
