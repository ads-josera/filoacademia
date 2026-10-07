# Manual de despliegue (cPanel · PHP 8.4)

Cada bloque se copia y pega completo en **cPanel → Terminal** (o por SSH).
Antes de empezar, reemplaza en TODOS los bloques:

| Marcador | Ejemplo |
|---|---|
| `TU_DOMINIO` | `herofilo.mx` |
| `TU_USUARIO` | el usuario de cPanel (se ve con `whoami`) |

La aplicación vive en `~/filoacademia` y **solo** su carpeta `public/` se
publica en la web. Configuración, base de datos y bitácoras quedan fuera de
`public_html`.

---

## 0. Preparación en el panel (una sola vez, sin terminal)

1. **MultiPHP Manager** → selecciona el dominio → **PHP 8.4** (`ea-php84`).
2. **Select PHP Version / MultiPHP INI** → confirma extensiones: `pdo_sqlite`,
   `curl`, `mbstring`, `openssl`.
3. **SSL/TLS Status** → el dominio con certificado válido (AutoSSL). Mercado Pago
   exige HTTPS.
4. **Email Accounts** → crea `pagos@TU_DOMINIO` con contraseña fuerte (desde ahí
   salen los correos).
5. **Email Deliverability** → que SPF y DKIM estén en verde (si no, los correos
   caen en spam).

## 1. Llave de despliegue para GitHub

```bash
# — Bloque 1: crear llave SSH del servidor (solo lectura del repositorio) —
mkdir -p ~/.ssh && chmod 700 ~/.ssh
ssh-keygen -t ed25519 -C "deploy@TU_DOMINIO" -f ~/.ssh/filoacademia_deploy -N ""
cat >> ~/.ssh/config <<'EOF'
Host github-filoacademia
    HostName github.com
    User git
    IdentityFile ~/.ssh/filoacademia_deploy
    IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config
echo "=== Copia esta llave pública en GitHub ==="
cat ~/.ssh/filoacademia_deploy.pub
```

En GitHub: **ads-josera/filoacademia → Settings → Deploy keys → Add deploy key**,
pega la llave, **sin** marcar «Allow write access».

## 2. Instalación

```bash
# — Bloque 2: clonar, instalar dependencias y preparar base de datos —
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
cd ~ && git clone git@github-filoacademia:ads-josera/filoacademia.git filoacademia
cd ~/filoacademia
if command -v composer >/dev/null; then COMPOSER="composer"; else
  $PHP -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  $PHP composer-setup.php --quiet && rm composer-setup.php
  COMPOSER="$PHP composer.phar"
fi
$COMPOSER install --no-dev --optimize-autoloader --no-interaction
cp -n config/config.example.php config/config.php
chmod 600 config/config.php
chmod 755 storage storage/logs storage/mail
$PHP -v | head -1
```

## 3. Configuración

```bash
nano ~/filoacademia/config/config.php
```

Valores de producción:

| Clave | Valor |
|---|---|
| `app.env` | `'production'` |
| `app.url` | `'https://TU_DOMINIO'` (sin barra final) |
| `mercadopago.checkout_mode` | `'pro'` o `'bricks'` |
| `mercadopago.public_key` / `access_token` | Credenciales de **producción** (o de prueba para el ensayo, ver §6) |
| `mercadopago.webhook_secret` | Se obtiene en el §5 |
| `mail.transport` | `'smtp'` |
| `mail.host` | `'mail.TU_DOMINIO'` |
| `mail.port` / `encryption` | `465` / `'ssl'` |
| `mail.username` / `from_email` | `'pagos@TU_DOMINIO'` |
| `mail.password` | la del buzón |
| `mail.admin_recipients` | `['hola@herofilo.mx']` (y los que hagan falta) |

Guarda con `Ctrl+O`, `Enter`, `Ctrl+X`. Luego:

```bash
# — Bloque 3: crear tablas y verificar —
cd ~/filoacademia && /opt/cpanel/ea-php84/root/usr/bin/php bin/migrate.php
```

## 4. Publicar `public/` como raíz del sitio

Elige **una** opción.

**A. (Recomendada) Raíz del dominio apuntando a la app.** En **Domains** →
*Manage* el dominio → *Document Root* = `filoacademia/public`. Si tu versión de
cPanel lo permite, no hace falta nada más.

**B. Enlace simbólico** (si no se puede cambiar la raíz del dominio principal):

```bash
# — Bloque 4B: public_html → filoacademia/public (respalda lo anterior) —
cd ~ && mv public_html public_html.respaldo-$(date +%Y%m%d)
ln -s ~/filoacademia/public ~/public_html
ls -la ~ | grep public_html
```

