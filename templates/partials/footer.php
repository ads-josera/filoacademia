<?php
/**
 * @var FiloAcademia\Support\View $v
 * @var array<string, mixed> $business
 */
?>
<footer class="site-footer">
  <div class="site-footer__grid">
    <div>
      <a href="<?= $v->e($v->url('/')) ?>" class="logo" aria-label="HERO Filo Academia, inicio">
        <span class="hanko" aria-hidden="true"><span>英</span><span>雄</span></span>
        <span class="logo__name" aria-hidden="true">HERO<span class="logo__sub">Filo Academia</span></span>
      </a>
      <p class="site-footer__about">Afilado y reparación de cuchillos de cocina, a mano con piedras de agua. CDMX y toda la República.</p>
    </div>
    <div>
      <h2 class="site-footer__title">Contacto</h2>
      <ul>
        <li><a href="https://wa.me/<?= $v->e($business['whatsapp']) ?>" rel="noopener">WhatsApp · <?= $v->e($business['phone_display']) ?></a></li>
        <li><a href="tel:<?= $v->e($business['phone_e164']) ?>">Teléfono · <?= $v->e($business['phone_display']) ?></a></li>
        <li><a href="mailto:<?= $v->e($business['email']) ?>"><?= $v->e($business['email']) ?></a></li>
      </ul>
    </div>
    <div>
      <h2 class="site-footer__title">Taller</h2>
      <ul>
        <?php foreach ([...$business['address_lines'], ...$business['hours']] as $line): ?>
          <li><?= $v->e($line) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h2 class="site-footer__title">Sitio</h2>
      <ul>
        <li><a href="<?= $v->e($v->url('/#cotizar')) ?>">Cotizador</a></li>
        <li><a href="<?= $v->e($v->url('/#precios')) ?>">Precios</a></li>
        <li><a href="<?= $v->e($v->url('/academia')) ?>">Academia</a></li>
        <li><a href="<?= $v->e($v->url('/#especialista')) ?>">El especialista</a></li>
      </ul>
    </div>
  </div>
  <div class="site-footer__bottom">
    <span>© <?= date('Y') ?> <?= $v->e($business['name']) ?>. Todos los derechos reservados.</span>
    <span lang="ja">刃を研ぐ</span>
  </div>
</footer>
