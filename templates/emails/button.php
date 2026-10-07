<?php
/**
 * Botón de correo «a prueba de Outlook»: el color va en la celda de la tabla
 * (Outlook ignora el fondo y el relleno de los enlaces).
 *
 * @var FiloAcademia\Support\View $v
 * @var string $href
 * @var string $label
 */
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px;">
  <tr>
    <td bgcolor="#1a1918" style="background-color:#1a1918;border:1px solid #1a1918;border-radius:2px;">
      <a href="<?= $v->e($href) ?>" target="_blank" style="display:inline-block;padding:14px 28px;font-family:'Zen Kaku Gothic New','Helvetica Neue',Helvetica,Arial,sans-serif;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#f5f2ec;text-decoration:none;"><?= $v->e($label) ?></a>
    </td>
  </tr>
</table>