**C. Copia** (solo si el hosting no sigue enlaces simbólicos):

```bash
# — Bloque 4C: copiar public/ y decirle dónde está la app —
cd ~ && mv public_html public_html.respaldo-$(date +%Y%m%d) && mkdir public_html
cp -a ~/filoacademia/public/. ~/public_html/
echo "<?php return '/home/TU_USUARIO/filoacademia';" > ~/public_html/app-root.local.php
```
Con la opción C, **cada actualización** debe repetir el `cp -a` (ver §7).

Comprueba: `https://TU_DOMINIO`, `https://TU_DOMINIO/academia`,
`https://TU_DOMINIO/pagar/`. Y que `https://TU_DOMINIO/_boot.php` responda 403.
(`config/` y `storage/` no están dentro de `public/`, así que no tienen URL.)

## 5. Mercado Pago

1. <https://www.mercadopago.com.mx/developers/panel> → **Crear aplicación**
   (tipo: pagos en línea; producto: Checkout Pro y/o Checkout Bricks).
2. **Credenciales de prueba** y **de producción** → copia *Public Key* y
   *Access Token* a `config.php`.
3. **Webhooks → Configurar notificaciones** (es la ÚNICA vía de avisos: el
   sitio no envía `notification_url`, ver docs/FLUJO-DE-PAGO.md):
   - URL (modo productivo y de prueba): `https://TU_DOMINIO/webhooks/mercadopago.php`
   - Eventos: **Pagos**
   - Guarda y copia la **clave secreta** → `mercadopago.webhook_secret`.
4. Usa el botón **Simular** del panel de webhooks: en
   `storage/logs/app-AAAA-MM.log` y en la tabla `webhook_events` debe aparecer
   el aviso con firma válida y el resultado «pago inexistente en Mercado Pago»
   (el pago simulado no existe; se responde 200 para que no reintente).

## 6. Ensayo con credenciales de prueba (antes de cobrar de verdad)

Con las credenciales **de prueba** en `config.php`:

1. Crea un pedido real en el sitio y paga con una
   [tarjeta de prueba](https://www.mercadopago.com.mx/developers/es/docs/checkout-pro/integration-test/test-cards)
   (titular `APRO` = aprobado, `OTHE` = rechazado).
2. Verifica: pantalla «Pago recibido», correo al cliente, correo al taller.
3. Repite con `OTHE`: pantalla «El pago no se completó» y botón de reintento.
4. Repite con OXXO: pantalla con «Ver mi ficha de pago».
5. Cambia a credenciales de **producción** y haz un pago real pequeño; reembólsalo
   desde el panel de Mercado Pago y confirma que el pedido pasa a `refunded`.

## 6b. Revisión periódica de pagos (obligatoria)

Respaldo del webhook: confirma pagos aunque el cliente cierre la ventana o el
webhook falle. En **cPanel → Cron Jobs**, «Una vez cada 15 minutos»:

```
*/15 * * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/TU_USUARIO/filoacademia/bin/sync-pending.php >/dev/null 2>&1
```

Para probarlo a mano: `/opt/cpanel/ea-php84/root/usr/bin/php ~/filoacademia/bin/sync-pending.php`
→ imprime «Revisados: N · actualizados: N · errores: 0».

## 7. Actualizar a una nueva versión

```bash
# — Bloque 7: actualizar —
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
cd ~/filoacademia && cp storage/database.sqlite storage/respaldo-$(date +%Y%m%d-%H%M).sqlite 2>/dev/null
git pull --ff-only
if command -v composer >/dev/null; then composer install --no-dev --optimize-autoloader --no-interaction; else $PHP composer.phar install --no-dev --optimize-autoloader --no-interaction; fi
$PHP bin/migrate.php
# Solo si usas la opción C del §4:
# cp -a ~/filoacademia/public/. ~/public_html/
```

## 8. Respaldo

La única información que no está en git es `config/config.php` y
`storage/database.sqlite`. Agrega en **cPanel → Cron Jobs** (diario, 3:00):

```
0 3 * * * cp ~/filoacademia/storage/database.sqlite ~/filoacademia/storage/respaldo-$(date +\%u).sqlite
```

(guarda 7 copias rotativas, una por día de la semana). Los respaldos de cPanel
también incluyen la carpeta completa.

## 9. Volver atrás

```bash
# — Bloque 9: regresar a la versión anterior —
cd ~/filoacademia && git log --oneline -5      # identifica la versión buena
git checkout <HASH_BUENO>
/opt/cpanel/ea-php84/root/usr/bin/php bin/migrate.php
```
Para volver a la última: `git checkout main && git pull --ff-only`.
