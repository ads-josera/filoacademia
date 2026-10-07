<?php
/**
 * Página de error genérica (pedido no encontrado, enlace inválido, fallo).
 *
 * @var FiloAcademia\Support\View $v
 * @var array<string, mixed> $business
 * @var string $heading
 * @var string $message
 */
?>
<div class="checkout">
  <section class="status-hero" aria-labelledby="error-title">
    <div class="status-hero__icon status-hero__icon--danger" aria-hidden="true">!</div>
    <h1 class="status-hero__title" id="error-title"><?= $v->e($heading) ?></h1>
    <p><?= $v->e($message) ?></p>
    <div class="status-hero__actions">
      <a class="btn" href="<?= $v->e($v->url('/')) ?>">Volver al inicio</a>
      <a class="btn btn--ghost" href="https://wa.me/<?= $v->e($business['whatsapp']) ?>" rel="noopener">Escribir por WhatsApp</a>
    </div>
  </section>
</div>
