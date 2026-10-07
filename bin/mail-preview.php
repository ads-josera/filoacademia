<?php

/**
 * Envía los correos de «pago aprobado» de un pedido existente SIN tocar su
 * estado ni las marcas de envío. Para revisar el diseño en Mailpit (DDEV) o
 * en un buzón real. Usa la misma clase que el aviso automático.
 *
 *   php bin/mail-preview.php HF-123456 [correo@destino]
 *
 * Con [correo@destino], ambos correos (cliente y taller) van a esa dirección.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

/** @var FiloAcademia\App $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

$order = $app->orders()->findByFolio((string) ($argv[1] ?? ''));
$to = isset($argv[2]) ? filter_var($argv[2], FILTER_VALIDATE_EMAIL) : null;
if ($order === null || $to === false) {
    fwrite(STDERR, "Uso: php bin/mail-preview.php HF-123456 [correo@destino]\n");
    exit(1);
}

$emails = $app->orderEmails();
$customer = $emails->customerPaid($order, '[Prueba] ');
if ($to !== null) {
    $customer = new FiloAcademia\Mail\Email([$to], $customer->subject, $customer->html, $customer->text, $customer->replyTo, $customer->inlineImages);
}
$admins = $to !== null ? [$to] : ($emails->adminRecipients() ?: ['taller@ejemplo.mx']);

$app->mailer()->send($customer);
$app->mailer()->send($emails->adminPaid($order, '[Prueba] ', $admins));

echo sprintf('Enviados los dos correos de %s a %s.', $order->folio, $to ?? 'los destinatarios configurados') . PHP_EOL;
