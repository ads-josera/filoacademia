<?php
/**
 * Paso 3: resultado del pago. Cada estado del pedido tiene su pantalla.
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 * @var array<string, mixed> $business
 * @var bool $syncFailed  No se pudo consultar a Mercado Pago en esta visita.
 */
use FiloAcademia\Order\OrderStatus;

$whatsapp = 'https://wa.me/' . $business['whatsapp'] . '?text=' . rawurlencode('Hola, mi pedido es ' . $order->folio);
$isOxxoLike = $order->mpTicketUrl !== null;

$state = match ($order->status) {
    OrderStatus::Approved => [
        'icon' => 'success', 'symbol' => '✓',
        'title' => 'Pago recibido',
        'body' => sprintf('Te enviamos la confirmación a %s. Te contactamos por WhatsApp en %s para agendar la recolección o enviarte tu guía.', $order->customerEmail, $business['response_time']),
    ],
    OrderStatus::Pending => $order->mpPaymentId === null
        ? [
            'icon' => 'warning', 'symbol' => '…',
            'title' => 'Tu pedido está guardado, falta el pago',
            'body' => 'No recibimos ningún pago para este pedido. Puedes pagarlo ahora; si ya pagaste y saliste antes de volver aquí, la confirmación te llegará por correo en unos minutos.',
        ]
        : ($isOxxoLike
        ? [
            'icon' => 'warning', 'symbol' => '…',
            'title' => 'Falta completar tu pago',
            'body' => 'Tu ficha de pago está lista. Págala antes de su vencimiento; en cuanto se acredite te llega la confirmación por correo.',
        ]
        : [
            'icon' => 'warning', 'symbol' => '…',
            'title' => 'Esperando confirmación del pago',
            'body' => 'Todavía no recibimos la confirmación de Mercado Pago. Puede tardar unos minutos; esta página se actualiza sola.',
        ]),
    OrderStatus::InProcess => [
        'icon' => 'warning', 'symbol' => '…',
        'title' => 'Tu pago está en revisión',
        'body' => 'Mercado Pago está revisando el pago; normalmente se resuelve en minutos y como máximo en 2 días hábiles. Te avisamos por correo en cuanto se apruebe.',
    ],
    OrderStatus::Rejected, OrderStatus::Cancelled => [
        'icon' => 'danger', 'symbol' => '!',
        'title' => 'El pago no se completó',
        'body' => 'No se realizó ningún cargo. Puedes intentarlo de nuevo con otra tarjeta u otro medio de pago.',
    ],
    OrderStatus::Refunded => [
        'icon' => 'warning', 'symbol' => '↺',
        'title' => 'Pago reembolsado',
        'body' => 'Este pedido fue reembolsado. Si tienes dudas, escríbenos.',
    ],
};
$autoRefresh = in_array($order->status, [OrderStatus::InProcess], true)
    || ($order->status === OrderStatus::Pending && !$isOxxoLike && $order->mpPaymentId !== null);
?>
<div class="checkout">
  <div class="checkout__head">
    <ol class="progress" aria-label="Pasos del pago">
      <li>1 · Datos</li>
      <li>2 · Pago</li>
      <li aria-current="step">3 · Confirmación</li>
    </ol>
  </div>

  <section class="status-hero" aria-labelledby="estado-title"<?= $autoRefresh ? ' data-auto-refresh="15"' : '' ?><?= in_array($order->status, [OrderStatus::Approved, OrderStatus::InProcess], true) || $isOxxoLike ? ' data-clear-cart' : '' ?>>
    <div class="status-hero__icon status-hero__icon--<?= $v->e($state['icon']) ?>" aria-hidden="true"><?= $v->e($state['symbol']) ?></div>
    <h1 class="status-hero__title" id="estado-title"><?= $v->e($state['title']) ?></h1>
    <p><?= $v->e($state['body']) ?></p>
    <p class="status-hero__folio num">Folio <span class="u-nowrap"><?= $v->e($order->folio) ?></span></p>

    <?php if ($syncFailed): ?>
      <p class="alert alert--info">No pudimos consultar el estado más reciente con Mercado Pago; puede tardar unos minutos en actualizarse. Si ya pagaste, la confirmación te llegará por correo.</p>
    <?php endif; ?>

    <div class="status-hero__actions">
      <?php if ($order->status === OrderStatus::Pending && $isOxxoLike): ?>
        <a class="btn" href="<?= $v->e($order->mpTicketUrl) ?>" target="_blank" rel="noopener">Ver mi ficha de pago</a>
      <?php elseif ($order->status->acceptsPayment()): ?>
        <a class="btn" href="<?= $v->e($v->urls()->payStep($order)) ?>"><?= $order->mpPaymentId === null ? 'Pagar ahora' : 'Intentar el pago de nuevo' ?></a>
      <?php else: ?>
        <a class="btn" href="<?= $v->e($v->url('/')) ?>">Volver al inicio</a>
      <?php endif; ?>
      <a class="btn btn--ghost" href="<?= $v->e($whatsapp) ?>" rel="noopener">Escribir por WhatsApp</a>
    </div>
  </section>

  <div class="checkout__grid u-mt-6">
    <section class="panel" aria-labelledby="resumen-title">
      <h2 class="panel__title" id="resumen-title">Resumen del pedido</h2>
      <?= $v->render('partials/order-summary', ['order' => $order]) ?>
    </section>
    <section class="panel" aria-labelledby="siguiente-title">
      <h2 class="panel__title" id="siguiente-title"><?= $order->deliveryId === 'taller' ? 'Tráelo al taller' : 'Recolección' ?></h2>
      <?php if ($order->deliveryId === 'taller'): ?>
        <p class="section-lead"><?= $v->e(implode(', ', $business['address_lines'])) ?>.</p>
        <p class="section-lead"><?= $v->e(implode(' · ', $business['hours'])) ?>.</p>
      <?php else: ?>
        <dl class="detail-list">
          <dt>Entrega</dt><dd><?= $v->e($order->deliveryName) ?></dd>
          <?php if ($order->customerAddress !== null): ?><dt>Dirección</dt><dd><?= $v->e($order->customerAddress) ?></dd><?php endif; ?>
          <dt>WhatsApp</dt><dd><?= $v->e($order->customerPhone) ?></dd>
        </dl>
      <?php endif; ?>
    </section>
  </div>
</div>
