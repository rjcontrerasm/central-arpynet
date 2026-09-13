#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/home/centralarpynet/central_app"
PHP_BIN="/opt/cpanel/ea-php84/root/usr/bin/php"
COMPOSER_BIN="/usr/local/bin/composer"
TEST_REF="${1:-main}"
TEST_DIR="/home/centralarpynet/.central-preflight-$$"

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

cd "$APP_DIR"
git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true

git fetch origin --prune
git rev-parse --verify "origin/$TEST_REF" >/dev/null 2>&1 \
    || fail "No existe origin/$TEST_REF"

cleanup() {
    local exit_code=$?

    cd "$APP_DIR" 2>/dev/null || true
    git worktree remove --force "$TEST_DIR" >/dev/null 2>&1 || true
    rm -rf "$TEST_DIR" >/dev/null 2>&1 || true

    if [[ $exit_code -ne 0 ]]; then
        printf '\nPreflight FALLÓ. Producción no fue modificada.\n' >&2
    fi

    exit "$exit_code"
}
trap cleanup EXIT

log "Creando entorno temporal para $TEST_REF"
git worktree add --detach "$TEST_DIR" "origin/$TEST_REF"

if [[ -f "$APP_DIR/.env" ]]; then
    cp "$APP_DIR/.env" "$TEST_DIR/.env"
fi

cd "$TEST_DIR"

log "Instalando dependencias de prueba en el worktree temporal"
"$PHP_BIN" "$COMPOSER_BIN" install \
    --prefer-dist \
    --no-interaction \
    --no-progress

log "Validando sintaxis de los archivos principales modificados"
for file in \
    app/Models/User.php \
    app/Models/Project.php \
    app/Models/ServiceOrder.php \
    app/Providers/AppServiceProvider.php \
    app/Filament/Resources/Tasks/TaskResource.php \
    app/Filament/Resources/Incidents/IncidentResource.php \
    app/Filament/Resources/ServiceOrders/ServiceOrderResource.php \
    app/Filament/Resources/Projects/ProjectResource.php \
    app/Http/Controllers/DailyOpsController.php \
    database/migrations/2026_09_12_130500_add_is_active_to_users_table.php \
    database/migrations/2026_09_12_131500_add_assigned_to_to_service_orders_table.php \
    database/migrations/2026_09_12_155500_create_project_user_table.php \
    tests/Feature/MultiUserOrganizationAccessTest.php \
    tests/Feature/ProjectParticipantsTest.php
do
    "$PHP_BIN" -l "$file" >/dev/null
    printf 'OK  %s\n' "$file"
done

log "Ejecutando pruebas multiusuario con SQLite en memoria"
"$PHP_BIN" artisan test tests/Feature/MultiUserOrganizationAccessTest.php
"$PHP_BIN" artisan test tests/Feature/ProjectParticipantsTest.php

if [[ "${FULL_TESTS:-0}" == "1" ]]; then
    log "Ejecutando suite Feature completa"
    "$PHP_BIN" artisan test --testsuite=Feature
fi

printf '\n========================================\n'
printf 'Preflight OK\n'
printf 'Rama:   %s\n' "$TEST_REF"
printf 'Commit: %s\n' "$(git rev-parse HEAD)"
printf 'Producción no fue modificada.\n'
printf '========================================\n'
