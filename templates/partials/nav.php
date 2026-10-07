<?php
/**
 * Navegación principal y menú móvil.
 *
 * Lista paralela: si agregas una sección enlazable, súmala en $links (sirve
 * para escritorio, móvil y pie a la vez) — ver también partials/footer.php.
 *
 * @var FiloAcademia\Support\View $v
 * @var string $active
 */
$links = [
    ['href' => $v->url('/#cotizar'), 'label' => 'Cotizar', 'key' => ''],
    ['href' => $v->url('/#precios'), 'label' => 'Precios', 'key' => ''],
    ['href' => $v->url('/academia'), 'label' => 'Academia', 'key' => 'academia'],
    ['href' => $v->url('/#especialista'), 'label' => 'El especialista', 'key' => ''],
];
?>
<header class="site-nav">
  <nav class="site-nav__inner" aria-label="Principal">
    <a href="<?= $v->e($v->url('/')) ?>" class="logo" aria-label="HERO Filo Academia, inicio">
      <span class="hanko" aria-hidden="true"><span>英</span><span>雄</span></span>
      <span class="logo__name" aria-hidden="true">HERO<span class="logo__sub">Filo Academia</span></span>
    </a>
    <ul class="site-nav__links">
      <?php foreach ($links as $link): ?>
        <li><a href="<?= $v->e($link['href']) ?>"<?= $link['key'] !== '' && $link['key'] === $active ? ' aria-current="page"' : '' ?>><?= $v->e($link['label']) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="site-nav__actions">
      <button type="button" class="cart-button" data-cart-open aria-haspopup="dialog" aria-label="Abrir carrito">
        <svg class="cart-button__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 7h12l-1 13H7L6 7Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 7a3 3 0 0 1 6 0" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
        <span class="cart-button__label">Carrito</span>
        <span class="cart-button__count num" data-cart-count>0</span>
      </button>
      <button type="button" class="menu-toggle" data-menu-open aria-haspopup="dialog" aria-label="Abrir menú">
        <span></span><span></span><span></span>
      </button>
    </div>
  </nav>
</header>

<dialog class="mobile-menu" data-menu aria-label="Menú">
  <div class="mobile-menu__inner">
    <button type="button" class="icon-button mobile-menu__close" data-menu-close aria-label="Cerrar menú">✕</button>
    <ul>
      <?php foreach ($links as $link): ?>
        <li><a href="<?= $v->e($link['href']) ?>" data-menu-link><?= $v->e($link['label']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</dialog>
