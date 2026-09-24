<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Jarvis · Central ARPYNET</title>
<x-operational-theme />
<link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/agent-proposals.css') }}?v=2.39.2"
    >
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
<section class="daily-plan" aria-label="Plan Diario Jarvis">
<div class="panel-head">
<div>
<h2>Plan Diario Jarvis</h2>
<div class="muted">{{ $dailyPlan['date_label'] }} · orden sugerido de jornada · solo lectura.</div>
</div>
<span class="chip">{{ $dailyPlan['total_items'] }} asuntos</span>
</div>

<div class="intel-summary">{{ $dailyPlan['summary'] }}</div>

@if($dailyPlan['focus_organizations'])
<div class="daily-focus" aria-label="Ámbitos de foco">
@foreach($dailyPlan['focus_organizations'] as $focus)
<span>{{ $focus['name'] }} · {{ $focus['pressure_score'] }}</span>
@endforeach
</div>
@endif

<div class="daily-plan-grid">
@foreach($dailyPlan['sections'] as $sectionKey => $section)
<div class="daily-section">
<h3>{{ $section['label'] }}</h3>
<div class="daily-section-hint">{{ $section['hint'] }}</div>

<div class="daily-items">
@forelse($section['items'] as $planItem)
<div class="daily-item">
<div class="daily-item-top">
<div>
<div class="daily-scope">{{ $planItem['organization'] }} · {{ $planItem['type_label'] }}</div>
<a class="daily-title" href="{{ $planItem['url'] }}">{{ $planItem['title'] }}</a>
</div>
<span class="daily-sequence">{{ $planItem['sequence'] }}</span>
</div>
<div class="daily-reason">{{ $planItem['why'] }}</div>
<div class="daily-move"><strong>Siguiente:</strong> {{ $planItem['suggested_move'] }}</div>
</div>
@empty
<div class="muted">Sin asuntos en este bloque.</div>
@endforelse
</div>
</div>
@endforeach
</div>

<div class="guard">
<strong>Límite activo:</strong>
el Plan Diario Jarvis ordena señales; no reserva horas, no modifica fechas, no mueve tareas y no crea propuestas automáticamente.
</div>
</section>
<section class="review-assist" aria-label="Revisión diaria asistida por Jarvis">
<div class="panel-head">
<div>
<h2>Cierre asistido Jarvis</h2>
<div class="muted">{{ $dailyReviewAssistant['date_label'] }} · cabos sueltos destacados antes de cerrar.</div>
</div>
<span class="chip">
{{ $dailyReviewAssistant['review']['reviewed_count'] }}/{{ $dailyReviewAssistant['review']['total_steps'] }} revisados
</span>
</div>

<div class="intel-summary">{{ $dailyReviewAssistant['summary'] }}</div>

<div class="review-progress" aria-label="Progreso de revisión diaria">
@foreach($dailyReviewAssistant['review']['steps'] as $reviewStep)
<span class="review-step {{ $reviewStep['reviewed'] ? 'done' : '' }}">
{{ $reviewStep['reviewed'] ? '✓' : '○' }} {{ $reviewStep['label'] }}
</span>
@endforeach
</div>

<div class="review-grid">
@foreach($dailyReviewAssistant['sections'] as $reviewSection)
<div class="review-column">
<div class="review-column-head">
<h3>{{ $reviewSection['label'] }}</h3>
<span class="review-count">{{ $reviewSection['count'] }}</span>
</div>

<div class="review-items">
@forelse($reviewSection['items'] as $reviewItem)
<div class="review-item">
<div class="review-item-scope">{{ $reviewItem['organization'] }} · {{ $reviewItem['type_label'] }}</div>
<a class="review-item-title" href="{{ $reviewItem['url'] }}">{{ $reviewItem['title'] }}</a>
<div class="review-item-meta">{{ $reviewItem['why'] }}</div>
<div class="review-item-meta"><strong>Siguiente:</strong> {{ $reviewItem['suggested_move'] }}</div>
</div>
@empty
<div class="muted">Sin cabos sueltos destacados.</div>
@endforelse
</div>
</div>
@endforeach
</div>

<div class="review-actions">
<a class="review-link" href="{{ $dailyReviewAssistant['review']['url'] }}">Abrir revisión diaria →</a>
<span class="muted">
Jarvis no marca pasos ni completa la revisión por ti.
</span>
</div>

<div class="guard">
<strong>Límite activo:</strong>
esta sección es una lectura previa al cierre. No crea sesiones de revisión, no marca pasos, no modifica entidades y no ejecuta acciones.
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

