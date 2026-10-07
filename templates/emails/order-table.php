<?php
/**
 * Tabla de importes del pedido para correos (compartida cliente / taller).
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 */
$cell = 'padding:10px 0;border-bottom:1px solid #ddd6c9;font-size:14px;color:#1a1918;';
$amount = $cell . 'text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;';
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0 24px;">
  <?php foreach ($order->lines as $line): ?>
    <tr>
      <td style="<?= $cell ?>"><?= $v->e($line['name']) ?>&nbsp;×&nbsp;<?= (int) $line['qty'] ?><br><span style="color:#68635a;font-size:12px;"><?= $v->money($line['unit_price']) ?> por cuchillo</span></td>
      <td style="<?= $amount ?>"><?= $v->money($line['unit_price'] * $line['qty']) ?></td>
    </tr>
  <?php endforeach; ?>
  <tr>
    <td style="<?= $cell ?>"><?= $v->e($order->deliveryName) ?></td>
    <td style="<?= $amount ?>"><?= $order->deliveryAmount === 0 ? 'Sin costo' : $v->money($order->deliveryAmount) ?></td>
  </tr>
  <?php if ($order->insuranceAmount > 0): ?>
    <tr>
      <td style="<?= $cell ?>">Seguro de paquetería (hasta $5,000 MXN)</td>
      <td style="<?= $amount ?>"><?= $v->money($order->insuranceAmount) ?></td>
    </tr>
  <?php endif; ?>
  <tr>
    <td style="padding:12px 0 0;font-size:15px;font-weight:700;color:#1a1918;">Total pagado</td>
    <td style="padding:12px 0 0;font-size:20px;font-weight:600;text-align:right;white-space:nowrap;font-family:'Shippori Mincho',Georgia,serif;color:#1a1918;"><?= $v->money($order->total) ?> <?= $v->e($order->currency) ?></td>
  </tr>
</table>
