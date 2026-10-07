<?php
/**
 * Marco de los correos.
 *
 * Reglas del HTML de correo (no son las de la web):
 * - Tablas para la estructura y estilos en línea: es lo que respetan Gmail,
 *   Outlook y Apple Mail por igual.
 * - Ancho fijo de 560 px con una tabla condicional para Outlook de escritorio,
 *   que ignora max-width.
 * - El logo viaja incrustado (cid:logo, ver Mail\OrderEmails), no como imagen remota.
 * - Tipografías de marca donde se cargan (Apple Mail, iOS); el resto usa la
 *   alternativa más parecida (Georgia para títulos, Helvetica/Arial para texto).
 * Colores tomados de los tokens del sitio (assets/css/app.css).
 *
 * @var FiloAcademia\Support\View $v
 * @var array<string, mixed> $business
 * @var string $preheader  Texto que se ve junto al asunto en la bandeja.
 * @var string $body       HTML ya renderizado del contenido.
 */
?><!DOCTYPE html>
<html lang="es-MX" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<meta name="x-apple-disable-message-reformatting">
<title><?= $v->e($business['name']) ?></title>
<!--[if mso]><style>body,table,td,p,a,h1,h2{font-family:Arial,sans-serif !important;}</style><![endif]-->
<!--[if !mso]><!-->
<link href="https://fonts.googleapis.com/css2?family=Shippori+Mincho:wght@600&family=Zen+Kaku+Gothic+New:wght@400;700&display=swap" rel="stylesheet">
<!--<![endif]-->
<style>
  :root { color-scheme: light; supported-color-schemes: light; }
  @media (max-width: 600px) {
    .card-pad { padding: 24px 20px !important; }
    .head-pad { padding: 20px !important; }
    .h1 { font-size: 22px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f5f2ec;color:#1a1918;-webkit-text-size-adjust:100%;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;overflow:hidden;mso-hide:all;"><?= $v->e($preheader) ?>&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f2ec" style="background-color:#f5f2ec;">
  <tr>
    <td align="center" style="padding:32px 12px;">
      <!--[if mso]><table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fffdf8" style="width:100%;max-width:560px;background-color:#fffdf8;border:1px solid #ddd6c9;">
        <tr>
          <td class="head-pad" style="padding:24px 32px;border-bottom:1px solid #ddd6c9;">
            <img src="cid:logo" width="163" height="37" alt="HERO · Filo Academia" style="display:block;border:0;outline:none;text-decoration:none;height:37px;width:163px;font-family:Georgia,serif;font-size:18px;color:#1a1918;">
          </td>
        </tr>
        <tr>
          <td class="card-pad" style="padding:32px;font-family:'Zen Kaku Gothic New','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1a1918;">
            <?= $body ?>
          </td>
        </tr>
        <tr>
          <td bgcolor="#1a1918" style="padding:24px 32px;background-color:#1a1918;font-family:'Zen Kaku Gothic New','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:13px;line-height:1.7;color:#c9c4bb;">
            <strong style="color:#f5f2ec;"><?= $v->e($business['name']) ?></strong><br>
            <?= $v->e(implode(', ', $business['address_lines'])) ?><br>
            <?= $v->e(implode(' · ', $business['hours'])) ?><br>
            <a href="<?= $v->e($v->whatsappUrl()) ?>" style="color:#f5f2ec;text-decoration:underline;">WhatsApp <?= $v->e($business['phone_display']) ?></a>
            &nbsp;·&nbsp;
            <a href="mailto:<?= $v->e($business['email']) ?>" style="color:#f5f2ec;text-decoration:underline;"><?= $v->e($business['email']) ?></a>
          </td>
        </tr>
      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body>
</html>
