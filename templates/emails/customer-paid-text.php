<?php
/**
 * Versión de texto plano del correo al cliente (sin HTML: no se escapa).
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 * @var array<string, mixed> $business
 */
?>
Pago recibido · pedido <?= $order->folio ?>


Hola <?= $v->firstName($order->customerName) ?>, gracias por confiarnos tus cuchillos.

<?php foreach ($order->lines as $line): ?>
- <?= $line['name'] ?> × <?= $line['qty'] ?>: <?= $v->money($line['unit_price'] * $line['qty']) ?>

<?php endforeach; ?>
- <?= $order->deliveryName ?>: <?= $order->deliveryAmount === 0 ? 'Sin costo' : $v->money($order->deliveryAmount) ?>

<?php if ($order->insuranceAmount > 0): ?>
- Seguro de paquetería: <?= $v->money($order->insuranceAmount) ?>

<?php endif; ?>
Total pagado: <?= $v->money($order->total) ?> <?= $order->currency ?>


<?php if ($order->deliveryId === 'taller'): ?>
Tráenos tus cuchillos al taller: <?= implode(', ', $business['address_lines']) ?>. Horario: <?= implode(' · ', $business['hours']) ?>.
<?php else: ?>
Te escribimos por WhatsApp al <?= $order->customerPhone ?> en <?= $business['response_time'] ?> para agendar la recolección o enviarte tu guía.
<?php endif; ?>

Revisamos cada pieza antes de tocarla. Si el trabajo es distinto al cotizado, te lo confirmamos antes de hacer nada.

<?= $business['name'] ?> · WhatsApp <?= $business['phone_display'] ?> · <?= $business['email'] ?>
