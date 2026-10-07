<?php
/**
 * Paso 2 del pago: cobrar el pedido ya creado.
 *
 * Modo «pro»: un botón que lleva a la ventana segura de Mercado Pago.
 * Modo «bricks»: el formulario de Mercado Pago dentro de esta página
 * (assets/js/bricks.js).
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Order\Order $order
 * @var array<string, mixed> $business
 * @var string $mode           pro | bricks
 * @var string $publicKey
 * @var int $maxInstallments
 * @var string $csrf
 * @var string|null $gatewayError  Mercado Pago no respondió al preparar el cobro.
 * @var bool $retry            El pedido tuvo un intento rechazado o cancelado.
 */
$whatsappText = $v->whatsappOrderMessage($order, 'Hola, tengo un problema para pagar mi pedido en el sitio.');
?>
<div class="checkout">
  <div class="checkout__head">
    <ol class="progress" aria-label="Pasos del pago">
      <li>1 · Datos</li>
      <li aria-current="step">2 · Pago</li>
      <li>3 · Confirmación</li>
    </ol>
    <h1 class="section-title">Pago del pedido <span class="num u-nowrap"><?= $v->e($order->folio) ?></span></h1>
    <p class="section-lead">Revisa el resumen y paga de forma segura con Mercado Pago: tarjeta de crédito o débito, efectivo en OXXO o tu cuenta de Mercado Pago.</p>
  </div>

  <div class="checkout__grid">
    <div>
      <?php if ($retry): ?>
        <div class="alert alert--warning form-errors" role="status">
          <span class="alert__icon" aria-hidden="true">!</span>
          <div><p>El intento anterior no se completó. No se te cobró: puedes intentarlo de nuevo con otro medio de pago.</p></div>
        </div>
      <?php endif; ?>

      <?php if ($gatewayError !== null): ?>
        <section class="panel">
          <div class="alert alert--danger" role="alert">
            <span class="alert__icon" aria-hidden="true">!</span>
            <div>
              <p><b>No pudimos conectar con Mercado Pago.</b> Tu pedido quedó guardado con el folio <?= $v->e($order->folio) ?> y no se ha cobrado nada.</p>
              <p>Intenta de nuevo en unos momentos. Si sigue fallando, escríbenos y lo resolvemos contigo.</p>
            </div>
          </div>
          <div class="status-hero__actions">
            <a class="btn" href="<?= $v->e($v->urls()->payStep($order)) ?>">Intentar de nuevo</a>
            <?= $v->render('partials/whatsapp-link', ['label' => 'Escribir por WhatsApp', 'text' => $whatsappText, 'class' => 'btn btn--ghost']) ?>
          </div>
        </section>
      <?php elseif ($mode === 'bricks'): ?>
        <section class="panel" aria-labelledby="pago-title">
          <h2 class="panel__title" id="pago-title">Medio de pago</h2>
          <div class="brick-loading" data-brick-loading role="status">
            <span class="spinner" aria-hidden="true"></span> Cargando el formulario seguro de Mercado Pago…
          </div>
          <div id="payment-brick" data-payment-brick
            data-public-key="<?= $v->e($publicKey) ?>"
            data-amount="<?= (int) $order->total ?>"
            data-preference-id="<?= $v->e((string) $order->mpPreferenceId) ?>"
            data-email="<?= $v->e($order->customerEmail) ?>"
            data-max-installments="<?= (int) $maxInstallments ?>"
            data-endpoint="<?= $v->e($v->url('/api/pago')) ?>"
            data-folio="<?= $v->e($order->folio) ?>"
            data-token="<?= $v->e($order->accessToken) ?>"
            data-csrf="<?= $v->e($csrf) ?>"></div>
          <div class="alert alert--danger" data-brick-error role="alert" hidden>
            <span class="alert__icon" aria-hidden="true">!</span>
            <div>
              <p data-brick-error-text>No se pudo procesar el pago. No se te cobró.</p>
              <p><a href="<?= $v->e($v->urls()->payStep($order)) ?>">Recargar e intentar de nuevo</a> o <?= $v->render('partials/whatsapp-link', ['label' => 'escríbenos por WhatsApp', 'text' => $whatsappText]) ?>.</p>
            </div>
          </div>
        </section>
      <?php else: ?>
        <section class="panel" aria-labelledby="pago-title">
          <h2 class="panel__title" id="pago-title">Pagar con Mercado Pago</h2>
          <p class="section-lead">Te llevamos a la página segura de Mercado Pago. Al terminar regresas aquí para ver la confirmación.</p>
          <div class="u-mt-4">
            <a class="btn btn--block" href="<?= $v->e((string) $order->mpInitPoint) ?>" data-pay-redirect>Pagar <?= $v->money($order->total) ?> MXN</a>
          </div>
          <p class="pay-methods">Tarjeta de crédito o débito · efectivo en OXXO · cuenta de Mercado Pago</p>
        </section>
      <?php endif; ?>
      <p class="secure-note">Tus datos de pago los procesa Mercado Pago. Nosotros nunca vemos ni guardamos tu tarjeta.</p>
      <section class="panel u-mt-6">
        <h2 class="panel__title">Datos de contacto</h2>
        <dl class="detail-list">
          <dt>Nombre</dt><dd><?= $v->e($order->customerName) ?></dd>
          <dt>Correo</dt><dd><?= $v->e($order->customerEmail) ?></dd>
          <dt>WhatsApp</dt><dd><?= $v->e($order->customerPhone) ?></dd>
          <?php if ($order->customerAddress !== null): ?><dt>Dirección</dt><dd><?= $v->e($order->customerAddress) ?></dd><?php endif; ?>
        </dl>
      </section>
    </div>

    <aside class="checkout__aside" aria-labelledby="resumen-title">
      <section class="panel">
        <h2 class="panel__title" id="resumen-title">Resumen</h2>
        <?= $v->render('partials/order-summary', ['order' => $order]) ?>
      </section>
    </aside>
  </div>
</div>
