<?php
/**
 * Esqueleto de todas las páginas.
 *
 * @var FiloAcademia\Support\View $v
 * @var array<string, mixed> $business
 * @var string $content      HTML ya renderizado de la página.
 * @var string $title
 * @var string $description
 * @var string $active       home | academia | pagar
 * @var list<string> $scripts  Módulos JS de assets/js/ que necesita la página.
 * @var array<string, mixed>|null $catalog  Catálogo público (si la página cotiza).
 * @var bool $noindex
 */
$scripts ??= [];
$catalog ??= null;
$noindex ??= false;
?><!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $v->e($title) ?></title>
<meta name="description" content="<?= $v->e($description) ?>">
<?php if ($noindex): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="theme-color" content="#f5f2ec">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= $v->e($title) ?>">
<meta property="og:description" content="<?= $v->e($description) ?>">
<meta property="og:image" content="<?= $v->e($v->urls()->absolute('/assets/img/hero-cuchillos.jpg')) ?>">
<link rel="icon" href="<?= $v->e($v->asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Shippori+Mincho:wght@500;600&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap">
<link rel="stylesheet" href="<?= $v->e($v->asset('css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>

<?= $v->render('partials/nav', ['active' => $active]) ?>

<main id="contenido">
<?= $content ?>
</main>

<?= $v->render('partials/footer') ?>
<?= $v->render('partials/cart-drawer') ?>

<?php if ($catalog !== null): ?>
<script type="application/json" id="catalog-data"><?= $v->json($catalog) ?></script>
<?php endif; ?>
<script type="module" src="<?= $v->e($v->asset('js/site.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script type="module" src="<?= $v->e($v->asset('js/' . $script . '.js')) ?>"></script>
<?php endforeach; ?>
</body>
</html>
