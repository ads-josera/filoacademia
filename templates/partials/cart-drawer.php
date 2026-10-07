<?php
/**
 * Carrito lateral. El contenido lo pinta assets/js/cart.js desde el
 * almacenamiento del navegador; aquí solo está la estructura.
 *
 * @var FiloAcademia\Support\View $v
 */
?>
<dialog class="drawer" data-cart aria-labelledby="cart-title">
  <div class="drawer__head">
    <h2 class="drawer__title" id="cart-title">Tu carrito</h2>
    <button type="button" class="icon-button" data-cart-close aria-label="Cerrar carrito">✕</button>
  </div>
  <div class="drawer__body" data-cart-lines aria-live="polite">
    <div class="empty-state">
      <p>Tu carrito está vacío.</p>
    </div>
  </div>
  <div class="drawer__foot" data-cart-foot hidden>
    <div class="summary">
      <div class="summary__row summary__row--total"><span>Servicios</span><span data-cart-total>$0</span></div>
    </div>
    <p class="cart-note">La recepción y entrega se elige en el siguiente paso.</p>
    <a class="btn btn--block" href="<?= $v->e($v->url('/pagar/')) ?>">Continuar al pago</a>
  </div>
</dialog>
