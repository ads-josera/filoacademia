<?php

/**
 * Prueba de punta a punta de los correos por el camino REAL: crea un pedido de
 * prueba y le aplica un pago aprobado simulado con PaymentSyncService (el mismo
 * servicio que usan el webhook y la página de resultado). Salen los dos
 * correos —cliente y taller— exactamente como en producción.
 *
 *   php bin/mail-test-flow.php correo@destino
 *
 * El correo del taller va a los destinatarios configurados en
 * mail.admin_recipients. NO usar en producción: el pedido queda «pagado»
 * con un pago que no existe en Mercado Pago (folio de prueba en el log).
 */

declare(strict_types=1);

use FiloAcademia\Order\PaymentSnapshot;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

/** @var FiloAcademia\App $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

if ($app->config->isProduction()) {
    fwrite(STDERR, "No se ejecuta en producción.\n");
    exit(1);
}

$to = filter_var($argv[1] ?? '', FILTER_VALIDATE_EMAIL);
if ($to === false) {
    fwrite(STDERR, "Uso: php bin/mail-test-flow.php correo@destino\n");
    exit(1);
}

[$order, $errors] = $app->checkout()->placeOrder([
    'cart' => json_encode([
        ['removal' => 'remocion-2mm', 'extras' => ['punta'], 'qty' => 2],
        ['removal' => 'ninguna', 'extras' => ['oxido'], 'qty' => 1],
    ]),
    'delivery' => 'nacional',
    'insurance' => '1',
    'name' => 'José Prueba',
    'email' => $to,
    'phone' => '55 3005 0231',
    'address' => "Av. Reforma 123, Col. Juárez\nCP 06600, Ciudad de México",
    'notes' => 'Pedido de prueba de correos.',
], 'prueba-correos-' . bin2hex(random_bytes(4)));

if ($order === null) {
    fwrite(STDERR, 'No se pudo crear el pedido: ' . json_encode($errors, JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}

$updated = $app->paymentSync()->apply(new PaymentSnapshot(
    id: (string) random_int(10_000_000_000, 99_999_999_999),
    status: 'approved',
    statusDetail: 'accredited',
    externalReference: $order->folio,
    transactionAmount: (float) $order->total,
    currency: $order->currency,
    paymentMethodId: 'visa',
    ticketUrl: null,
));

echo sprintf("Pedido %s → %s. Correos enviados (cliente: %s; taller: %s).\n",
    $order->folio,
    $updated?->status->value ?? '¿?',
    $to,
    implode(', ', $app->orderEmails()->adminRecipients()),
);
