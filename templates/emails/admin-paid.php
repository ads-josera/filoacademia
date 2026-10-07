<?php
/**
 * Correo al taller: nuevo pago aprobado. Trae todo lo necesario para
 * contactar al cliente sin abrir otro sistema.
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 * @var array<string, mixed> $business
 */
$display = "font-family:'Shippori Mincho',Georgia,'Times New Roman',serif;font-weight:600;color:#1a1918;";
$label = 'padding:6px 16px 6px 0;color:#68635a;font-size:13px;vertical-align:top;white-space:nowrap;';
$value = 'padding:6px 0;font-size:14px;color:#1a1918;';
$link = 'color:#b23a2a;text-decoration:underline;';

$whatsappDigits = preg_replace('/\D+/', '', $order->customerPhone) ?? '';
if (strlen($whatsappDigits) === 10) {
    $whatsappDigits = '52' . $whatsappDigits;
}
$firstName = $v->firstName($order->customerName);
$greeting = sprintf('Hola %s, te escribimos de %s por tu pedido %s.', $firstName, $business['name'], $order->folio);
$customerWhatsapp = 'https://wa.me/' . $whatsappDigits . '?text=' . rawurlencode($greeting);
ob_start();
?>
<p style="margin:0 0 8px;font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#2f6b3a;">● Pago aprobado</p>
<h1 class="h1" style="margin:0 0 4px;<?= $display ?>font-size:26px;line-height:1.25;"><?= $v->money($order->total) ?> <?= $v->e($order->currency) ?></h1>
<p style="margin:0 0 20px;color:#54514b;">Pedido <strong style="color:#1a1918;"><?= $v->e($order->folio) ?></strong> · <?= (int) $order->knifeCount() ?> <?= $order->knifeCount() === 1 ? 'cuchillo' : 'cuchillos' ?> · <?= $v->e($order->deliveryName) ?></p>

<?= $v->render('emails/button', ['href' => $customerWhatsapp, 'label' => 'Escribir a ' . $firstName . ' por WhatsApp']) ?>

<h2 style="margin:24px 0 8px;<?= $display ?>font-size:18px;">Cliente</h2>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
  <tr><td style="<?= $label ?>">Nombre</td><td style="<?= $value ?>"><strong><?= $v->e($order->customerName) ?></strong></td></tr>
  <tr><td style="<?= $label ?>">WhatsApp</td><td style="<?= $value ?>"><a href="<?= $v->e($customerWhatsapp) ?>" target="_blank" style="<?= $link ?>"><?= $v->e($order->customerPhone) ?></a></td></tr>
  <tr><td style="<?= $label ?>">Correo</td><td style="<?= $value ?>"><a href="mailto:<?= $v->e($order->customerEmail) ?>" style="<?= $link ?>"><?= $v->e($order->customerEmail) ?></a></td></tr>
  <?php if ($order->customerAddress !== null): ?>
    <tr><td style="<?= $label ?>">Dirección</td><td style="<?= $value ?>"><a href="<?= $v->e($v->mapsUrl($order->customerAddress)) ?>" target="_blank" style="<?= $link ?>"><?= nl2br($v->e($order->customerAddress)) ?></a></td></tr>
  <?php endif; ?>
  <?php if ($order->customerNotes !== null): ?>
    <tr><td style="<?= $label ?>">Notas</td><td style="<?= $value ?>"><?= nl2br($v->e($order->customerNotes)) ?></td></tr>
  <?php endif; ?>
</table>

<h2 style="margin:24px 0 0;<?= $display ?>font-size:18px;">Servicios</h2>
<?= $v->render('emails/order-table', ['order' => $order]) ?>

<?php if ($order->mpPaymentId !== null): ?>
<h2 style="margin:0 0 8px;<?= $display ?>font-size:18px;">Pago</h2>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0;">
  <tr><td style="<?= $label ?>">Medio</td><td style="<?= $value ?>"><?= $v->e($v->paymentMethodLabel($order->mpPaymentMethod)) ?></td></tr>
  <tr><td style="<?= $label ?>">Fecha</td><td style="<?= $value ?>"><?= $v->e($v->dateTime($order->paidAt)) ?></td></tr>
  <tr><td style="<?= $label ?>">ID Mercado Pago</td><td style="<?= $value ?>"><?= $v->e($order->mpPaymentId) ?></td></tr>
  <tr><td style="<?= $label ?>">Cobro</td><td style="<?= $value ?>"><?= $v->e($order->checkoutMode === 'bricks' ? 'Formulario en el sitio (Bricks)' : 'Checkout Pro') ?></td></tr>
</table>
<?php endif; ?>
<p style="margin:20px 0 0;font-size:13px;color:#68635a;">Responder a este correo le escribe directamente al cliente.</p>
<?php
echo $v->render('emails/layout', [
    'preheader' => sprintf('%s pagó %s · %s', $order->customerName, $v->money($order->total), $order->deliveryName),
    'body' => (string) ob_get_clean(),
]);
