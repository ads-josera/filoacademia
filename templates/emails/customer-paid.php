<?php
/**
 * Correo al cliente: pago aprobado.
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 * @var array<string, mixed> $business
 */
$firstName = explode(' ', $order->customerName)[0];
ob_start();
?>
<h1 style="margin:0 0 8px;font-family:Georgia,serif;font-size:24px;font-weight:600;">Pago recibido</h1>
<p style="margin:0 0 16px;color:#54514b;">Hola <?= $v->e($firstName) ?>, gracias por confiarnos tus cuchillos. Este es el comprobante de tu pedido <strong style="color:#1a1918;"><?= $v->e($order->folio) ?></strong>.</p>

<?= $v->render('emails/order-table', ['order' => $order]) ?>

<h2 style="margin:0 0 8px;font-family:Georgia,serif;font-size:17px;">Qué sigue</h2>
<?php if ($order->deliveryId === 'taller'): ?>
  <p style="margin:0 0 16px;color:#54514b;">Tráenos tus cuchillos al taller: <?= $v->e(implode(', ', $business['address_lines'])) ?>. Horario: <?= $v->e(implode(' · ', $business['hours'])) ?>. Menciona tu folio al llegar.</p>
<?php else: ?>
  <p style="margin:0 0 16px;color:#54514b;">Te escribimos por WhatsApp al <?= $v->e($order->customerPhone) ?> en <?= $v->e($business['response_time']) ?> para agendar la recolección o enviarte tu guía de paquetería.</p>
<?php endif; ?>
<p style="margin:0 0 16px;color:#54514b;">Revisamos cada pieza antes de tocarla. Si el trabajo que necesita es distinto al que cotizaste, te lo confirmamos antes de hacer nada.</p>
<p style="margin:0;color:#54514b;">¿Dudas? Responde a este correo o escríbenos por WhatsApp al <?= $v->e($business['phone_display']) ?>.</p>
<?php
echo $v->render('emails/layout', [
    'preheader' => sprintf('Recibimos tu pago de %s · pedido %s', $v->money($order->total), $order->folio),
    'body' => (string) ob_get_clean(),
]);
