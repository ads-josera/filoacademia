# Cambiar textos y precios

Guía para quien vaya a cambiar lo que dice el sitio: textos, datos de contacto,
cursos, precios o fotos. No hace falta saber programar, pero sí respetar dos
reglas:

1. **Cambia solo el texto entre comillas** (`'así'`) o entre etiquetas
   (`<p>así</p>`). Lo que lleva `<?=` … `?>` es código que pinta datos: no se toca.
2. **Siempre se prueba en local antes de publicar** (sección 5). Una comilla de
   más tira la página completa.

---

## 1. Mapa: qué quiero cambiar → dónde está

| Quiero cambiar… | Archivo | Qué buscar |
|---|---|---|
| **Precios** del afilado, reparaciones, envío o seguro | `config/catalog.php` | Ver sección 2 |
| Teléfono, WhatsApp, correo, dirección, horario, tiempo de respuesta | `config/business.php` | Se cambia una vez y se actualiza en todo el sitio y los correos |
| Título grande de portada y texto de bienvenida | `templates/pages/home.php` | `hero__title`, `hero__lead`, `hero__note` |
| Textos del cotizador (pasos 1 a 5, notas) | `templates/pages/home.php` | sección `id="cotizar"` |
| Lista de precios: títulos y letra pequeña | `templates/pages/home.php` | sección `id="precios"` (los **montos** NO están aquí, salen de `catalog.php`) |
| «Cómo funciona» (los tres pasos) | `templates/pages/home.php` | sección `id="como"` |
| «El especialista» (lo que recibimos / lo que hacemos) | `templates/pages/home.php` | sección `id="especialista"` |
| «El taller» (fotos del showroom) | `templates/pages/home.php` | sección `id="showroom"` |
| **Cursos de la Academia** (nombre, descripción, duración, cupo, mensaje de WhatsApp) | `templates/pages/academia.php` | la lista `$courses` al inicio del archivo: un bloque por curso |
| Texto de introducción de la Academia | `templates/pages/academia.php` | debajo de `$courses` |
| Menú superior | `templates/partials/nav.php` | `'label' => …` |
| Pie de página (descripción, enlaces) | `templates/partials/footer.php` | `site-footer__about` |
| Título de la pestaña y descripción para Google | `src/Http/PageController.php` | `'title'` y `'description'` de cada página |
| Formulario de datos y pantallas de pago | `templates/pages/pagar.php`, `pedido.php`, `resultado.php` | textos visibles |
| Correo que recibe el cliente al pagar | `templates/emails/customer-paid.php` (y su versión de solo texto `customer-paid-text.php`) | cambiar las dos |
| Correo que recibe el taller | `templates/emails/admin-paid.php` (y `admin-paid-text.php`) | cambiar las dos |
| Asunto de los correos | `src/Mail/OrderEmails.php` | `'Pago recibido · pedido %s'` y `'Nuevo pago · …'` (no borrar los `%s`: ahí va el folio) |
| Mensaje de WhatsApp con los datos del pedido | `templates/whatsapp/order-message.php` | |
| A qué correos llega el aviso de compra | `config/config.php` **del servidor** → `mail.admin_recipients` | Hoy: `pedidos@herofilo.mx`. Está fuera de git: se edita directo en el servidor |
| Página de error / no encontrada | `templates/pages/error.php` | |

### Fotos

Están en `public/assets/img/`. Cada foto va **en dos formatos con el mismo
nombre**: `.jpg` y `.webp` (por ejemplo `hero-cuchillos.jpg` y
`hero-cuchillos.webp`). Para cambiar una foto, sustituye los dos archivos
conservando el nombre y la misma proporción (ancho × alto), o la página se
descuadra. El texto alternativo (lo que lee un lector de pantalla) está en el
`'alt' => '…'` junto a cada foto en la plantilla.

### Logo

- En el sitio: `public/assets/img/favicon.svg` (icono de la pestaña); el logo
  de la cabecera es texto con estilo («HERO · Filo Academia») en
  `templates/partials/nav.php`.
- En los correos: `templates/emails/assets/logo.png`.

---

## 2. Cambiar precios

**Todos los precios viven en un solo archivo: `config/catalog.php`.** De ahí
salen a la vez la lista de precios, el cotizador, el carrito y **lo que se
cobra** en Mercado Pago. No hay que cambiarlos en ningún otro lugar.

Los montos son **pesos enteros, sin signo ni comas**: `'price' => 1250`, no
`'$1,250'`.

| Precio | Dónde en `catalog.php` |
|---|---|
| Afilado (servicio base, por cuchillo) | `'base'` → `'price'` |
| Remoción de 1, 2 y 3 mm | `'removal'` → cada renglón → `'price'` |
| Punta rota, mellas, óxido | `'extras'` → cada renglón → `'price'` |
| En el taller / CDMX / Fuera de CDMX / Internacional | `'delivery'` → cada bloque → `'price'` |
| Seguro de paquetería | `'insurance'` → `'price'` |
| Máximo de cuchillos por renglón del carrito | `'max_knives_per_line'` |

