# Operación y soporte

## Cambiar precios

Edita `config/catalog.php` (montos en pesos enteros). Lo toman a la vez la lista
de precios, el cotizador y el cobro. Reglas:

- **No renombres los `id`**: están guardados en los pedidos.
- Para retirar una opción de entrega: `'available' => false`.
- Para permitir seguro en otra entrega: `'insurable' => true`.

Sube el cambio con git y aplica el Bloque 7 de [DESPLIEGUE.md](DESPLIEGUE.md).

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
| Pagos aprobados pero el pedido sigue «pendiente» | Webhook sin configurar o clave secreta incorrecta, y cron de revisión sin configurar | `webhook_events` mostrará `firma inválida`; vuelve a copiar la clave (§5). Corre `php bin/sync-pending.php` para ponerte al día y revisa el cron (§6b) |
| No llegan correos | Datos SMTP o SPF/DKIM | Busca «No se pudo enviar el aviso» en el log; revisa *Email Deliverability* |
| Llega el correo del cliente pero no el del taller | `admin_recipients` vacío | Agrega al menos una dirección |
| «Recibimos muchos pedidos desde tu conexión» | Límite de 8 pedidos/10 min por IP | Espera 10 minutos; se ajusta en `CheckoutService::MAX_ORDERS_PER_IP`. Si el sitio se pone detrás de Cloudflare u otro proxy, todos comparten IP: hay que leer la IP real del proxy en `App::clientIp()` |
| Página en blanco | Error de PHP | `storage/logs/php-errors.log` |
| El cliente pagó dos veces | Pagó con OXXO y luego con tarjeta | El pedido queda con el primer pago aprobado; reembolsa el otro desde Mercado Pago |

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
