# CENTRAL — Runbook de recuperación de despliegue

## Propósito

Este runbook define cómo recuperar CENTRAL cuando un despliegue falla. No convierte el rollback en una acción automática: una migración ya aplicada puede volver inseguro un simple cambio de código hacia atrás.

## Principios

- ejecutar operaciones de Git/Laravel como `centralarpynet`, nunca como root;
- conservar el SHA previo y el SHA objetivo antes de cambiar código;
- no desplegar con worktree sucio;
- no ejecutar `migrate:rollback` a ciegas;
- no restaurar base de datos sin confirmar el punto de recuperación y la compatibilidad del código;
- levantar mantenimiento solo después de verificar aplicación, migraciones, cachés y respuesta HTTP del origen.

## Señales ya provistas por el deploy

`scripts/deploy-cpanel-central.sh` registra `PREVIOUS_SHA`, comprueba el remote, estado limpio, permisos, SHA objetivo, dependencias, migraciones, cachés, archivos públicos y HTTP directo del origen. Si el proceso se interrumpe, su trap intenta levantar Laravel y muestra el commit previo.

## Clasificación del fallo

### A. Falla antes de ejecutar migraciones

Si Composer, checkout o una validación previa falla antes de `artisan migrate --force`, puede recuperarse el código anterior con bajo riesgo, siempre verificando primero que no hubo cambios de esquema.

### B. Falla después de ejecutar migraciones

No hacer reset automático al SHA anterior. Determinar primero:

1. qué migraciones se aplicaron;
2. si son backward-compatible con el código anterior;
3. si existe backup verificado anterior al cambio;
4. si conviene corregir hacia adelante en lugar de revertir.

### C. Aplicación responde pero una función está degradada

Mantener la versión desplegada si la integridad de datos está preservada. Desactivar únicamente la regla/integración afectada desde los controles existentes y usar Estado y recuperación para identificar automatizaciones fallidas, bloqueadas o stale. No reintentar escrituras mutantes por lote.

## Recuperación de código sin migraciones incompatibles

Como `centralarpynet`:

```bash
cd /home/centralarpynet/central_app
git status --short
git rev-parse HEAD
git show --no-patch --oneline <SHA_PREVIO>
```

Confirmar que el worktree esté limpio y que `<SHA_PREVIO>` sea exactamente el commit deseado. Después, durante una ventana de mantenimiento deliberada:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php artisan down --retry=60 --refresh=15
git checkout --detach <SHA_PREVIO>
/opt/cpanel/ea-php84/root/usr/bin/php /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
/opt/cpanel/ea-php84/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php84/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php84/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php84/root/usr/bin/php artisan view:cache
```

No ejecutar `migrate:rollback` dentro de este procedimiento. Si el esquema ya cambió, detener aquí y evaluar compatibilidad/backup.

Después de validar el código y el esquema:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php artisan migrate:status
/opt/cpanel/ea-php84/root/usr/bin/php artisan up
```

Verificar el origen directamente y las funciones críticas antes de cerrar el incidente.

## Recuperación de base de datos

La aplicación no restaura backups por sí misma. Antes de cualquier restore:

- identificar backup exacto y fecha/hora;
- confirmar alcance de pérdida de datos posterior al backup;
- detener escrituras;
- conservar una copia del estado actual aunque esté degradado;
- restaurar en entorno aislado cuando sea posible y validar migraciones/modelos;
- documentar quién autorizó la restauración y qué datos pueden perderse.

## Scheduler y automatizaciones

Si una regla falla repetidamente:

1. desactivar la regla específica;
2. revisar su ejecución en FRONT;
3. corregir la causa;
4. usar vista previa antes de reactivarla;
5. nunca convertir un `failed`, `blocked` o `stale` en ejecución automática sin recalcular condición, autorización y versión.

L2/L3 conservan sus límites diarios y undo; hardening no los omite durante recuperación.

## Criterio de cierre

Un incidente de despliegue se considera recuperado cuando:

- Laravel está fuera de mantenimiento;
- `migrate:status` es coherente con el código activo;
- cachés se regeneraron correctamente;
- el origen devuelve HTTP esperado;
- no existen errores críticos nuevos por la recuperación;
- el SHA activo está documentado;
- cualquier rollback/restore y su impacto de datos quedaron registrados.
