<?php
/**
 * Correo al cliente: pago aprobado.
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 * @var array<string, mixed> $business
 */
$firstName = $v->firstName($order->customerName);
$display = "font-family:'Shippori Mincho',Georgia,'Times New Roman',serif;font-weight:600;color:#1a1918;";
$muted = 'margin:0 0 16px;color:#54514b;';
ob_start();
?>
<p style="margin:0 0 8px;font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#b23a2a;">Pedido <?= $v->e($order->folio) ?></p>
<h1 class="h1" style="margin:0 0 12px;<?= $display ?>font-size:26px;line-height:1.25;">Pago recibido</h1>
<p style="<?= $muted ?>">Hola <?= $v->e($firstName) ?>, gracias por confiarnos tus cuchillos. Este es el comprobante de tu pedido.</p>

<?= $v->render('emails/order-table', ['order' => $order]) ?>

<h2 style="margin:0 0 8px;<?= $display ?>font-size:18px;">Qué sigue</h2>
<?php if ($order->deliveryId === 'taller'): ?>
  <p style="<?= $muted ?>">Tráenos tus cuchillos al taller: <?= $v->e(implode(', ', $business['address_lines'])) ?>. Horario: <?= $v->e(implode(' · ', $business['hours'])) ?>. Menciona tu folio al llegar.</p>
<?php else: ?>
  <p style="<?= $muted ?>">Te escribimos por WhatsApp al <?= $v->e($order->customerPhone) ?> en <?= $v->e($business['response_time']) ?> para agendar la recolección o enviarte tu guía de paquetería.</p>
<?php endif; ?>
<p style="<?= $muted ?>">Revisamos cada pieza antes de tocarla. Si el trabajo que necesita es distinto al que cotizaste, te lo confirmamos antes de hacer nada.</p>

<?= $v->render('emails/button', ['href' => $v->urls()->paymentResult($order), 'label' => 'Ver mi pedido']) ?>
<p style="margin:16px 0 0;font-size:14px;color:#54514b;">¿Dudas? Responde a este correo o <a href="<?= $v->e($v->whatsappUrl($v->whatsappOrderMessage($order, 'Hola, ya pagué mi pedido en el sitio y tengo una duda.'))) ?>" target="_blank" style="color:#b23a2a;text-decoration:underline;">escríbenos por WhatsApp</a>.</p>
<?php
echo $v->render('emails/layout', [
    'preheader' => sprintf('Recibimos tu pago de %s · pedido %s. Te contactamos en %s.', $v->money($order->total), $order->folio, $business['response_time']),
    'body' => (string) ob_get_clean(),
]);
