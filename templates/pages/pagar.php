<?php
/**
 * Paso 1 del pago: forma de entrega y datos de contacto.
 *
 * El resumen del carrito lo pinta assets/js/checkout.js (el carrito vive en el
 * navegador); el servidor recalcula todo al recibir el formulario.
 *
 * @var FiloAcademia\Support\View $v
 * @var FiloAcademia\Catalog\Catalog $catalogModel
 * @var array<string, mixed> $business
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var string $csrf
 */
$insurance = $catalogModel->insurance();
$selectedDelivery = $old['delivery'] ?? '';
$fieldError = static function (string $field) use ($errors, $v): string {
    return isset($errors[$field])
        ? '<p class="field__error" id="error-' . $v->e($field) . '">' . $v->e($errors[$field]) . '</p>'
        : '';
};
$invalid = static fn (string $field): string => isset($errors[$field])
    ? ' aria-invalid="true" aria-describedby="error-' . $field . '"'
    : '';
$labels = ['cart' => 'Carrito', 'name' => 'Nombre', 'email' => 'Correo', 'phone' => 'WhatsApp', 'address' => 'Dirección', 'notes' => 'Notas', 'form' => 'Formulario', 'delivery' => 'Entrega'];
?>
<div class="checkout">
  <div class="checkout__head">
    <ol class="progress" aria-label="Pasos del pago">
      <li aria-current="step">1 · Datos</li>
      <li>2 · Pago</li>
      <li>3 · Confirmación</li>
    </ol>
    <h1 class="section-title">Datos de recolección</h1>
    <p class="section-lead">Con esto agendamos la recogida de tu cuchillo o te enviamos la guía de paquetería.</p>
  </div>

  <div class="checkout__grid" data-checkout-empty hidden>
    <div class="panel empty-state">
      <p>Tu carrito está vacío. Cotiza tu servicio y agrégalo para continuar.</p>
      <a class="btn" href="<?= $v->e($v->url('/#cotizar')) ?>">Ir al cotizador</a>
    </div>
  </div>

  <form class="checkout__grid" method="post" action="<?= $v->e($v->url('/pagar/')) ?>" data-checkout-form novalidate>
    <input type="hidden" name="csrf" value="<?= $v->e($csrf) ?>">
    <input type="hidden" name="cart" value="<?= $v->e($old['cart'] ?? '') ?>" data-checkout-cart>

    <div>
      <?php if ($errors !== []): ?>
        <div class="alert alert--danger form-errors" role="alert" tabindex="-1" data-error-summary>
          <span class="alert__icon" aria-hidden="true">!</span>
          <div>
            <p><b>Revisa estos datos para continuar:</b></p>
            <ul>
              <?php foreach ($errors as $field => $message): ?>
                <li><?php if (in_array($field, ['name', 'email', 'phone', 'address', 'notes'], true)): ?><a href="#field-<?= $v->e($field) ?>"><?= $v->e($labels[$field] ?? $field) ?></a>: <?php endif; ?><?= $v->e($message) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <section class="panel" aria-labelledby="entrega-title">
        <h2 class="panel__title" id="entrega-title">Recepción y entrega</h2>
        <fieldset class="choice-group">
          <legend class="visually-hidden">Recepción y entrega</legend>
          <div class="choices choices--3 choices--stack-sm">
            <?php foreach ($catalogModel->deliveryOptions() as $option): ?>
              <?php if (!$option['available']) { continue; } ?>
              <label class="choice">
                <input type="radio" name="delivery" value="<?= $v->e($option['id']) ?>" required
                  data-price="<?= (int) $option['price'] ?>" data-needs-address="<?= $option['needs_address'] ? '1' : '0' ?>" data-insurable="<?= $option['insurable'] ? '1' : '0' ?>"
                  <?= $selectedDelivery === $option['id'] ? 'checked' : '' ?>>
                <span class="choice__card">
                  <span class="choice__title"><?= $v->e($option['name']) ?></span>
                  <span class="choice__detail"><?= $v->e($option['detail']) ?></span>
                  <span class="choice__price num"><?= $option['price'] === 0 ? 'Sin costo' : $v->money($option['price']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div data-insurance-step>
          <label class="option-row u-mt-4">
            <input type="checkbox" name="insurance" value="1" data-insurance data-price="<?= (int) $insurance['price'] ?>"<?= ($old['insurance'] ?? '') === '1' ? ' checked' : '' ?>>
            <span class="option-row__box" aria-hidden="true"></span>
            <span class="option-row__text"><b><?= $v->e($insurance['name']) ?></b><small><?= $v->e($insurance['detail']) ?></small></span>
            <span class="option-row__price num">+<?= $v->money($insurance['price']) ?></span>
          </label>
        </div>
      </section>

      <section class="panel" aria-labelledby="datos-title">
        <h2 class="panel__title" id="datos-title">Tus datos</h2>
        <div class="field">
          <label class="field__label" for="field-name">Nombre</label>
          <input class="field__input" id="field-name" name="name" type="text" autocomplete="name" required maxlength="120" value="<?= $v->e($old['name'] ?? '') ?>"<?= $invalid('name') ?>>
          <?= $fieldError('name') ?>
        </div>
        <div class="field">
          <label class="field__label" for="field-email">Correo electrónico</label>
          <input class="field__input" id="field-email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= $v->e($old['email'] ?? '') ?>"<?= $invalid('email') ?>>
          <p class="field__hint">Ahí te llega la confirmación del pago.</p>
          <?= $fieldError('email') ?>
        </div>
        <div class="field">
          <label class="field__label" for="field-phone">WhatsApp</label>
          <input class="field__input" id="field-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" required maxlength="30" placeholder="55 0000 0000" value="<?= $v->e($old['phone'] ?? '') ?>"<?= $invalid('phone') ?>>
          <p class="field__hint">Por aquí te contactamos para agendar la recolección.</p>
          <?= $fieldError('phone') ?>
        </div>
        <div class="field" data-address-field>
          <label class="field__label" for="field-address">Dirección completa</label>
          <textarea class="field__input" id="field-address" name="address" rows="3" autocomplete="street-address" maxlength="500" placeholder="Calle, número, colonia, CP y ciudad"<?= $invalid('address') ?>><?= $v->e($old['address'] ?? '') ?></textarea>
          <?= $fieldError('address') ?>
        </div>
        <div class="field">
          <label class="field__label" for="field-notes">Notas <span class="field__hint">(opcional)</span></label>
          <textarea class="field__input" id="field-notes" name="notes" rows="2" maxlength="500" placeholder="Marca o tipo de cuchillo, horario para recolección…"<?= $invalid('notes') ?>><?= $v->e($old['notes'] ?? '') ?></textarea>
          <?= $fieldError('notes') ?>
        </div>
      </section>

      <div class="panel">
        <button type="submit" class="btn btn--block" data-checkout-submit>Continuar al pago</button>
        <p class="secure-note">En el siguiente paso pagas con Mercado Pago. No guardamos datos de tarjeta.</p>
      </div>
    </div>

    <aside class="checkout__aside" aria-labelledby="pedido-title">
      <section class="panel">
        <h2 class="panel__title" id="pedido-title">Tu pedido</h2>
        <ul class="order-lines" data-checkout-lines></ul>
        <div class="summary">
          <div class="summary__row"><span>Servicios</span><span data-sum-services>—</span></div>
          <div class="summary__row"><span>Recepción y entrega</span><span data-sum-delivery>—</span></div>
          <div class="summary__row" data-sum-insurance-row hidden><span>Seguro de paquetería</span><span data-sum-insurance>—</span></div>
          <div class="summary__row summary__row--total"><span>Total a pagar</span><span data-sum-total>—</span></div>
        </div>
        <p class="cart-note u-mt-3">Precio de referencia: si al revisar el cuchillo el trabajo cambia, te lo confirmamos antes de tocarlo.</p>
        <p class="u-mt-3"><a class="link-button" href="<?= $v->e($v->url('/#cotizar')) ?>">Agregar otro servicio</a></p>
      </section>
    </aside>
  </form>
  <noscript><div class="checkout__grid"><p class="alert alert--warning">Para pagar en línea activa JavaScript, o escríbenos por WhatsApp al <?= $v->e($business['phone_display']) ?>.</p></div></noscript>
</div>
