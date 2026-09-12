#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/home/centralarpynet/central_app"
WEB_DIR="/home/centralarpynet/public_html"
PHP_BIN="/opt/cpanel/ea-php84/root/usr/bin/php"
COMPOSER_BIN="/usr/local/bin/composer"
DEPLOY_REF="${1:-feature/2.15-users-organizations}"
APP_URL="https://central.arpynet.com"

log() {
    printf '\n[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    printf '\nERROR: %s\n' "$*" >&2
    exit 1
}

if [[ "$(id -un)" == "root" ]]; then
    fail "Ejecuta este script como el usuario centralarpynet, no como root."
fi

[[ -d "$APP_DIR/.git" ]] || fail "No existe el repositorio en $APP_DIR"
[[ -x "$PHP_BIN" ]] || fail "No existe PHP 8.4 en $PHP_BIN"
[[ -f "$COMPOSER_BIN" ]] || fail "No existe Composer en $COMPOSER_BIN"
command -v git >/dev/null 2>&1 || fail "git no está disponible"
command -v rsync >/dev/null 2>&1 || fail "rsync no está disponible"

cd "$APP_DIR"

git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true

ORIGIN_URL="$(git remote get-url origin 2>/dev/null || true)"
[[ -n "$ORIGIN_URL" ]] || fail "El repositorio no tiene remote origin"

if [[ "$ORIGIN_URL" != *"central-arpynet"* ]]; then
    fail "El remote origin no parece corresponder a Central ARPYNET: $ORIGIN_URL"
fi

if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    fail "Hay cambios locales sin guardar. No se desplegará para evitar sobrescribirlos."
fi

PREVIOUS_SHA="$(git rev-parse HEAD)"
CURRENT_BRANCH="$(git branch --show-current)"

log "Estado actual: $CURRENT_BRANCH @ $PREVIOUS_SHA"
log "Buscando $DEPLOY_REF en origin"

git fetch origin --prune

git rev-parse --verify "origin/$DEPLOY_REF" >/dev/null 2>&1 \
    || fail "No existe origin/$DEPLOY_REF"

TARGET_SHA="$(git rev-parse "origin/$DEPLOY_REF")"
log "Objetivo: $DEPLOY_REF @ $TARGET_SHA"

MAINTENANCE_ON=0
cleanup() {
    local exit_code=$?

    if [[ "$MAINTENANCE_ON" -eq 1 ]]; then
        log "Levantando Laravel"
        "$PHP_BIN" artisan up >/dev/null 2>&1 || true
    fi

    if [[ $exit_code -ne 0 ]]; then
        printf '\nDespliegue interrumpido. Commit previo: %s\n' "$PREVIOUS_SHA" >&2
    fi

    exit "$exit_code"
}
trap cleanup EXIT

log "Activando modo mantenimiento"
"$PHP_BIN" artisan down --retry=60 --refresh=15 || true
MAINTENANCE_ON=1

log "Actualizando código"
git checkout -B "$DEPLOY_REF" "origin/$DEPLOY_REF"

log "Instalando dependencias PHP de producción"
"$PHP_BIN" "$COMPOSER_BIN" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

log "Limpiando cachés anteriores"
"$PHP_BIN" artisan optimize:clear

log "Ejecutando migraciones"
"$PHP_BIN" artisan migrate --force

log "Asegurando enlace de storage"
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

log "Regenerando cachés"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

log "Ajustando permisos de directorios escribibles"
chmod -R u+rwX,g+rwX storage bootstrap/cache

if [[ ! -f "$WEB_DIR/index.php" ]]; then
    fail "No existe $WEB_DIR/index.php"
fi

if ! grep -q "central_app/vendor/autoload.php" "$WEB_DIR/index.php"; then
    fail "El index.php público no apunta a central_app/vendor/autoload.php"
fi

if ! grep -q "central_app/bootstrap/app.php" "$WEB_DIR/index.php"; then
    fail "El index.php público no apunta a central_app/bootstrap/app.php"
fi

log "Sincronizando archivos públicos sin reemplazar el index.php adaptado a cPanel"
rsync -a \
    --exclude='index.php' \
    "$APP_DIR/public/" \
    "$WEB_DIR/"

if "$PHP_BIN" artisan list --raw 2>/dev/null | grep -q '^horizon:terminate'; then
    log "Solicitando reinicio limpio de Horizon"
    "$PHP_BIN" artisan horizon:terminate || true
fi

log "Desactivando modo mantenimiento"
"$PHP_BIN" artisan up
MAINTENANCE_ON=0

DEPLOYED_SHA="$(git rev-parse HEAD)"

log "Verificación Laravel"
"$PHP_BIN" artisan about --only=environment 2>/dev/null || true
"$PHP_BIN" artisan migrate:status | tail -n 15

if command -v curl >/dev/null 2>&1; then
    log "Verificando respuesta HTTP de Central"
    HTTP_CODE="$(curl -k -L -sS -o /dev/null -w '%{http_code}' "$APP_URL/mi-dia" || true)"
    printf 'HTTP %s\n' "$HTTP_CODE"
fi

printf '\n========================================\n'
printf 'Central ARPYNET desplegado\n'
printf 'Rama:  %s\n' "$DEPLOY_REF"
printf 'Commit: %s\n' "$DEPLOYED_SHA"
printf 'Previo: %s\n' "$PREVIOUS_SHA"
printf 'URL:    %s\n' "$APP_URL"
printf '========================================\n'
