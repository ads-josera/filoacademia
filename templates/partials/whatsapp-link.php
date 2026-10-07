<?php
/**
 * Enlace a WhatsApp del taller: el ÚNICO lugar donde se arma uno.
 *
 * Abre en pestaña nueva para que el cliente no pierda la página donde estaba
 * (sobre todo en el flujo de pago, donde debe poder reintentar).
 *
 * @var FiloAcademia\Support\View $v
 * @var string $label        Texto visible.
 * @var string|null $text    Mensaje precargado (texto plano).
 * @var string|null $class   Clases CSS del enlace.
 */
$text ??= null;
$class ??= null;
?><a<?= $class !== null ? ' class="' . $v->e($class) . '"' : '' ?> href="<?= $v->e($v->whatsappUrl($text)) ?>" target="_blank" rel="noopener"><?= $v->e($label) ?><span class="visually-hidden"> (se abre en una pestaña nueva)</span></a>
