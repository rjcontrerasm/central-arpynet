<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Jarvis · Central ARPYNET</title>
<x-operational-theme />
<style>
.jarvis{width:min(1180px,calc(100% - 28px));margin:0 auto;padding:24px 0 70px}
.head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}
h1{margin:0 0 5px;font-size:clamp(32px,7vw,48px);letter-spacing:-.05em}
h2{margin:0;font-size:16px;letter-spacing:-.02em}
.sub,.meta,.empty,.muted{color:var(--op-muted,#94a3b8)}.sub{font-size:13px}.muted{font-size:11px}
.notice{margin:0 0 14px;padding:11px 13px;border:1px solid #166534;border-radius:13px;background:#052e16;color:#bbf7d0;font-size:12px;font-weight:750}
.safety{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.safety span{padding:5px 8px;border-radius:999px;background:#111827;border:1px solid var(--op-border,#334155);font-size:10px;font-weight:850}
.safety .safe{color:#86efac}.safety .locked{color:#bfdbfe}
.metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px;margin-bottom:14px}
.metric{display:block;padding:13px;border:1px solid var(--op-border,#334155);border-radius:15px;background:var(--op-card,#0f172a);color:inherit;text-decoration:none}
.metric strong{display:block;font-size:25px;line-height:1}.metric span{display:block;margin-top:6px;font-size:10px;font-weight:850;color:var(--op-muted,#94a3b8)}
.metric.attention strong{color:#fde68a}.metric.ready strong{color:#86efac}.metric.done strong{color:#7dd3fc}.metric.stale strong{color:#d4d4d8}
.executive{margin-bottom:12px;padding:15px;border:1px solid var(--op-border,#334155);border-radius:17px;background:var(--op-card,#0f172a)}.executive-grid{display:grid;grid-template-columns:180px minmax(0,1fr);gap:16px}.executive-rank{display:grid;gap:7px;margin-top:11px}.executive-org{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:9px 10px;border-radius:11px;background:rgba(148,163,184,.06);text-decoration:none;color:inherit}.executive-org strong{font-size:11px}.executive-org-meta{margin-top:3px;font-size:9px;color:var(--op-muted,#94a3b8)}.executive-score{font-size:16px;font-weight:900}.executive-top{display:grid;gap:7px;margin-top:11px}.executive-priority{padding:9px 10px;border-radius:11px;background:rgba(148,163,184,.06)}.executive-priority-head{display:flex;justify-content:space-between;gap:10px}.executive-scope{font-size:9px;font-weight:900;color:#93c5fd}.executive-title{margin-top:3px;font-size:11px;font-weight:900}.executive-reason{margin-top:3px;font-size:9px;color:var(--op-muted,#94a3b8);line-height:1.4}
.intel{margin-bottom:12px;padding:15px;border:1px solid var(--op-border,#334155);border-radius:17px;background:var(--op-card,#0f172a)}
.intel-grid{display:grid;grid-template-columns:180px minmax(0,1fr);gap:16px;align-items:start}.pressure{padding:14px;border-radius:14px;background:rgba(148,163,184,.07)}.pressure-score{font-size:38px;font-weight:900;line-height:1;letter-spacing:-.05em}.pressure-label{margin-top:5px;font-size:10px;font-weight:900;text-transform:uppercase}.pressure.high .pressure-score{color:#fca5a5}.pressure.elevated .pressure-score{color:#fdba74}.pressure.moderate .pressure-score{color:#fde68a}.pressure.controlled .pressure-score{color:#86efac}.intel-summary{font-size:12px;line-height:1.55}.driver-list{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.driver{padding:5px 7px;border-radius:999px;background:rgba(148,163,184,.08);font-size:9px;font-weight:800}.priority-intel{display:grid;gap:7px;margin-top:12px}.priority-row{padding:10px;border-radius:12px;background:rgba(148,163,184,.06)}.priority-top{display:flex;justify-content:space-between;gap:10px}.priority-name{font-size:11px;font-weight:900}.priority-rank{font-size:10px;font-weight:900;color:var(--op-muted,#94a3b8)}.priority-why,.priority-move{margin-top:4px;font-size:10px;line-height:1.45;color:var(--op-muted,#94a3b8)}.priority-move strong{color:inherit}.proposal-compatible{display:inline-block;margin-top:6px;padding:4px 6px;border-radius:999px;background:#172554;color:#bfdbfe;font-size:9px;font-weight:850}.proposal-prepare{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px;margin-top:8px;align-items:end}.proposal-prepare.service{grid-template-columns:minmax(0,1fr) 170px auto}.proposal-field{display:grid;gap:4px}.proposal-field label{font-size:9px;font-weight:800;color:var(--op-muted,#94a3b8)}.proposal-input{width:100%;min-height:36px;padding:7px 9px;border:1px solid var(--op-border,#334155);border-radius:9px;background:transparent;color:inherit;font:inherit;font-size:11px}.proposal-input:focus{outline:2px solid rgba(96,165,250,.35);border-color:#60a5fa}.prepare-button{min-height:36px;padding:7px 10px;border:1px solid #1d4ed8;border-radius:9px;background:#172554;color:#dbeafe;font:inherit;font-size:10px;font-weight:900;cursor:pointer}.prepare-note{margin-top:5px;font-size:9px;color:var(--op-muted,#94a3b8)}
.center-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(280px,.85fr);gap:12px;margin-bottom:18px}
.panel{padding:14px;border:1px solid var(--op-border,#334155);border-radius:16px;background:var(--op-card,#0f172a)}
.panel-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:11px}
.context-counts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}
.context-count{padding:9px;border-radius:11px;background:rgba(148,163,184,.07)}
.context-count strong{display:block;font-size:18px}.context-count span{font-size:9px;color:var(--op-muted,#94a3b8);font-weight:800}
.attention-list{display:grid;gap:7px;margin-top:10px}.attention-item{display:block;padding:9px 10px;border-radius:11px;background:rgba(148,163,184,.07);color:inherit;text-decoration:none}.attention-title{font-size:11px;font-weight:850}.attention-meta{margin-top:3px;font-size:9px;color:var(--op-muted,#94a3b8)}.level-critical{border-left:3px solid #ef4444}.level-high{border-left:3px solid #f59e0b}.level-medium{border-left:3px solid #60a5fa}
.quick-links{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.quick-link{padding:10px;border:1px solid var(--op-border,#334155);border-radius:11px;color:inherit;text-decoration:none;font-size:10px;font-weight:850}.quick-link:hover{border-color:#60a5fa}
.filters{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 13px}.chip{padding:7px 10px;border:1px solid var(--op-border,#334155);border-radius:999px;color:inherit;text-decoration:none;font-size:11px;font-weight:800}.chip.active{border-color:#60a5fa;background:#172554;color:#dbeafe}
.section-title{display:flex;justify-content:space-between;gap:10px;align-items:end;margin:20px 0 10px}
.list{display:grid;gap:10px}.card{padding:14px;border:1px solid var(--op-border,#334155);border-radius:16px;background:var(--op-card,#0f172a)}
.row{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.title{font-weight:850;line-height:1.3}.meta{margin-top:3px;font-size:11px;line-height:1.45}
.badges{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.badge{padding:4px 7px;border-radius:999px;background:#1e293b;color:#cbd5e1;font-size:10px;font-weight:850}.badge.pending{background:#422006;color:#fde68a}.badge.approved{background:#052e16;color:#bbf7d0}.badge.rejected{background:#450a0a;color:#fecaca}.badge.executed{background:#082f49;color:#bae6fd}.badge.stale{background:#3f3f46;color:#e4e4e7}
.reason{margin-top:9px;padding:8px 10px;border-left:3px solid #60a5fa;border-radius:8px;background:rgba(37,99,235,.08);font-size:12px}
.changes{margin-top:9px;display:grid;gap:5px}.change{padding:7px 9px;border-radius:9px;background:rgba(148,163,184,.08);font-size:11px;overflow-wrap:anywhere}
.actions{display:flex;gap:8px;margin-top:11px}.actions form{margin:0}.button{min-height:36px;padding:7px 11px;border-radius:9px;font:inherit;font-size:11px;font-weight:850;cursor:pointer}.approve{border:1px solid #166534;background:#052e16;color:#bbf7d0}.reject{border:1px solid #7f1d1d;background:#450a0a;color:#fecaca}.execute{border:1px solid #1d4ed8;background:#172554;color:#dbeafe}
.guard{margin-top:18px;padding:12px;border:1px dashed var(--op-border,#334155);border-radius:14px;color:var(--op-muted,#94a3b8);font-size:11px}.empty{padding:20px;border:1px dashed var(--op-border,#334155);border-radius:15px;text-align:center;font-size:12px}
@media(max-width:900px){.metrics{grid-template-columns:repeat(3,minmax(0,1fr))}.executive-grid,.intel-grid,.center-grid{grid-template-columns:1fr}}
@media(max-width:620px){.head,.row,.panel-head{display:grid}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.context-counts{grid-template-columns:repeat(2,minmax(0,1fr))}.quick-links,.proposal-prepare,.proposal-prepare.service{grid-template-columns:1fr}}
@media(prefers-color-scheme:light){.chip.active{background:#eff6ff;color:#1d4ed8}.badge{background:#f1f5f9;color:#475569}.badge.pending{background:#fffbeb;color:#a16207}.badge.approved{background:#f0fdf4;color:#166534}.badge.rejected{background:#fef2f2;color:#b91c1c}.badge.executed{background:#f0f9ff;color:#0369a1}.badge.stale{background:#f4f4f5;color:#52525b}.notice{background:#f0fdf4;color:#166534}.reason{background:#eff6ff}.approve{background:#f0fdf4;color:#166534}.reject{background:#fef2f2;color:#b91c1c}.execute{background:#eff6ff;color:#1d4ed8}.safety span{background:#f8fafc}.safety .safe{color:#166534}.safety .locked{color:#1d4ed8}}
</style>
</head>
<body>
<div class="jarvis">
<header class="head">
<div>
<h1>Jarvis</h1>
<div class="sub">Centro de control para propuestas y contexto operativo.</div>
<div class="safety">
<span class="safe">✓ Confirmación humana</span>
<span class="locked">Autonomía: deshabilitada</span>
<span class="locked">Red externa: deshabilitada</span>
<span class="locked">Contrato {{ $contract['contract'] }}</span>
</div>
</div>
<x-operational-nav active="agent" />
</header>

@if(session('agent_proposal_message'))
<div class="notice">{{ session('agent_proposal_message') }}</div>
@endif

<section class="metrics" aria-label="Resumen Jarvis">
<a class="metric attention" href="{{ route('agent-proposals.index',array_filter(['scope'=>$selectedScope,'status'=>'pending'])) }}"><strong>{{ $summary['pending'] }}</strong><span>REQUIEREN DECISIÓN</span></a>
<a class="metric ready" href="{{ route('agent-proposals.index',array_filter(['scope'=>$selectedScope,'status'=>'approved'])) }}"><strong>{{ $summary['approved'] }}</strong><span>LISTAS PARA EJECUTAR</span></a>
<a class="metric done" href="{{ route('agent-proposals.index',array_filter(['scope'=>$selectedScope,'status'=>'executed'])) }}"><strong>{{ $summary['executed'] }}</strong><span>EJECUTADAS</span></a>
<a class="metric stale" href="{{ route('agent-proposals.index',array_filter(['scope'=>$selectedScope,'status'=>'stale'])) }}"><strong>{{ $summary['stale'] }}</strong><span>DESACTUALIZADAS</span></a>
<div class="metric"><strong>{{ $summary['executed_recent'] }}</strong><span>EJECUTADAS · 7 DÍAS</span><span class="muted">{{ $summary['undone'] }} deshechas</span></div>
</section>

<section class="executive" aria-label="Prioridad ejecutiva global">
<div class="panel-head">
<div>
<h2>Prioridad ejecutiva global</h2>
<div class="muted">Comparación transversal de todos tus ámbitos activos · solo lectura.</div>
</div>
<span class="chip">{{ $executivePrioritization['organization_count'] }} ámbitos</span>
</div>

<div class="executive-grid">
<div class="pressure {{ $executivePrioritization['pressure_level'] }}">
<div class="pressure-score">{{ $executivePrioritization['pressure_score'] }}</div>
<div class="pressure-label">Presión global {{ $executivePrioritization['pressure_label'] }}</div>
<div class="muted">
{{ $executivePrioritization['critical_total'] }} críticos ·
{{ $executivePrioritization['incidents_total'] }} incidentes
</div>
</div>

<div>
<div class="intel-summary">{{ $executivePrioritization['summary'] }}</div>

<div class="executive-rank" aria-label="Ranking de ámbitos">
@forelse($executivePrioritization['organizations'] as $organizationPriority)
<a
    class="executive-org"
    href="{{ route('agent-proposals.index', ['scope' => $organizationPriority['id'], 'status' => $selectedStatus]) }}"
>
<div>
<strong>{{ $organizationPriority['name'] }}</strong>
<div class="executive-org-meta">
{{ $organizationPriority['critical'] }} críticos ·
{{ $organizationPriority['attention'] }} atención ·
{{ $organizationPriority['incidents_open'] }} incidentes
@if($organizationPriority['top_priority'])
· Principal: {{ $organizationPriority['top_priority']['title'] }}
@endif
</div>
</div>
<div class="executive-score">{{ $organizationPriority['pressure_score'] }}</div>
</a>
@empty
<div class="empty">No hay ámbitos activos para comparar.</div>
@endforelse
</div>
</div>
</div>

@if($executivePrioritization['top_priorities'])
<div class="section-title">
<div>
<h2>Top transversal</h2>
<div class="muted">Los asuntos con mayor rank operativo entre todos los ámbitos.</div>
</div>
</div>

<div class="executive-top">
@foreach($executivePrioritization['top_priorities'] as $globalPriority)
<div class="executive-priority">
<div class="executive-priority-head">
<div>
<div class="executive-scope">{{ $globalPriority['organization'] }}</div>
<a class="executive-title" href="{{ $globalPriority['url'] }}">{{ $globalPriority['title'] }}</a>
</div>
<span class="priority-rank">{{ $globalPriority['level_label'] }} · {{ $globalPriority['rank'] }}</span>
</div>
<div class="executive-reason">{{ $globalPriority['why'] }}</div>
</div>
@endforeach
</div>
@endif

<div class="guard">
<strong>Límite activo:</strong>
la priorización ejecutiva global solo compara señales ya autorizadas. No crea propuestas, no modifica entidades y no ejecuta acciones.
</div>
</section>
<section class="intel" aria-label="Lectura operativa de Jarvis">
<div class="panel-head">
<div>
<h2>Lectura Jarvis</h2>
<div class="muted">Interpretación determinística del contexto 360 · solo lectura.</div>
</div>
<span class="chip">Sin red · sin escritura</span>
</div>

<div class="intel-grid">
<div class="pressure {{ $operationalIntelligence['pressure_level'] }}">
<div class="pressure-score">{{ $operationalIntelligence['pressure_score'] }}</div>
<div class="pressure-label">Presión {{ $operationalIntelligence['pressure_label'] }}</div>
<div class="muted">
{{ $operationalIntelligence['distribution']['critical'] }} críticos ·
{{ $operationalIntelligence['distribution']['attention'] }} atención ·
{{ $operationalIntelligence['distribution']['watch'] }} vigilar
</div>
</div>

<div>
<div class="intel-summary">
{{ $operationalIntelligence['summary'] }}
</div>

@if($operationalIntelligence['drivers'])
<div class="driver-list" aria-label="Principales causas">
@foreach($operationalIntelligence['drivers'] as $driver)
<span class="driver">{{ $driver['label'] }} · {{ $driver['count'] }}</span>
@endforeach
</div>
@endif

<div class="priority-intel">
@forelse($operationalIntelligence['priorities'] as $priority)
<div class="priority-row">
<div class="priority-top">
<a class="priority-name" href="{{ $priority['url'] }}">{{ $priority['title'] }}</a>
<span class="priority-rank">{{ $priority['level_label'] }} · {{ $priority['rank'] }}</span>
</div>
<div class="priority-why"><strong>Por qué:</strong> {{ $priority['why'] }}</div>
<div class="priority-move"><strong>Siguiente movimiento:</strong> {{ $priority['suggested_move'] }}</div>
@if($priority['proposal_action'])
<div class="proposal-compatible">
Jarvis podría preparar: {{ $priority['proposal_label'] }}
</div>

@if($priority['proposal_action'] === 'project.next_action.set')
<form
    class="proposal-prepare"
    method="POST"
    action="{{ route('agent-proposals.prepare') }}"
>
@csrf
<input type="hidden" name="subject_type" value="project">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="project.next_action.set">
<div class="proposal-field">
<label for="jarvis-project-next-{{ $priority['id'] }}">Siguiente acción concreta</label>
<input
    class="proposal-input"
    id="jarvis-project-next-{{ $priority['id'] }}"
    name="next_action"
    type="text"
    maxlength="255"
    required
    placeholder="Ej. Coordinar reunión de arranque con el cliente"
>
</div>
<button
    class="prepare-button"
    type="submit"
    data-confirm="¿Preparar esta propuesta? Solo se agregará a Pendientes; no se ejecutará ningún cambio."
    data-busy-label="Preparando…"
>
Preparar propuesta
</button>
</form>
<div class="prepare-note">La acción que escribas será el cambio propuesto; el proyecto no se modificará todavía.</div>
@elseif($priority['proposal_action'] === 'service_order.next_action.set')
<form
    class="proposal-prepare service"
    method="POST"
    action="{{ route('agent-proposals.prepare') }}"
>
@csrf
<input type="hidden" name="subject_type" value="service_order">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="service_order.next_action.set">
<div class="proposal-field">
<label for="jarvis-service-next-{{ $priority['id'] }}">Siguiente acción concreta</label>
<input
    class="proposal-input"
    id="jarvis-service-next-{{ $priority['id'] }}"
    name="next_action"
    type="text"
    maxlength="255"
    required
    placeholder="Ej. Entregar informe técnico al cliente"
>
</div>
<div class="proposal-field">
<label for="jarvis-service-at-{{ $priority['id'] }}">Fecha / hora opcional</label>
<input
    class="proposal-input"
    id="jarvis-service-at-{{ $priority['id'] }}"
    name="next_action_at"
    type="datetime-local"
>
</div>
<button
    class="prepare-button"
    type="submit"
    data-confirm="¿Preparar esta propuesta? Solo se agregará a Pendientes; no se ejecutará ningún cambio."
    data-busy-label="Preparando…"
>
Preparar propuesta
</button>
</form>
<div class="prepare-note">La acción y fecha se guardarán únicamente en la propuesta pendiente.</div>
@endif
@endif

@if($priority['type'] === 'task')
<div class="proposal-compatible">
Acciones de tarea disponibles con preparación humana
</div>
<form
    class="proposal-prepare"
    method="POST"
    action="{{ route('agent-proposals.prepare') }}"
>
@csrf
<input type="hidden" name="subject_type" value="task">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<div class="proposal-field">
<label for="jarvis-task-action-{{ $priority['id'] }}">Acción propuesta</label>
<select
    class="proposal-input"
    id="jarvis-task-action-{{ $priority['id'] }}"
    name="action"
    required
>
@foreach($contract['action_catalog']['task'] as $actionKey => $definition)
<option value="{{ $actionKey }}">{{ $definition['label'] }}</option>
@endforeach
</select>
</div>
<button
    class="prepare-button"
    type="submit"
    data-confirm="¿Preparar esta acción de tarea? Solo se agregará a Pendientes; la tarea no cambiará todavía."
    data-busy-label="Preparando…"
>
Preparar acción
</button>
</form>
<div class="prepare-note">
La tarea permanecerá intacta hasta que apruebes la propuesta y confirmes su ejecución por segunda vez.
</div>
@endif
</div>
@empty
<div class="empty">No hay prioridades operativas que interpretar en este momento.</div>
@endforelse
</div>
</div>
</div>

<div class="guard">
<strong>Límite activo:</strong>
esta lectura no crea propuestas por sí sola, no modifica entidades y no ejecuta acciones. Solo “Preparar propuesta”, pulsado por una persona, puede registrar una propuesta pendiente; después seguirá pasando por aprobación humana y segunda confirmación.
</div>
</section>
<section class="center-grid">
<div class="panel">
<div class="panel-head">
<div>
<h2>Contexto operativo</h2>
<div class="muted">
@if($focusOrganization)
{{ $focusOrganization->name }}
@else
Sin ámbito disponible
@endif
</div>
</div>
@if($operationalContext)
<a class="chip" href="{{ $operationalContext['url'] }}">Abrir vista 360 →</a>
@endif
</div>

@if($operationalContext)
<div class="context-counts">
<div class="context-count"><strong>{{ $operationalContext['counts']['tasks_open'] }}</strong><span>TAREAS ABIERTAS</span></div>
<div class="context-count"><strong>{{ $operationalContext['counts']['projects_open'] }}</strong><span>PROYECTOS ABIERTOS</span></div>
<div class="context-count"><strong>{{ $operationalContext['counts']['services_open'] }}</strong><span>SERVICIOS ABIERTOS</span></div>
<div class="context-count"><strong>{{ $operationalContext['counts']['obligations_pending'] }}</strong><span>VENCIMIENTOS</span></div>
<div class="context-count"><strong>{{ $operationalContext['counts']['incidents_open'] }}</strong><span>INCIDENTES</span></div>
<div class="context-count"><strong>{{ $operationalContext['counts']['clients'] }}</strong><span>CLIENTES ACTIVOS</span></div>
</div>

<div class="attention-list">
@forelse(array_slice($operationalContext['attention'],0,6) as $item)
<a class="attention-item level-{{ $item['level'] }}" href="{{ $item['url'] }}">
<div class="attention-title">{{ $item['title'] }}</div>
<div class="attention-meta">
{{ $item['level_label'] }} · {{ $item['type'] }}
@if($item['date_label'])
· {{ $item['date_label'] }}
@endif
</div>
</a>
@empty
<div class="empty">No hay focos operativos relevantes en este ámbito.</div>
@endforelse
</div>
@endif
</div>

<div class="panel">
<div class="panel-head">
<div>
<h2>Accesos rápidos</h2>
<div class="muted">Mismo ámbito operativo, sin acciones automáticas.</div>
</div>
</div>
<div class="quick-links">
<a class="quick-link" href="{{ route('daily-ops.show',$focusOrganization ? ['scope'=>$focusOrganization->id] : []) }}">Mi Día →</a>
<a class="quick-link" href="{{ route('project-ops.show',$focusOrganization ? ['scope'=>$focusOrganization->id,'focus'=>'all'] : []) }}">Proyectos →</a>
<a class="quick-link" href="{{ route('service-orders-ops.show',$focusOrganization ? ['scope'=>$focusOrganization->id] : []) }}">Servicios →</a>
<a class="quick-link" href="{{ route('operational-360.show',$focusOrganization ? ['scope'=>$focusOrganization->id] : []) }}">Vista 360 →</a>
<a class="quick-link" href="{{ route('audit-history.index') }}">Historial →</a>
<a class="quick-link" href="{{ route('automation-center.index') }}">Automatizaciones →</a>
</div>

<div class="guard">
<strong>Estado de seguridad:</strong>
Jarvis no ejecuta cambios por sí solo. Puede leer contexto, crear propuestas y registrar previews. La ejecución requiere aprobación y una segunda confirmación humana. El agente no puede ejecutar por sí solo ni usar red externa.
</div>
</div>
</section>

<nav class="filters" aria-label="Filtrar propuestas">
@foreach($statusLabels as $status => $label)
<a
    class="chip {{ $selectedStatus === $status ? 'active' : '' }}"
    href="{{ route(
        'agent-proposals.index',
        array_merge(
            $selectedScope ? ['scope' => $selectedScope] : [],
            ['status' => $status],
        ),
    ) }}"
>
{{ $label }}
@if($status!=='all')
· {{ (int)($counts[$status] ?? 0) }}
@endif
</a>
@endforeach
</nav>

<nav class="filters" aria-label="Filtrar por ámbito">
<a class="chip {{ $selectedScope ? '' : 'active' }}" href="{{ route('agent-proposals.index',['status'=>$selectedStatus]) }}">Todos los ámbitos</a>
@foreach($organizations as $organization)
<a class="chip {{ $selectedScope===$organization->id ? 'active' : '' }}" href="{{ route('agent-proposals.index',['scope'=>$organization->id,'status'=>$selectedStatus]) }}">{{ $organization->name }}</a>
@endforeach
</nav>

<div class="section-title">
<div>
<h2>Cola de propuestas</h2>
<div class="muted">La lista conserva los controles de aprobación, ejecución y protección stale.</div>
</div>
</div>

<div class="list">
@forelse($proposals as $proposal)
<article class="card">
<div class="row">
<div>
<div class="title">{{ $proposal->action_label }}</div>
<div class="meta">{{ $proposal->subject_title }} · {{ $proposal->organization?->name ?? 'Sin ámbito' }}</div>
</div>
<span class="badge {{ $proposal->status }}">{{ \App\Models\AgentActionProposal::statusOptions()[$proposal->status] ?? $proposal->status }}</span>
</div>

<div class="badges">
<span class="badge">{{ $proposal->subject_type }}</span>
<span class="badge">{{ $proposal->risk }}</span>
<span class="badge">{{ $proposal->effect }}</span>
</div>

@if($proposal->rationale)
<div class="reason"><strong>Motivo:</strong> {{ $proposal->rationale }}</div>
@endif

<div class="changes">
@foreach($proposal->proposed_changes as $key=>$value)
<div class="change">
<strong>{{ $key }}:</strong>
@if(is_array($value))
{{ json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}
@elseif(is_bool($value))
{{ $value ? 'sí' : 'no' }}
@elseif(is_null($value))
null
@else
{{ $value }}
@endif
</div>
@endforeach
</div>

<div class="meta">
Propuesta #{{ $proposal->id }} · {{ $proposal->created_at?->format('d/m/Y H:i') }}
@if($proposal->reviewed_at)
· revisada {{ $proposal->reviewed_at->format('d/m/Y H:i') }}
@endif
@if($proposal->executed_at)
· ejecutada {{ $proposal->executed_at->format('d/m/Y H:i') }}
@endif
@if($proposal->undoAction && $proposal->undoAction->undone_at)
· <strong>deshecha</strong>
@endif
</div>

@if($proposal->status==='pending')
<div class="actions">
<form method="POST" action="{{ route('agent-proposals.approve',$proposal) }}">
@csrf
<button class="button approve" type="submit" data-confirm="¿Aprobar esta propuesta? Aún no se ejecutará el cambio.">Aprobar</button>
</form>
<form method="POST" action="{{ route('agent-proposals.reject',$proposal) }}">
@csrf
<button class="button reject" type="submit" data-confirm="¿Rechazar esta propuesta?">Rechazar</button>
</form>
</div>
@elseif($proposal->status==='approved')
<div class="actions">
<form method="POST" action="{{ route('agent-proposals.execute',$proposal) }}">
@csrf
<input type="hidden" name="confirm_execution" value="1">
<button class="button execute" type="submit" data-confirm="Segunda confirmación: ¿ejecutar exactamente estos cambios ahora?" data-busy-label="Ejecutando…">Ejecutar cambio</button>
</form>
</div>
@endif
</article>
@empty
<div class="empty">No hay propuestas en este filtro.</div>
@endforelse
</div>
</div>
<x-operational-interactions />
</body>
</html>
