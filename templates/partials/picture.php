<?php
/**
 * Imagen con WebP y JPEG de respaldo.
 *
 * @var FiloAcademia\Support\View $v
 * @var string $name   Nombre base en assets/img/ (sin extensión).
 * @var string $alt
 * @var int $width
 * @var int $height
 * @var bool $eager    true solo para la imagen principal visible al cargar.
 */
$eager ??= false;
?>
<picture>
  <source srcset="<?= $v->e($v->asset('img/' . $name . '.webp')) ?>" type="image/webp">
  <img src="<?= $v->e($v->asset('img/' . $name . '.jpg')) ?>" alt="<?= $v->e($alt) ?>" width="<?= (int) $width ?>" height="<?= (int) $height ?>"<?= $eager ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
</picture>