@if($priority['type'] === 'project')
<details class="proposal-more" data-expanded-actions="2.18">
<summary>Más acciones de proyecto</summary>

<form class="proposal-prepare" method="POST" action="{{ route('agent-proposals.prepare') }}">
@csrf
<input type="hidden" name="subject_type" value="project">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="project.status.set">
<div class="proposal-field">
<label for="jarvis-project-status-{{ $priority['id'] }}">Nuevo estado</label>
<select class="proposal-input" id="jarvis-project-status-{{ $priority['id'] }}" name="project_status" required>
@foreach(\App\Models\Project::statusOptions() as $statusKey => $statusLabel)
<option value="{{ $statusKey }}">{{ $statusLabel }}</option>
@endforeach
</select>
</div>
<button class="prepare-button" type="submit" data-confirm="¿Preparar el cambio de estado? El proyecto no se modificará todavía." data-busy-label="Preparando…">Preparar estado</button>
</form>

<form class="proposal-prepare" method="POST" action="{{ route('agent-proposals.prepare') }}">
@csrf
<input type="hidden" name="subject_type" value="project">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="project.blockers.clear">
<div class="proposal-field">
<label>Bloqueos</label>
<div class="prepare-note">Prepara la limpieza de los bloqueos registrados. Si ya no existen, CENTRAL no creará una propuesta vacía.</div>
</div>
<button class="prepare-button" type="submit" data-confirm="¿Preparar la limpieza de bloqueos? El proyecto no se modificará todavía." data-busy-label="Preparando…">Preparar limpieza</button>
</form>

<form class="proposal-prepare service" method="POST" action="{{ route('agent-proposals.prepare') }}">
@csrf
<input type="hidden" name="subject_type" value="project">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="project.task.create">
<div class="proposal-field">
<label for="jarvis-project-task-{{ $priority['id'] }}">Nueva tarea vinculada</label>
<input class="proposal-input" id="jarvis-project-task-{{ $priority['id'] }}" name="project_task_title" type="text" maxlength="255" required placeholder="Ej. Validar entregable con el cliente">
</div>
<div class="proposal-field">
<label for="jarvis-project-urgency-{{ $priority['id'] }}">Urgencia</label>
<select class="proposal-input" id="jarvis-project-urgency-{{ $priority['id'] }}" name="project_task_urgency">
<option value="normal">Normal</option>
<option value="high">Alta</option>
<option value="critical">Crítica</option>
<option value="low">Baja</option>
</select>
</div>
<div class="proposal-field">
<label for="jarvis-project-due-{{ $priority['id'] }}">Vence (opcional)</label>
<input class="proposal-input" id="jarvis-project-due-{{ $priority['id'] }}" name="project_task_due_date" type="date">
</div>
<button class="prepare-button" type="submit" data-confirm="¿Preparar la creación de esta tarea? No se creará hasta aprobar y confirmar la propuesta." data-busy-label="Preparando…">Preparar tarea</button>
</form>
<div class="prepare-note">Todas estas acciones siguen el flujo propuesta → aprobación → segunda confirmación → undo.</div>
</details>
@elseif($priority['type'] === 'service_order')
<details class="proposal-more" data-expanded-actions="2.18">
<summary>Más acciones de servicio</summary>
<form class="proposal-prepare" method="POST" action="{{ route('agent-proposals.prepare') }}">
@csrf
<input type="hidden" name="subject_type" value="service_order">
<input type="hidden" name="subject_id" value="{{ $priority['id'] }}">
<input type="hidden" name="action" value="service_order.stage.set">
<div class="proposal-field">
<label for="jarvis-service-stage-{{ $priority['id'] }}">Nueva etapa</label>
<select class="proposal-input" id="jarvis-service-stage-{{ $priority['id'] }}" name="service_stage" required>
@foreach(\App\Models\ServiceOrder::stageOptions() as $stageKey => $stageLabel)
<option value="{{ $stageKey }}">{{ $stageLabel }}</option>
@endforeach
</select>
</div>
<button class="prepare-button" type="submit" data-confirm="¿Preparar el cambio de etapa? El servicio no se modificará todavía." data-busy-label="Preparando…">Preparar etapa</button>
</form>
<div class="prepare-note">La etapa solo cambiará después de aprobación humana y segunda confirmación.</div>
</details>
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
<a class="quick-link" href="{{ route('daily-review.show') }}">Revisión diaria →</a>
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
