#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/home/centralarpynet/central_app"
APP_USER="centralarpynet"
APP_GROUP="$(id -gn "$APP_USER")"

log() {
    printf '\n[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    printf '\nERROR: %s\n' "$*" >&2
    exit 1
}

if [[ "$(id -u)" -ne 0 ]]; then
    fail "Este script debe ejecutarse como root porque corrige propiedad de archivos."
fi

[[ -d "$APP_DIR/.git" ]] || fail "No existe el repositorio en $APP_DIR"

log "Corrigiendo propietario del repositorio"
chown -R "$APP_USER:$APP_GROUP" "$APP_DIR"

log "Asegurando permisos de escritura para el usuario de cPanel"
find "$APP_DIR" -type d -exec chmod u+rwx {} +
find "$APP_DIR" -type f -exec chmod u+rw {} +

log "Verificando como $APP_USER"
su -s /bin/bash - "$APP_USER" -c "
    cd '$APP_DIR'
    git config --global --add safe.directory '$APP_DIR' >/dev/null 2>&1 || true
    printf 'Usuario: '; id -un
    printf 'Rama: '; git branch --show-current
    printf 'Commit: '; git rev-parse HEAD
    echo 'Estado:'
    git status --short
    test -w resources/views/components/operational-nav.blade.php
    test -w resources/views/components
"

printf '\n========================================\n'
printf 'Permisos del repositorio corregidos\n'
printf 'Propietario: %s:%s\n' "$APP_USER" "$APP_GROUP"
printf '========================================\n'
