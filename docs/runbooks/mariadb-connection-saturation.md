# Runbook — Saturación de conexiones MariaDB

## Señal conocida

CENTRAL registró incidentes `SQLSTATE[HY000] [1040] Too many connections`
cuando el scheduler intentaba leer su cache de coordinación.

El hallazgo no implica que CENTRAL fuera el consumidor dominante:
MariaDB es compartido por múltiples cuentas del servidor.

## Baseline desde 2026-09-24

- `max_connections=120`
- `tmp_table_size=64M`
- `max_heap_table_size=64M`
- `wait_timeout=300`
- CENTRAL usa `CACHE_STORE=file`
- monitor externo:
  `/usr/local/sbin/arpynet-mariadb-connection-watch`
- snapshots:
  `/var/log/arpynet-mariadb-watch`

## Umbrales del monitor

- WARNING: 70 %
- CRITICAL: 85 %

El monitor registra usuario, base, host, comando, conexiones agregadas,
procesos PHP-FPM, carga y memoria. No registra el texto SQL.

## Diagnóstico desde CENTRAL

Ejecutar como usuario de la aplicación:

```bash
php artisan central:db-health
```

La salida muestra presión actual y máximo observado. Las métricas son
globales del servidor MariaDB y no prueban por sí solas cuál aplicación
originó un pico.

## Si vuelve a aparecer 1040

1. No aumentar `max_connections` inmediatamente.
2. Revisar el snapshot más próximo al evento.
3. Identificar usuario/base/pool que concentró conexiones.
4. Revisar sleeps, `pm.max_children` y patrón de tráfico del consumidor.
5. Confirmar RAM disponible antes de ampliar capacidad.
6. Mantener CENTRAL con cache fuera de SQL.
7. Cambiar límites solo con backup y rollback definidos.

## Indicadores que requieren revisión

- uso actual sostenido >= 70 %;
- pico repetido >= 90 %;
- crecimiento de `Connection_errors_max_connections`;
- gran cantidad de conexiones `Sleep` de un mismo usuario;
- pool PHP-FPM con concurrencia muy superior a la capacidad SQL;
- aumento simultáneo de swap/OOM/load.

## Nota

`Max_used_connections` y
`Connection_errors_max_connections` son acumulativos desde el último
arranque de MariaDB. Un valor alto puede corresponder a un incidente ya
resuelto y no al estado actual.
