<?php
/**
 * Versión de texto plano del aviso al taller (sin HTML: no se escapa).
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 */
?>
Nuevo pago · <?= $order->folio ?> · <?= $v->money($order->total) ?> <?= $order->currency ?>


Cliente: <?= $order->customerName ?>

WhatsApp: <?= $order->customerPhone ?>

Correo: <?= $order->customerEmail ?>

Entrega: <?= $order->deliveryName ?>

<?php if ($order->customerAddress !== null): ?>
Dirección: <?= $order->customerAddress ?>

<?php endif; ?>
<?php if ($order->customerNotes !== null): ?>
Notas: <?= $order->customerNotes ?>

<?php endif; ?>

<?php foreach ($order->lines as $line): ?>
- <?= $line['name'] ?> × <?= $line['qty'] ?>: <?= $v->money($line['unit_price'] * $line['qty']) ?>

<?php endforeach; ?>
- <?= $order->deliveryName ?>: <?= $v->money($order->deliveryAmount) ?>

<?php if ($order->insuranceAmount > 0): ?>
- Seguro: <?= $v->money($order->insuranceAmount) ?>

<?php endif; ?>
Total: <?= $v->money($order->total) ?>


ID de pago Mercado Pago: <?= $order->mpPaymentId ?> (<?= $order->mpPaymentMethod ?>)
