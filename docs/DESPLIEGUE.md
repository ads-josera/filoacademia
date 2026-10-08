# Manual de despliegue (cPanel · PHP 8.4)

Cada bloque se copia y pega **completo** en la terminal del servidor (SSH o
cPanel → Terminal). Están escritos para el servidor de **herofilo.mx**; para otro
servidor cambia el usuario, el dominio y revisa la sección 0.

## 0. El servidor (verificado el 2026-10-07)

| Qué | Valor |
|---|---|
| Dominio | `herofilo.mx` (DNS `dizinc.com`, IP 107.161.187.186) |
| SSL | Let's Encrypt (AutoSSL), renovación automática |
| Usuario / home | `herofilo` / `/home/herofilo` |
| Sistema | cPanel sobre **CloudLinux** (CageFS, Imunify360) |
| PHP del sitio | **8.4** (`ea-php84`), lo fija cPanel en `public_html/.htaccess` |
| PHP de la terminal | ⚠️ **8.1** (`php`): NO sirve. Usar siempre `/opt/cpanel/ea-php84/root/usr/bin/php` |
| PHP 8.4 de CloudLinux (`/opt/alt/php84`) | ⚠️ sin `mbstring` ni `pdo_sqlite`: no usar |
| `allow_url_fopen` | Desactivado en PHP (correcto, no cambiar). Composer se descarga con `curl` y se verifica su SHA-256; el sitio usa cURL para Mercado Pago |
| Composer | No viene instalado: `bin/deploy.sh` descarga `composer.phar` la primera vez |
| Buzones | `pagos@herofilo.mx` (envía los avisos), `hola@herofilo.mx` (atiende respuestas) |
| Correo | MX, SPF, DKIM y DMARC (`p=none`) publicados |

**Cómo queda instalado:**

```
/home/herofilo/
├── filoacademia/        ← la aplicación (git): código, config.php, base de datos, bitácoras
└── public_html/         ← copia de filoacademia/public/ que hace bin/deploy.sh
    ├── .htaccess        ← bloques de cPanel (PHP 8.4) + reglas del sitio
    ├── php.ini, .user.ini, .well-known/, cgi-bin/   ← de cPanel: el script no los toca
    └── app-root.local.php  ← le dice al sitio dónde está la aplicación
```

`public_html` **no se reemplaza** por un enlace a `public/`: perdería el bloque
con el que cPanel fija PHP 8.4 (el sitio caería a 8.1) y la carpeta
`.well-known` donde se renueva el SSL. `bin/deploy.sh` copia los archivos y
reconstruye el `.htaccess` conservando lo de cPanel.

---

## 1. Llave para descargar el código de GitHub (una sola vez)

```bash
# — Bloque 1: llave de solo lectura para el repositorio —
mkdir -p ~/.ssh && chmod 700 ~/.ssh
ssh-keygen -t ed25519 -C "deploy@herofilo.mx" -f ~/.ssh/filoacademia_deploy -N ""
cat >> ~/.ssh/config <<'EOF'
Host github-filoacademia
    HostName github.com
    User git
    IdentityFile ~/.ssh/filoacademia_deploy
    IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config
echo; echo "=== Copia esta llave en GitHub → ads-josera/filoacademia → Settings → Deploy keys ==="
cat ~/.ssh/filoacademia_deploy.pub
```

En GitHub: **Settings → Deploy keys → Add deploy key**, pega la llave, título
`herofilo.mx`, **sin** «Allow write access».

## 2. Descargar el código (una sola vez)

```bash
# — Bloque 2: clonar y preparar la configuración —
cd ~ && git clone git@github-filoacademia:ads-josera/filoacademia.git filoacademia
cd ~/filoacademia
cp -n config/config.example.php config/config.php && chmod 600 config/config.php
ls -la
```

## 3. Configuración de producción

```bash
nano ~/filoacademia/config/config.php
```

| Clave | Valor |
|---|---|
| `app.env` | `'production'` |
| `app.url` | `'https://herofilo.mx'` |
| `mercadopago.checkout_mode` | `'pro'` o `'bricks'` |
| `mercadopago.public_key` / `access_token` | De **prueba** durante el ensayo; de **producción** al abrir |
| `mercadopago.webhook_secret` | Se obtiene en el paso 6 |
| `mail.transport` | `'smtp'` |
| `mail.host` / `port` / `encryption` | `'mail.herofilo.mx'` / `465` / `'ssl'` |
| `mail.username` / `from_email` | `'pagos@herofilo.mx'` |
| `mail.password` | la del buzón `pagos@` |
| `mail.admin_recipients` | `['hola@herofilo.mx']` (y los que hagan falta) |

