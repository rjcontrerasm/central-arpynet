# Central ARPYNET — Roadmap maestro

Este documento fija la numeración vigente a partir del cierre de 2.15.

| Hito | Objetivo | Estado |
| --- | --- | --- |
| 2.15 | Usuarios y Organizaciones | Completado |
| 2.16 | Colaboración | En construcción |
| 2.17 | Preparación humana de propuestas | Completado |
| 2.18 | Ampliar acciones sugeribles | Completado |
| 2.19 | Priorización ejecutiva transversal | Completado |
| 2.20 | Plan automático del día | Completado |
| 2.21 | Revisión diaria/semanal asistida | Completado |
| 2.22 | Automatizaciones inteligentes | Completado |
| 2.23 | Automatización cross-module | Completado |
| 2.24 | Finanzas ejecutivas avanzadas | Pendiente |
| 2.25 | Health Score de clientes/servicios | Pendiente |
| 2.26 | Incident 360 | Pendiente |
| 2.27 | WhatsApp como interfaz CENTRAL | Pendiente |
| 2.28 | Calendar / contexto externo | Pendiente |
| 2.29 | Motor de decisión | Pendiente |
| 2.30 | Delegación controlada | Pendiente |
| 2.31 | Autonomía nivel 1 | Pendiente |
| 2.32 | Autonomía nivel 2 | Pendiente |
| 2.33 | Autonomía nivel 3 | Pendiente |
| 2.34 | Hardening y recuperación | Pendiente |
| 2.35 | UX final desktop/mobile | Pendiente |
| 2.36 | CENTRAL Copilot | Meta final |

## Regla de avance

Cada hito debe pasar por desarrollo, pruebas, CI, revisión, integración a `main`, preflight cPanel, despliegue controlado y smoke test antes de considerarse cerrado.

## Nota de re-baseline

La base existente ya contiene implementaciones relacionadas con propuestas de Jarvis, priorización ejecutiva, plan diario, revisiones asistidas y automatizaciones. Por ello, los hitos 2.17–2.23 deben auditarse contra el código actual antes de crear funcionalidad duplicada. El criterio será cerrar brechas y formalizar cada capacidad existente, no reescribirla sin necesidad.


## Cierre de auditoría 2.17–2.23 — 2026-09-27

La auditoría contra el código y las pruebas existentes confirmó que estos
hitos ya estaban implementados sobre la base actual y no requerían una
reescritura:

- **2.17** — Preparación humana de propuestas: propuestas pendientes,
  deduplicación, control de stale state y ausencia de mutación antes de
  aprobación.
- **2.18** — Acciones sugeribles ampliadas: cambios de estado, siguiente
  acción, limpieza de bloqueos, creación propuesta de tareas y cambios de
  etapa de servicios.
- **2.19** — Priorización ejecutiva transversal: ranking entre ámbitos,
  presión global y prioridades consolidadas.
- **2.20** — Plan automático del día: construcción determinística del plan
  desde prioridades ejecutivas, sin mutaciones.
- **2.21** — Revisión diaria/semanal asistida: cierre diario guiado,
  persistencia de progreso y revisión semanal.
- **2.22** — Automatizaciones inteligentes: catálogo cerrado, preview,
  ejecución controlada, confirmaciones, deduplicación y scheduler.
- **2.23** — Automatización cross-module: creación controlada de tareas
  desde proyectos, servicios y vencimientos, con confirmación explícita,
  protección contra stale state y undo.

La continuación funcional debe partir de **2.24**, evitando duplicar estas
capacidades ya presentes.

## Re-baseline operativo — 2026-09-24

La línea posterior a 2.36 cerró deuda técnica y de confiabilidad antes de
continuar con nuevos módulos.

| Hito | Objetivo | Estado |
| --- | --- | --- |
| 2.37.0 | Hardening de conversión y recurrencias | Completado |
| 2.37.1 | Observabilidad de scheduler y recurrencias | Completado |
| 2.37.2 | Resiliencia de cache ante saturación MariaDB | Completado |
| 2.37.3 | Arquitectura de navegación operacional | Completado |
| 2.37.4 | Mi Día como centro operativo | Completado |
| 2.37.5 | Cabecera operacional reutilizable | Completado |
| 2.37.6 | Búsqueda global | Completado |
| 2.37.7 | Jerarquía action-first | Completado |
| 2.37.8 | Observabilidad completa en FRONT | Completado |
| 2.38.0 | Browser E2E con Playwright | Completado |
| 2.38.1 | CI de compatibilidad MariaDB 11.4 | Completado |
| 2.38.2 | Hardening de administración y multiempresa | Completado |
| 2.38.3 | Observabilidad de conexiones MariaDB | Completado |
| 2.38.4 | Cierre de segunda auditoría y E2E críticos | En producción |
| 2.39 | Consolidación de assets FRONT y CSP más estricto | Completado |

### Cierre de 2.39

La consolidación de assets del FRONT quedó completada sin introducir un
pipeline de construcción adicional en cPanel. Los assets operativos se sirven
como archivos estáticos versionados automáticamente y el FRONT aplica
`script-src 'self'` y `style-src 'self'`.

Filament (`/admin`) conserva temporalmente la política CSP compatible
anterior porque su stack y la vista personalizada de Integraciones todavía
usan recursos inline. Ese endurecimiento queda como deuda separada y no
bloquea el cierre del FRONT operacional.
