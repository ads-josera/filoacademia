#!/usr/bin/env bash
#
# Despliegue en cPanel: actualiza el código y publica public/ en public_html
# sin tocar lo que administra cPanel.
#
#   bin/deploy.sh --dry-run    # muestra qué haría, no cambia nada
#   bin/deploy.sh              # despliega
#   bin/deploy.sh --no-pull    # despliega sin git pull (primera instalación)
#
# Qué conserva de public_html (lo administra cPanel, no el repositorio):
#   - los bloques «cPanel-generated» del .htaccess (versión de PHP 8.4, INI)
#   - php.ini, .user.ini, .well-known/ (renovación del SSL), cgi-bin/
#
# Variables (opcionales):
#   PHP_BIN      PHP 8.4 de cPanel (por omisión /opt/cpanel/ea-php84/root/usr/bin/php;
#                el «php» de la terminal de este servidor es 8.1 y no sirve)
#   PUBLIC_HTML  carpeta pública (por omisión ~/public_html)
#   SITE_URL     para la comprobación final (por omisión, app.url de config.php)

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
PUBLIC_HTML="${PUBLIC_HTML:-$HOME/public_html}"
DRY_RUN=0
PULL=1

for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        --no-pull) PULL=0 ;;
        *) echo "Opción desconocida: $arg" >&2; exit 2 ;;
    esac
done

