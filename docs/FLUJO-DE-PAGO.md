# Flujo de pago

## Resumen

```
 Cotizador ──► Carrito ──► /pagar (datos) ──► /pagar/pedido (cobro) ──► Mercado Pago ──► /pagar/resultado
 (navegador)   (navegador)   crea el pedido       pro: botón              │                 consulta el pago
                             en BD (pending)      bricks: formulario      │                 y muestra el estado
                                                                          ▼
                                                           /webhooks/mercadopago.php
                                                           (confirma aunque el cliente
                                                            cierre la ventana)
                                                                          │
                                              pago aprobado ──► correo al cliente + correo al taller (una vez)
```

## Paso a paso

1. **Cotizador** (`/`): el cliente elige reparación, cantidad, entrega y seguro.
   «Agregar al carrito» guarda **identificadores** en `localStorage`
   (`filo.cart.v1`), nunca precios con valor de cobro.
2. **Datos** (`/pagar/`): elige entrega (una por pedido), seguro (solo con
   paquetería) y escribe nombre, correo, WhatsApp y dirección (no se pide si
   recoge en el taller). Al enviar, el servidor:
   - verifica el token CSRF,
   - recalcula el total con `config/catalog.php`,
   - valida los datos,
   - aplica el límite de 8 pedidos por IP cada 10 minutos,
   - guarda el pedido con estado `pending`, folio `HF-######` y un token de acceso.
3. **Cobro** (`/pagar/pedido?folio=…&t=…`): crea (una sola vez) la preferencia
   de Mercado Pago con los conceptos del pedido.
   - **Modo `pro`**: botón «Pagar $X MXN» que lleva a la ventana segura de
     Mercado Pago (tarjeta, OXXO, saldo MP).
   - **Modo `bricks`**: el formulario de Mercado Pago aparece en la página. Al
     enviarlo, `assets/js/bricks.js` llama a `/api/pago`, que cobra con el
     **monto del pedido guardado** (el del navegador se descarta) y una llave de
     idempotencia `folio-intento` (un doble clic no cobra dos veces). Máximo 6
     intentos por pedido.
4. **Resultado** (`/pagar/resultado?folio=…&t=…`): Mercado Pago regresa aquí
   con `payment_id`. El servidor **consulta ese pago en la API** (no cree el
   parámetro) y solo lo acepta si es de este pedido. Pantallas: pagado,
   esperando confirmación (se refresca sola), ficha OXXO pendiente, en revisión,
   rechazado (con «Intentar de nuevo»), reembolsado.
5. **Webhook** (`/webhooks/mercadopago.php`): Mercado Pago avisa de cada cambio.
   Se valida la firma `x-signature` con la clave secreta y se consulta el pago en
   la API. Así un pago en OXXO que se acredita días después también se confirma
   y dispara los correos.

## Estados del pedido

| Estado | Significado | Origen en Mercado Pago |
|---|---|---|
| `pending` | Sin pagar, o ficha OXXO generada sin pagar | `pending` |
| `in_process` | En revisión antifraude | `in_process`, `in_mediation`, `authorized` |
| `approved` | **Pagado** → se envían los correos | `approved` |
| `rejected` | Rechazado, se puede reintentar | `rejected` |
| `cancelled` | Cancelado o ficha vencida, se puede reintentar | `cancelled`, `expired` |
| `refunded` | Reembolsado o contracargo | `refunded`, `charged_back` |

**Regla de no-regresión**: un pedido `approved` solo cambia por noticias del
**mismo** pago (p. ej. un reembolso). Un intento anterior rechazado que llega
tarde no lo deja como «rechazado».

## Correos

Al quedar `approved` se envían, **una sola vez cada uno** aunque el pago llegue
por varias vías:

| Para | Asunto | Contenido | Responder a |
|---|---|---|---|
| Cliente | Pago recibido · pedido HF-… | Conceptos, total, qué sigue (taller o recolección) | `hola@herofilo.mx` |
| Taller (`mail.admin_recipients`) | Nuevo pago · HF-… · $… · Nombre | Datos de contacto, dirección, notas, conceptos, ID de pago MP | el cliente |

Si el SMTP falla, la marca de envío se libera y el siguiente aviso de Mercado
Pago (que reintenta el webhook) vuelve a intentarlo. El fallo queda en
`storage/logs/app-AAAA-MM.log`.

## Seguridad

| Riesgo | Defensa | Dónde |
|---|---|---|
| Cambiar el precio desde el navegador | El servidor recalcula; el monto de cobro sale del pedido guardado | `QuoteCalculator`, `CheckoutService`, `MercadoPagoGateway` |
| Webhook falso | Firma HMAC-SHA256 con clave secreta; además se consulta la API | `WebhookSignature`, `WebhookController` |
| `payment_id` ajeno en la URL de retorno | El pago debe tener `external_reference` = folio del pedido | `PaymentSyncService::apply()` |
| Pago por monto o moneda distintos | No se marca como pagado y se registra como error | `PaymentSyncService::amountMatches()` |
| Ver datos de otro pedido adivinando el folio | Se exige también el token de 64 caracteres | `Order::isAccessibleWith()` |
| Doble cobro por doble clic | Botón desactivado + llave de idempotencia | `checkout.js`, `CheckoutService` |
| Envío de formularios desde otro sitio | Token CSRF en sesión | `Session`, `CheckoutController` |
| Abuso (pedidos masivos) | 8 pedidos / 10 min por IP; 6 intentos de cobro por pedido | `CheckoutService` |
| Llaves expuestas | Solo en `config/config.php`, fuera de git y de `public_html` | `.gitignore`, estructura |
| Datos de tarjeta | Nunca pasan por nuestro servidor: los tokeniza Mercado Pago | Checkout Pro / Bricks |

## Política de precio (decisión del cliente)

Se cobra el **total estimado completo**. Si al revisar el cuchillo el trabajo
cambia, la diferencia se cobra aparte (liga de pago de Mercado Pago) o se
reembolsa desde el panel de Mercado Pago. El sitio lo advierte junto al total y
en el correo.