Guardar: `Ctrl+O`, `Enter`, `Ctrl+X`.

## 4. Primer despliegue

```bash
# — Bloque 4: simulación (no cambia nada) —
cd ~/filoacademia && bash bin/deploy.sh --dry-run --no-pull
```

Revisa la salida: debe mostrar los bloques «cPanel-generated» con `ea-php84`
que se conservarán y la lista de archivos que se copiarán. Si todo se ve bien:

```bash
cd ~/filoacademia && bash bin/deploy.sh --no-pull
```

Al final comprueba solo que `/`, `/academia` y `/pagar/` respondan 200 y que
`/app-root.local.php` responda 403.

## 5. Revisión periódica de pagos (cron, obligatoria)

Respaldo del webhook: confirma pagos aunque el cliente cierre la ventana o el
aviso de Mercado Pago falle. **cPanel → Cron Jobs**, «Una vez cada 15 minutos»:

```
*/15 * * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/herofilo/filoacademia/bin/sync-pending.php >/dev/null 2>&1
```

Probarlo a mano: `/opt/cpanel/ea-php84/root/usr/bin/php ~/filoacademia/bin/sync-pending.php`
→ «Revisados: N · actualizados: N · errores: 0».

## 6. Webhook de Mercado Pago

Es la **única** vía de avisos (el sitio no envía `notification_url`, ver
docs/FLUJO-DE-PAGO.md). En la aplicación del cliente en
<https://www.mercadopago.com.mx/developers/panel/app>:

1. **Webhooks → Configurar notificaciones**.
2. URL (modo de prueba y productivo): `https://herofilo.mx/webhooks/mercadopago.php`
3. Evento: **Pagos**. Guardar.
4. Copiar la **clave secreta** a `mercadopago.webhook_secret` en `config.php`.
5. Botón **Simular**: en la tabla `webhook_events` debe quedar el aviso con
   firma válida y «pago inexistente en Mercado Pago» (el pago simulado no existe).

```bash
sqlite3 ~/filoacademia/storage/database.sqlite "SELECT created_at, signature_valid, result FROM webhook_events ORDER BY id DESC LIMIT 5;"
```

## 7. Ensayo con credenciales de prueba (antes de cobrar de verdad)

1. Pedido real en el sitio, pagado con el **comprador de prueba** y una
   [tarjeta de prueba](https://www.mercadopago.com.mx/developers/es/docs/checkout-pro/integration-test/test-cards)
   (titular `APRO` = aprobado, `OTHE` = rechazado).
2. Verificar: pantalla «Pago recibido», correo al cliente, correo a `hola@`.
3. Repetir con `OTHE`: «El pago no se completó» y botón de reintento.
4. Repetir con OXXO: «Ver mi ficha de pago».
5. Cambiar a credenciales de **producción**, hacer un pago real pequeño y
   reembolsarlo desde el panel de Mercado Pago (el pedido pasa a `refunded`).

## 8. Actualizar a una nueva versión

```bash
# — Bloque 8: actualizar —
cd ~/filoacademia && mkdir -p storage/backups && cp storage/database.sqlite storage/backups/db-$(date +%Y%m%d-%H%M).sqlite 2>/dev/null
bash bin/deploy.sh
```

## 9. Respaldo diario de la base de datos

**cPanel → Cron Jobs**, diario a las 3:00 (guarda 7 copias rotativas):

```
0 3 * * * mkdir -p /home/herofilo/filoacademia/storage/backups && cp /home/herofilo/filoacademia/storage/database.sqlite /home/herofilo/filoacademia/storage/backups/db-dia-$(date +\%u).sqlite
```

Lo único que no está en git es `config/config.php` y `storage/`. Los respaldos
de cPanel incluyen la carpeta completa.

## 10. Volver atrás

```bash
# — Bloque 10: regresar a una versión anterior —
cd ~/filoacademia && git log --oneline -5          # identifica la versión buena
git checkout <HASH_BUENO> && bash bin/deploy.sh --no-pull
```

Para volver a la última: `git checkout main && bash bin/deploy.sh`.
El `.htaccess` anterior queda en `storage/backups/htaccess-*` por si hiciera falta.
