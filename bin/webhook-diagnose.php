<?php

/**
 * Diagnóstico de firma del webhook: prueba contra el ÚLTIMO aviso rechazado
 * (storage/logs/webhook-last-invalid.json) varias formas de armar el manifiesto
 * con la clave de config.php, y dice cuál coincide. No imprime la clave.
 *
 *   php bin/webhook-diagnose.php
 *
 * Para probar OTRA clave sin guardarla (no queda en el historial):
 *   read -rsp 'Clave a probar: ' WEBHOOK_SECRET_TRY && export WEBHOOK_SECRET_TRY && php bin/webhook-diagnose.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$config = require $root . '/config/config.php';
$secretRaw = (string) ($config['mercadopago']['webhook_secret'] ?? '');
$file = $root . '/storage/logs/webhook-last-invalid.json';

if (!is_file($file)) {
    exit("No hay avisos rechazados guardados todavía ($file).\n");
}
$n = json_decode((string) file_get_contents($file), true);

$parts = [];
foreach (explode(',', (string) $n['x_signature']) as $pair) {
    [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, '');
    $parts[trim($k)] = trim($v);
}
$ts = $parts['ts'] ?? '';
$v1 = $parts['v1'] ?? '';
$rid = (string) $n['x_request_id'];
parse_str((string) $n['query_string'], $rawQuery);   // PHP convierte «data.id» en «data_id»
preg_match('/(?:^|&)data\.id=([^&]*)/', (string) $n['query_string'], $m);
$idUrl = isset($m[1]) ? urldecode($m[1]) : (string) ($rawQuery['data_id'] ?? $rawQuery['id'] ?? '');
$body = json_decode((string) $n['body'], true) ?: [];
$idBody = (string) ($body['data']['id'] ?? '');

echo "Aviso recibido: {$n['received_at']}\n";
echo "query: {$n['query_string']}\n";
echo "x-signature: ts=$ts, v1=" . substr($v1, 0, 12) . "…\n";
echo "x-request-id: $rid\n";
echo "data.id en URL: $idUrl · en cuerpo: $idBody · type cuerpo: " . ($body['type'] ?? '-') . " · live_mode: " . var_export($body['live_mode'] ?? null, true) . "\n";
echo "clave: " . strlen($secretRaw) . " caracteres" . ($secretRaw !== trim($secretRaw) ? ' (¡con espacios alrededor!)' : '') . "\n\n";

$secrets = ['clave de config.php' => $secretRaw, 'clave de config.php sin espacios' => trim($secretRaw)];
$try = (string) getenv('WEBHOOK_SECRET_TRY');
if ($try !== '') {
    $secrets['clave probada (WEBHOOK_SECRET_TRY)'] = trim($try);
    echo "También se prueba una clave adicional de " . strlen(trim($try)) . " caracteres (no se guarda).\n\n";
}
$ids = array_unique(array_filter([$idUrl, strtolower($idUrl), $idBody]));
$manifests = [];
foreach ($ids as $id) {
    $manifests["id:$id;request-id:$rid;ts:$ts;"] = "oficial (id=$id)";
    $manifests["id:$id;ts:$ts;"] = "sin request-id (id=$id)";
    $manifests["id:$id;request-id:$rid;ts:$ts"] = "sin «;» final (id=$id)";
}
$manifests["request-id:$rid;ts:$ts;"] = 'sin id';

$found = false;
foreach ($secrets as $secretName => $secret) {
    foreach ($manifests as $manifest => $label) {
        if (hash_equals(hash_hmac('sha256', $manifest, $secret), $v1)) {
            echo "✔ COINCIDE: $label con $secretName\n   manifiesto: $manifest\n";
            $found = true;
        }
    }
}
if (!$found) {
    echo "✘ Ninguna variante coincide con ninguna de las claves probadas.\n";
    echo "  → Mercado Pago firma estos avisos con una clave distinta.\n";
}
