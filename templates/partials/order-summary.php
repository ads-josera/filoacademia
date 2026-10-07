<?php
/**
 * Resumen de un pedido ya guardado (pantallas de pago y resultado).
 * Los correos tienen su propia versión con estilos en línea.
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 */
?>
<ul class="order-lines">
  <?php foreach ($order->lines as $line): ?>
    <li><span><?= $v->e($line['name']) ?>&nbsp;×&nbsp;<?= (int) $line['qty'] ?></span><span><?= $v->money($line['unit_price'] * $line['qty']) ?></span></li>
  <?php endforeach; ?>
</ul>
<div class="summary">
  <div class="summary__row"><span><?= $v->e($order->deliveryName) ?></span><span><?= $order->deliveryAmount === 0 ? 'Sin costo' : $v->money($order->deliveryAmount) ?></span></div>
  <?php if ($order->insuranceAmount > 0): ?>
    <div class="summary__row"><span>Seguro de paquetería</span><span><?= $v->money($order->insuranceAmount) ?></span></div>
  <?php endif; ?>
  <div class="summary__row summary__row--total"><span>Total</span><span><?= $v->money($order->total) ?> <?= $v->e($order->currency) ?></span></div>
</div>
