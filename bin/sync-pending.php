<?php

/**
 * Respaldo del webhook: consulta en Mercado Pago los pedidos sin pago
 * confirmado de los últimos 7 días y aplica lo que encuentre (incluidos los
 * correos de confirmación, que siguen saliendo una sola vez).
 *
 * Cubre: cliente que paga y cierra la ventana antes de volver al sitio, ficha
 * OXXO pagada días después con el webhook caído o mal configurado.
 *
 *   php bin/sync-pending.php        (cron cada 15 minutos; ver docs/DESPLIEGUE.md)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

/** @var FiloAcademia\App $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

// Una sola ejecución a la vez aunque el cron se encime.
$lock = fopen($app->rootDir . '/storage/sync-pending.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$checked = 0;
$changed = 0;
$errors = 0;

foreach ($app->orders()->findUnsettledSince(7) as $order) {
    $checked++;
    try {
        $updated = $app->paymentSync()->syncByFolio($order->folio);
        if ($updated !== null && $updated->status !== $order->status) {
            $changed++;
        }
    } catch (FiloAcademia\Payment\PaymentGatewayException $exception) {
        $errors++;
        $app->logger()->error('Revisión periódica: no se pudo consultar el pedido.', ['folio' => $order->folio, 'error' => $exception]);
    }
}

if ($changed > 0 || $errors > 0) {
    $app->logger()->info('Revisión periódica de pagos.', ['revisados' => $checked, 'actualizados' => $changed, 'errores' => $errors]);
}

echo "Revisados: {$checked} · actualizados: {$changed} · errores: {$errors}" . PHP_EOL;
