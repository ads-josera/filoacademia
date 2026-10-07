<?php
/**
 * Correo al taller: nuevo pago aprobado. Trae todo lo necesario para
 * contactar al cliente sin abrir otro sistema.
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 * @var array<string, mixed> $business
 */
$label = 'padding:6px 16px 6px 0;color:#68635a;font-size:13px;vertical-align:top;white-space:nowrap;';
$value = 'padding:6px 0;font-size:14px;';
$whatsappDigits = preg_replace('/\D+/', '', $order->customerPhone) ?? '';
if (strlen($whatsappDigits) === 10) {
    $whatsappDigits = '52' . $whatsappDigits;
}
ob_start();
?>
<h1 style="margin:0 0 8px;font-family:Georgia,serif;font-size:22px;font-weight:600;">Nuevo pago · <?= $v->e($order->folio) ?></h1>
<p style="margin:0 0 16px;color:#54514b;">Pago aprobado por <?= $v->money($order->total) ?> <?= $v->e($order->currency) ?>. Contacta al cliente para agendar.</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
  <tr><td style="<?= $label ?>">Cliente</td><td style="<?= $value ?>"><strong><?= $v->e($order->customerName) ?></strong></td></tr>
  <tr><td style="<?= $label ?>">WhatsApp</td><td style="<?= $value ?>"><a href="https://wa.me/<?= $v->e($whatsappDigits) ?>" style="color:#b23a2a;"><?= $v->e($order->customerPhone) ?></a></td></tr>
  <tr><td style="<?= $label ?>">Correo</td><td style="<?= $value ?>"><a href="mailto:<?= $v->e($order->customerEmail) ?>" style="color:#b23a2a;"><?= $v->e($order->customerEmail) ?></a></td></tr>
  <tr><td style="<?= $label ?>">Entrega</td><td style="<?= $value ?>"><?= $v->e($order->deliveryName) ?></td></tr>
  <?php if ($order->customerAddress !== null): ?>
    <tr><td style="<?= $label ?>">Dirección</td><td style="<?= $value ?>"><?= nl2br($v->e($order->customerAddress)) ?></td></tr>
  <?php endif; ?>
  <?php if ($order->customerNotes !== null): ?>
    <tr><td style="<?= $label ?>">Notas</td><td style="<?= $value ?>"><?= nl2br($v->e($order->customerNotes)) ?></td></tr>
  <?php endif; ?>
  <tr><td style="<?= $label ?>">Cuchillos</td><td style="<?= $value ?>"><?= (int) $order->knifeCount() ?></td></tr>
</table>

<?= $v->render('emails/order-table', ['order' => $order]) ?>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0;">
  <tr><td style="<?= $label ?>">ID de pago MP</td><td style="<?= $value ?>"><?= $v->e((string) $order->mpPaymentId) ?></td></tr>
  <tr><td style="<?= $label ?>">Medio</td><td style="<?= $value ?>"><?= $v->e((string) $order->mpPaymentMethod) ?></td></tr>
  <tr><td style="<?= $label ?>">Fecha de pago</td><td style="<?= $value ?>"><?= $v->e((string) $order->paidAt) ?></td></tr>
  <tr><td style="<?= $label ?>">Modo de cobro</td><td style="<?= $value ?>"><?= $v->e($order->checkoutMode === 'bricks' ? 'Formulario en el sitio (Bricks)' : 'Checkout Pro') ?></td></tr>
</table>
<?php
echo $v->render('emails/layout', [
    'preheader' => sprintf('%s pagó %s · %s', $order->customerName, $v->money($order->total), $order->deliveryName),
    'body' => (string) ob_get_clean(),
]);