say()  { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail() { printf '\n\033[31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }
run()  { if [ "$DRY_RUN" -eq 1 ]; then echo "  (simulación) $*"; else "$@"; fi; }

# ---------- Comprobaciones previas ----------
say "Comprobaciones"
[ -x "$PHP_BIN" ] || fail "No existe $PHP_BIN. Define PHP_BIN con la ruta de PHP 8.4."
PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || fail "PHP $PHP_VERSION: se necesita 8.3 o superior."
# Se lee la lista una vez: «php -m | grep -q» falla con pipefail (grep cierra
# la tubería antes de que PHP termine de escribir y PHP recibe SIGPIPE).
PHP_MODULES="$("$PHP_BIN" -m)"
for ext in pdo_sqlite curl mbstring openssl; do
    grep -qix "$ext" <<<"$PHP_MODULES" || fail "A $PHP_BIN le falta la extensión $ext."
done
echo "  PHP $PHP_VERSION ($PHP_BIN) con pdo_sqlite, curl, mbstring, openssl"
[ -d "$PUBLIC_HTML" ] || fail "No existe $PUBLIC_HTML."
[ -f "$APP_DIR/config/config.php" ] || fail "Falta config/config.php (cópialo de config/config.example.php y llénalo)."
echo "  Aplicación: $APP_DIR"
echo "  Carpeta pública: $PUBLIC_HTML"

# ---------- Bloques de cPanel a conservar ----------
say "Bloques de cPanel que se conservan en .htaccess"
CPANEL_BLOCKS=""
if [ -f "$PUBLIC_HTML/.htaccess" ]; then
    CPANEL_BLOCKS="$(awk '/BEGIN cPanel-generated/{keep=1} keep{print} /END cPanel-generated/{keep=0; print ""}' "$PUBLIC_HTML/.htaccess")"
fi
if [ -z "$CPANEL_BLOCKS" ]; then
    fail "No encontré bloques «cPanel-generated» en $PUBLIC_HTML/.htaccess. Sin ellos el sitio podría quedar en PHP 8.1. Vuelve a elegir PHP 8.4 en cPanel → MultiPHP Manager y repite."
fi
echo "$CPANEL_BLOCKS" | sed 's/^/  | /'
grep -q 'ea-php8[4-9]' <<<"$CPANEL_BLOCKS" || fail "El bloque de cPanel no indica PHP 8.4+. Revisa MultiPHP Manager."

# ---------- Código y dependencias ----------
cd "$APP_DIR"
if [ "$PULL" -eq 1 ]; then
    say "Actualizando código"
    run git pull --ff-only
fi

say "Dependencias (Composer)"
COMPOSER=("$PHP_BIN" "$APP_DIR/composer.phar")
if command -v composer >/dev/null 2>&1; then
    COMPOSER=("$PHP_BIN" "$(command -v composer)")
elif [ ! -f "$APP_DIR/composer.phar" ]; then
    echo "  Instalando composer.phar (con verificación de firma)"
    if [ "$DRY_RUN" -eq 0 ]; then
        EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
        "$PHP_BIN" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        ACTUAL="$("$PHP_BIN" -r "echo hash_file('sha384', 'composer-setup.php');")"
        [ "$EXPECTED" = "$ACTUAL" ] || { rm -f composer-setup.php; fail "Firma del instalador de Composer inválida."; }
        "$PHP_BIN" composer-setup.php --quiet && rm -f composer-setup.php
    fi
fi
run "${COMPOSER[@]}" install --no-dev --optimize-autoloader --no-interaction

say "Base de datos"
run "$PHP_BIN" bin/migrate.php
run chmod 755 storage storage/logs storage/mail
run chmod 600 config/config.php

# ---------- Publicar public/ en public_html ----------
# Respaldo ANTES de copiar: sin rsync, la copia simple sobrescribe el .htaccess.
if [ "$DRY_RUN" -eq 0 ]; then
    mkdir -p "$APP_DIR/storage/backups"
    cp "$PUBLIC_HTML/.htaccess" "$APP_DIR/storage/backups/htaccess-$(date +%Y%m%d-%H%M%S)"
fi

say "Publicando archivos en $PUBLIC_HTML"
PROTECT=(--exclude '.htaccess' --exclude '.well-known/' --exclude 'cgi-bin/' --exclude 'php.ini' --exclude '.user.ini' --exclude 'app-root.local.php' --exclude 'error_log')
if command -v rsync >/dev/null 2>&1; then
    # -rlt (no -a): no se copian permisos, dueño ni grupo, así public_html
    # conserva los de cPanel (750, grupo nobody). Los permisos de lo copiado
    # se fijan después (no se usa --chmod: rsync antiguos no lo tienen).
    RSYNC_FLAGS=(-rlt --delete --itemize-changes "${PROTECT[@]}")
    [ "$DRY_RUN" -eq 1 ] && RSYNC_FLAGS+=(--dry-run)
    rsync "${RSYNC_FLAGS[@]}" "$APP_DIR/public/" "$PUBLIC_HTML/" | sed 's/^/  /'
else
    echo "  (sin rsync: copia simple, no elimina archivos viejos)"
    run cp -R "$APP_DIR/public/." "$PUBLIC_HTML/"
fi

# Permisos estándar de hosting en lo publicado: carpetas 755, archivos 644 (sin
# escritura para el grupo). No se toca public_html en sí ni lo de cPanel.
if [ "$DRY_RUN" -eq 1 ]; then
    echo "  (simulación) permisos 755/644 en lo publicado"
else
    find "$PUBLIC_HTML" -mindepth 1 \( -path "$PUBLIC_HTML/.well-known" -o -path "$PUBLIC_HTML/cgi-bin" \) -prune \
        -o -type d -exec chmod 755 {} + \
        -o -type f ! -name 'php.ini' ! -name '.user.ini' -exec chmod 644 {} +
fi

# Dónde está la aplicación (public_html es una copia, no está dentro del proyecto).
if [ "$DRY_RUN" -eq 1 ]; then
    echo "  (simulación) escribir $PUBLIC_HTML/app-root.local.php → $APP_DIR"
else
    printf "<?php\n\n// Generado por bin/deploy.sh: ubicación de la aplicación.\nreturn '%s';\n" "$APP_DIR" > "$PUBLIC_HTML/app-root.local.php"
fi

# .htaccess = bloques de cPanel + reglas del sitio.
say ".htaccess"
NEW_HTACCESS="$(printf '%s\n\n# ==== Reglas del sitio (desde public/.htaccess del repositorio; no editar aquí) ====\n\n%s\n' "$CPANEL_BLOCKS" "$(cat "$APP_DIR/public/.htaccess")")"
if [ "$DRY_RUN" -eq 1 ]; then
    echo "  (simulación) se escribiría .htaccess con $(echo "$NEW_HTACCESS" | wc -l | tr -d ' ') líneas"
else
    printf '%s' "$NEW_HTACCESS" > "$PUBLIC_HTML/.htaccess"
    echo "  Escrito (respaldo del anterior en storage/backups/)."
fi

# ---------- Comprobación final ----------
SITE_URL="${SITE_URL:-$("$PHP_BIN" -r '$c = require "config/config.php"; echo rtrim($c["app"]["url"] ?? "", "/");')}"
if [ "$DRY_RUN" -eq 0 ] && [ -n "$SITE_URL" ]; then
    say "Comprobación en $SITE_URL"
    for path in / /academia /pagar/; do
        code="$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL$path")"
        printf '  %-10s → %s\n' "$path" "$code"
        [ "$code" = "200" ] || fail "$SITE_URL$path respondió $code. Revisa ~/logs/php.error.log y storage/logs/."
    done
    code="$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL/app-root.local.php")"
    printf '  %-22s → %s (debe ser 403)\n' "/app-root.local.php" "$code"
fi

say "Listo$([ "$DRY_RUN" -eq 1 ] && echo ' (simulación: no se cambió nada)')"
