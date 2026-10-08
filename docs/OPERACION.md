# Operación y soporte

## Cambiar precios

Edita `config/catalog.php` (montos en pesos enteros). Lo toman a la vez la lista
de precios, el cotizador y el cobro. Reglas:

- **No renombres los `id`**: están guardados en los pedidos.
- Para retirar una opción de entrega: `'available' => false`.
- Para permitir seguro en otra entrega: `'insurable' => true`.

Sube el cambio con git y en el servidor ejecuta `bash ~/filoacademia/bin/deploy.sh` (Bloque 8 de [DESPLIEGUE.md](DESPLIEGUE.md)).

## Cambiar teléfono, dirección, horario o correo

`config/business.php`. Se refleja en el pie, la academia, la página de resultado
y los correos.

## Cambiar entre Checkout Pro y Bricks

`config/config.php` → `mercadopago.checkout_mode` = `'pro'` o `'bricks'`. Efecto
inmediato, sin tocar código. Los pedidos ya creados se cobran con el modo vigente
al abrir su página de pago.

## Revisar pedidos

Mercado Pago es la fuente oficial de pagos (panel → Actividad). Para ver la base
de datos del sitio desde la terminal:

```bash
cd ~/filoacademia
sqlite3 -header -column storage/database.sqlite \
  "SELECT folio, status, total, customer_name, customer_phone, delivery_id, paid_at
   FROM orders ORDER BY id DESC LIMIT 20;"
```

Un pedido concreto: `... "SELECT * FROM orders WHERE folio='HF-123456';"`

## Bitácoras

| Archivo | Qué contiene |
|---|---|
| `storage/logs/app-AAAA-MM.log` | Pedidos creados, cambios de estado, correos enviados, errores de Mercado Pago o SMTP (una línea JSON por evento) |
| `storage/logs/php-errors.log` | Errores de PHP |
| tabla `webhook_events` | Cada aviso de Mercado Pago: firma válida, resultado |

```bash
tail -n 50 ~/filoacademia/storage/logs/app-$(date +%Y-%m).log
grep '"ERROR"' ~/filoacademia/storage/logs/app-*.log | tail
```

## Problemas comunes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| «No pudimos conectar con Mercado Pago» en el paso 2 | `access_token` vacío o inválido | Revisa `config.php`; el detalle exacto está en `app-*.log` |
| Pagos aprobados pero el pedido sigue «pendiente» | Webhook sin configurar o clave secreta incorrecta, y cron de revisión sin configurar | `webhook_events` mostrará `firma inválida`; vuelve a copiar la clave (§6). Corre `/opt/cpanel/ea-php84/root/usr/bin/php bin/sync-pending.php` para ponerte al día y revisa el cron (§5) |
| No llegan correos | Datos SMTP o SPF/DKIM | Busca «No se pudo enviar el aviso» en el log; revisa *Email Deliverability* |
| Llega el correo del cliente pero no el del taller | `admin_recipients` vacío | Agrega al menos una dirección |
| «Recibimos muchos pedidos desde tu conexión» | Límite de 8 pedidos/10 min por IP | Espera 10 minutos; se ajusta en `CheckoutService::MAX_ORDERS_PER_IP`. Si el sitio se pone detrás de Cloudflare u otro proxy, todos comparten IP: hay que leer la IP real del proxy en `App::clientIp()` |
| Página en blanco o error 500 | Error de PHP, o el sitio cayó a PHP 8.1 (se perdió el bloque de cPanel del `.htaccess`) | `storage/logs/php-errors.log` y `~/logs/php.error.log`. Si es la versión: cPanel → MultiPHP Manager → PHP 8.4 y vuelve a correr `bin/deploy.sh` |
| El cliente pagó dos veces | Pagó con OXXO y luego con tarjeta | El pedido queda con el primer pago aprobado; reembolsa el otro desde Mercado Pago |

## Probar y revisar los correos

| Herramienta | Qué hace |
|---|---|
| **Mailpit** (DDEV) | Atrapa todos los correos locales: <https://filoacademia.ddev.site:8026>. Pestaña *HTML Check* = compatibilidad por cliente de correo; *Link Check* = enlaces rotos. Config local: `transport 'smtp'`, `host 'localhost'`, `port 1025`, `encryption 'none'`, `username ''` |
| `php bin/mail-preview.php HF-123456 [correo]` | Reenvía los correos de un pedido existente sin cambiar su estado |
| `php bin/mail-test-flow.php correo@destino` | Prueba de punta a punta por el camino real: crea un pedido de prueba y simula un pago aprobado (no corre en producción) |

En pruebas con un SMTP real, cambia antes `mail.admin_recipients` a tu propio
correo: si no, el aviso de prueba le llega al buzón del cliente.

Los correos se construyen en `src/Mail/OrderEmails.php` con las plantillas de
`templates/emails/`. El logo va **incrustado** (`templates/emails/assets/logo.png`,
`cid:logo`), así que se ve aunque el sitio no esté en línea. Compatibilidad
medida en Mailpit: 97–98 %; lo que falta son mejoras que fallan sin romper nada
(tipografías web y ajustes móviles en clientes que no los soportan).

## Cobrar una diferencia o reembolsar

El precio final se confirma al revisar el cuchillo:

- **Diferencia a favor del taller**: crea una *liga de pago* en el panel de
  Mercado Pago por la diferencia, con el folio en la descripción.
- **Diferencia a favor del cliente**: panel de Mercado Pago → la venta →
  *Devolver dinero* (parcial o total). El webhook actualiza el pedido a
  `refunded` cuando el reembolso es total.

## Próximos pasos recomendados

1. **Panel de administración** para ver y marcar pedidos (hoy: correo + Mercado Pago + consulta SQL).
2. **Content-Security-Policy** con la lista de dominios de Mercado Pago, probada en modo de prueba.
3. **Aviso de privacidad** y términos (requisito LFPDPPP al recabar datos personales).
4. Correo al cliente cuando genera una ficha OXXO (hoy solo se le muestra en pantalla).
