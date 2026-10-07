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
      <?= $v->render('partials/whatsapp-link', ['label' => 'Escribir por WhatsApp', 'class' => 'btn btn--ghost']) ?>
    </div>
  </section>
</div>
