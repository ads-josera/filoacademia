# Arquitectura

## Qué es

Un sitio PHP sin framework, pequeño a propósito: cuatro páginas públicas, un
flujo de pago de tres pasos, un webhook y los correos. No usa framework porque
el alcance no lo justifica y en cPanel cada dependencia es algo más que
mantener; a cambio, el código sigue las mismas reglas que tendría con uno
(PSR-4, PSR-12, inyección de dependencias, capas separadas).

## Qué se corrigió del HTML original

| Problema en el original | Solución |
|---|---|
| `index.html` traía **dos copias del sitio** pegadas (una vieja con teléfono y dirección de prueba) | Una sola versión, con los datos reales (Eje 1 Norte #56 L17, 55 4258 8200) |
| El «pago» era simulado: mostraba «Pago recibido» y un folio aleatorio sin cobrar nada | Cobro real con Mercado Pago, folio guardado en base de datos |
| El precio lo calculaba solo el navegador | El servidor recalcula todo con el catálogo; el navegador no puede alterar el monto |
| La entrega se cobraba **por cada línea del carrito** (dos servicios = dos recolecciones) | La entrega y el seguro se eligen una vez por pedido |
| El seguro de paquetería se podía contratar recogiendo en el taller | Solo se ofrece con paquetería (fuera de CDMX) — configurable en `config/catalog.php` |
| No pedía correo electrónico | Correo obligatorio: ahí llega la confirmación |
| Gris de texto con contraste 2.7:1 (ilegible para muchos) | Tokens con contraste medido ≥ 4.5:1 |
| Imágenes de 3.5 MB en total; un archivo `.jpg.jpg` | WebP optimizado (~510 KB las cuatro) con JPEG de respaldo (~1 MB) para navegadores viejos |
| Botones `<div>` y `onclick` en línea, sin teclado ni lector de pantalla | Radios/checkbox reales, `<dialog>` nativo, foco visible |
| La Academia era una «vista» oculta con JavaScript | Página propia: `/academia` |
| `alert()` para errores | Errores junto al campo, con resumen y sin perder lo escrito |

## Estructura

```
nueva-version/
├── bootstrap.php            Arranque común: autoload, config, errores, zona horaria
├── composer.json            Dependencias (solo PHPMailer en producción)
├── config/
│   ├── config.example.php   Plantilla de configuración (en git)
│   ├── config.php           Configuración real con llaves (FUERA de git)
│   ├── catalog.php          Precios: única fuente de verdad
│   └── business.php         Datos públicos del negocio (teléfono, dirección…)
├── public/                  ← lo único accesible desde la web (public_html)
│   ├── index.php            Inicio
│   ├── academia.php         Academia
│   ├── 404.php
│   ├── pagar/index.php      Paso 1: datos (crea el pedido)
│   ├── pagar/pedido.php     Paso 2: cobro (Checkout Pro o Bricks)
│   ├── pagar/resultado.php  Paso 3: resultado (aquí regresa Mercado Pago)
│   ├── api/pago.php         Cobro con Bricks (JSON)
│   ├── webhooks/mercadopago.php  Notificaciones de Mercado Pago
│   ├── _boot.php            Localiza la aplicación (no accesible por web)
│   ├── .htaccess            HTTPS, URLs limpias, cabeceras, caché
│   └── assets/              css/app.css · js/*.js · img/
├── src/                     Código PHP (namespace FiloAcademia\)
│   ├── App.php              Contenedor: construye y conecta los servicios
│   ├── Catalog/             Catálogo y cálculo de precios
│   ├── Order/               Pedido, estados, repositorio, datos del cliente
│   ├── Payment/             Contrato de pasarela, servicios de cobro y sincronización
│   │   ├── MercadoPago/     Implementación de Mercado Pago y firma del webhook
│   │   └── Http/            Transporte HTTP (cURL)
│   ├── Mail/                Envío (SMTP o archivo) y avisos de pago
│   ├── Http/                Controladores y respuestas
│   ├── Persistence/         Conexión y esquema de base de datos
│   └── Support/             Configuración, URLs, bitácora, sesión, vistas
├── templates/               Plantillas PHP (layout, páginas, parciales, correos)
├── storage/                 Base SQLite, bitácoras y correos de prueba (FUERA de git)
├── bin/                     migrate.php · dev-router.php
├── tests/                   Unit/ (PHPUnit) · browser/qa.mjs (recorrido en navegador)
└── docs/
```

## Capas y responsabilidades

```
 public/*.php  ──►  Http\*Controller  ──►  Payment\CheckoutService ─┐
 (entrada, 3 líneas)  (lee petición,        Payment\PaymentSyncService ├─► Order\OrderRepository ─► PDO
                      responde)                     │                 │
                                                    ├─► Payment\PaymentGateway (interfaz)
                                                    │       └─ MercadoPago\MercadoPagoGateway ─► API REST
                                                    └─► Mail\OrderNotifier ─► Mail\Mailer (SMTP | archivo)
```

- **Controladores** (`src/Http`): solo traducen HTTP ↔ servicios. Sin reglas de negocio.
- **`CheckoutService`**: del carrito al cobro. Crea el pedido (con límite por IP),
  prepara la preferencia de Mercado Pago (una sola vez) y cobra con Bricks.
- **`PaymentSyncService`**: el **único** lugar donde un pedido cambia de estado por
  un pago. Lo llaman el webhook, la página de resultado y Bricks; es seguro
  llamarlo varias veces con el mismo pago.
- **`PaymentGateway`**: interfaz. Si mañana se agrega otro proveedor, se escribe
  otra implementación y no se toca el resto.
- **`OrderNotifier`**: correos de pago aprobado, con garantía de «una sola vez».

## Decisiones técnicas

| Decisión | Por qué |
|---|---|
| API REST de Mercado Pago directa (sin SDK) | Solo se usan 3 endpoints estables; una dependencia menos que actualizar |
| SQLite por defecto | Sin servidor que administrar; un archivo fácil de respaldar. Cambiar a MySQL es solo cambiar el DSN |
| Montos en pesos enteros | Todos los precios son enteros; evita errores de redondeo con decimales |
| Folio corto + token largo | El folio (HF-123456) se dicta por teléfono; el token (64 hex) protege los datos personales en la URL |
| Carrito en el navegador | No requiere cuentas ni sesiones; el servidor valida todo al enviarlo |
| `<dialog>` nativo para carrito y menú | El navegador resuelve foco, Esc y fondo inerte; menos JavaScript propio |
| Sin CSP estricta todavía | El SDK de Mercado Pago carga de varios dominios; una CSP mal hecha rompe el cobro. Ver «Próximos pasos» |

## Diseño

Se conservó el sistema visual del cliente (papel *washi*, tinta *sumi*, sello
rojo *aka*, tipografías Shippori Mincho y Zen Kaku Gothic New) y se formalizó
en tokens en `public/assets/css/app.css` (sección 1). **Para cambiar colores,
tamaños o espacios se editan los tokens, no los componentes.**

Contrastes medidos (WCAG, texto normal requiere 4.5:1):

| Token | Sobre fondo claro | Sobre fondo oscuro |
|---|---|---|
| `--color-text` #1a1918 | 15.7:1 | — |
| `--color-text-muted` #54514b | 7.1:1 | — |
| `--color-text-subtle` #68635a | 5.3:1 | — |
| `--color-accent` #b23a2a | 5.3:1 | 2.9:1 (no se usa sobre oscuro) |
| `--color-inverse-muted` #98938a | 2.7:1 (no se usa sobre claro) | 5.8:1 |
| `--color-inverse-accent` #e07a68 | — | 6.0:1 |

El sitio es solo en modo claro por decisión de marca.

## Componentes compartidos (no se duplican)

- **Enlaces a WhatsApp**: siempre con `templates/partials/whatsapp-link.php`
  (pestaña nueva, texto para lector de pantalla). Nunca escribir `wa.me` a mano.
- **Mensaje de WhatsApp de un pedido**: `templates/whatsapp/order-message.php`
  vía `$v->whatsappOrderMessage($order, $primeraLinea)`; lleva folio, servicios,
  entrega, seguro, total y nombre para que el taller atienda sin preguntar.
- **Resumen de pedido** en pantallas: `templates/partials/order-summary.php`; en
  correos: `templates/emails/order-table.php`.

## Listas paralelas (se olvidan, y no se ve mirando)

- **Enlaces de navegación**: `templates/partials/nav.php` (escritorio y móvil) y
  `templates/partials/footer.php`.
- **Una página nueva** necesita su archivo en `public/` y, si lleva URL limpia,
  funciona sola con `.htaccess` y `bin/dev-router.php`.
- **Una regla de precio nueva** se programa en `src/Catalog/QuoteCalculator.php`
  **y** en `lineFromSelection()` de `public/assets/js/cart-store.js` (esta
  última solo para mostrar; el cobro siempre usa la de PHP).
