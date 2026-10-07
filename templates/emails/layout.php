<?php
/**
 * Marco de los correos. Estilos en línea y tablas: es lo único que respetan
 * todos los clientes de correo (Gmail, Outlook, Apple Mail).
 * Colores tomados de los tokens del sitio (assets/css/app.css).
 *
 * @var FiloAcademia\Support\View $v
 * @var array<string, mixed> $business
 * @var string $preheader
 * @var string $body
 */
?><!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= $v->e($business['name']) ?></title>
</head>
<body style="margin:0;padding:0;background:#f5f2ec;color:#1a1918;font-family:'Helvetica Neue',Arial,sans-serif;">
<span style="display:none;max-height:0;overflow:hidden;opacity:0;"><?= $v->e($preheader) ?></span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f2ec;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fffdf8;border:1px solid #ddd6c9;border-radius:4px;">
        <tr>
          <td style="padding:24px 32px;border-bottom:1px solid #ddd6c9;">
            <span style="display:inline-block;background:#b23a2a;color:#ffffff;font-family:Georgia,serif;font-size:11px;line-height:1.1;padding:4px 6px;border-radius:4px;vertical-align:middle;">英<br>雄</span>
            <span style="font-family:Georgia,serif;font-size:18px;font-weight:600;letter-spacing:4px;vertical-align:middle;margin-left:8px;">HERO</span>
            <span style="font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#54514b;vertical-align:middle;margin-left:4px;">Filo Academia</span>
          </td>
        </tr>
        <tr>
          <td style="padding:32px;font-size:15px;line-height:1.6;">
            <?= $body ?>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 32px;background:#1a1918;color:#c9c4bb;font-size:13px;line-height:1.6;border-radius:0 0 4px 4px;">
            <strong style="color:#f5f2ec;"><?= $v->e($business['name']) ?></strong><br>
            <?= $v->e(implode(', ', $business['address_lines'])) ?><br>
            WhatsApp <?= $v->e($business['phone_display']) ?> · <a href="mailto:<?= $v->e($business['email']) ?>" style="color:#f5f2ec;"><?= $v->e($business['email']) ?></a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