Ejemplo: subir el afilado de $250 a $280.

```php
'base' => [
    'id' => 'afilado',
    'name' => 'Afilado a mano con piedras de agua',
    'short' => 'Afilado',
    'price' => 280,          // ← solo este número
],
```

### Reglas para no romper nada

- **Nunca cambies un `'id'`** (`'afilado'`, `'remocion-2mm'`, `'cdmx'`…). Están
  guardados en los pedidos ya hechos. El nombre visible (`'name'`) sí se puede
  cambiar.
- **Para quitar una opción de entrega** no la borres: pon `'available' => false`
  (así está «Internacional»). Para volver a ofrecerla, `true`.
- **Para ofrecer seguro en otra entrega**: `'insurable' => true` en ese bloque.
- **Agregar una reparación o un extra nuevo** (otro renglón en `'removal'` o
  `'extras'`) sí funciona solo, pero pídelo a desarrollo para revisar que el
  texto quepa bien en el cotizador.
- Si renombras una remoción, conserva el principio «Remoción de …»: el
  cotizador lo acorta a «Remoción …» para que quepa.

### Lo que NO sale de `catalog.php` (cambiarlo a mano si cambia)

- **La cobertura del seguro, «hasta $5,000 MXN»**, aparece escrita en tres
  lugares: `config/catalog.php` (`'insurance'` → `'detail'`) y dos veces en
  `templates/pages/home.php` (renglón del seguro en la lista de precios y
  «Condiciones del seguro»).
- La letra pequeña que menciona paqueterías (Estafeta, DHL, Paquetexpress) en
  `templates/pages/home.php`.

### Qué pasa con los pedidos y carritos existentes

- **Pedidos ya pagados**: conservan el precio con el que se pagaron.
- **Carritos que alguien ya tenía armado** en su navegador: pueden mostrar el
  precio anterior en el carrito, pero **el sitio siempre cobra el precio nuevo**.
  El total correcto aparece en la pantalla de pago y en Mercado Pago, antes de
  pagar. Por eso conviene cambiar precios en horas de poco movimiento.
- Diferencias con un cliente en particular se resuelven desde el panel de
  Mercado Pago (ver «Cobrar una diferencia o reembolsar» en
  [OPERACION.md](OPERACION.md)).

---

## 3. Lo que NO se edita aquí

- **Llaves de Mercado Pago, contraseñas de correo y modo de prueba/producción**:
  solo en `config/config.php` del servidor, nunca en git. Ver
  [DESPLIEGUE.md](DESPLIEGUE.md) §3.
- **Formas de pago que se ofrecen** (hoy: tarjeta y cuenta de Mercado Pago; sin
  efectivo ni SPEI): `mercadopago.excluded_payment_types` en `config/config.php`
  del servidor. Ver [FLUJO-DE-PAGO.md](FLUJO-DE-PAGO.md).
- Colores y tipografías: `public/assets/css/app.css` (variables al inicio del
  archivo). Es trabajo de diseño, no de contenido.

---

## 4. Acentos y caracteres especiales

Los archivos están en UTF-8: escribe acentos, «comillas» y la ñ con normalidad.
Dentro de un texto entre comillas simples (`'…'`) no puede ir otra comilla
simple sin más: usa el apóstrofo tipográfico ’ o escríbela como `\'`
(por ejemplo `'Chef’s knife'` o `'Chef\'s knife'`).

---

## 5. Publicar el cambio

### En local (siempre primero)

```bash
cd nueva-version
ddev start                                    # https://filoacademia.ddev.site
# … edita los archivos …
ddev exec vendor/bin/phpunit                  # debe decir OK
```

Abre en el navegador la página que cambiaste, en ancho de computadora y de
celular. Si cambiaste precios, revisa también: la lista de precios, un cálculo
en el cotizador, el carrito y la pantalla «Datos de recolección».

Si cambiaste un correo: `ddev exec php bin/mail-preview.php HF-XXXXXX` con un
pedido local y míralo en Mailpit (<https://filoacademia.ddev.site:8026>).

### Subir a GitHub

```bash
git add -A
git commit -m 'Precios: afilado a $280'       # describe qué cambió (comillas simples: con dobles, $2 se pierde)
git push
```

Autor de los commits: José Raúl Perea <ads@josera.com.mx>.

### En el servidor

```bash
cd ~/filoacademia && mkdir -p storage/backups && cp storage/database.sqlite storage/backups/db-$(date +%Y%m%d-%H%M).sqlite
bash bin/deploy.sh
```

Al final debe mostrar `/`, `/academia` y `/pagar/` con 200. Los navegadores
toman los estilos y scripts nuevos solos (el sitio les pone versión).

### Si algo sale mal

Vuelve a la versión anterior con el Bloque 10 de [DESPLIEGUE.md](DESPLIEGUE.md).
