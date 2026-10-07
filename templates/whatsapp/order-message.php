<?php
/**
 * Mensaje de WhatsApp con los datos del pedido, para que el taller pueda
 * atender sin pedirle nada más al cliente. Texto plano (se codifica en la URL).
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 * @var string $intro   Primera línea, según la situación.
 */
?>
<?= $intro ?>


Pedido: <?= $order->folio ?>

<?php foreach ($order->lines as $line): ?>
• <?= $line['name'] ?> × <?= $line['qty'] ?> — <?= $v->money($line['unit_price'] * $line['qty']) ?>

<?php endforeach; ?>
• <?= $order->deliveryName ?> — <?= $order->deliveryAmount === 0 ? 'sin costo' : $v->money($order->deliveryAmount) ?>

<?php if ($order->insuranceAmount > 0): ?>
• Seguro de paquetería — <?= $v->money($order->insuranceAmount) ?>

<?php endif; ?>

Total: <?= $v->money($order->total) ?> <?= $order->currency ?>

Nombre: <?= $order->customerName ?>
