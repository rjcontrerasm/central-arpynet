<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Proyectos · Central ARPYNET</title>
<x-operational-theme />
<link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/projects-ops.css') }}?v=2.39.1"
    >
</head>
<body>
@php
$createScope = $selectedScope && in_array($selectedScope, $writableOrganizationIds, true)
    ? $selectedScope
    : ($writableOrganizationIds[0] ?? null);
@endphp
<div class="shell">
<x-operational-page-header
    active="projects"
    title="Proyectos"
    subtitle="Avance, bloqueos, siguiente acción y señales de estancamiento."
>
    <x-slot:actions>
        @if($createScope)
            <a
                class="primary-link"
                href="{{ route('project-front.create', ['scope' => $createScope]) }}"
            >
                Nuevo proyecto
            </a>
        @endif
    </x-slot:actions>
</x-operational-page-header>

@if(session('project_action_success'))
<div class="success">{{ session('project_action_success') }}</div>
@endif

@php
$projectFocusTitle = match (true) {
    $summary['critical'] > 0 =>
        $summary['critical'].' proyectos críticos requieren decisión',
    $summary['attention'] > 0 =>
        $summary['attention'].' proyectos requieren revisión',
    default => 'Sin proyectos en alerta inmediata',
};
$projectFocusTone = $summary['critical'] > 0
    ? 'danger'
    : ($summary['attention'] > 0 ? 'warning' : 'success');
$projectFocusMeta =
    $summary['stagnant'].' estancados · '
    .$summary['no_next_action'].' sin próxima acción';
@endphp

<x-operational-focus-banner
    :title="$projectFocusTitle"
    :meta="$projectFocusMeta"
    :tone="$projectFocusTone"
>
    <x-slot:actions>
        <a
            class="primary-link"
            href="{{ route('project-ops.show', array_filter([
                'scope' => $selectedScope,
                'focus' => 'attention',
                'q' => $search !== '' ? $search : null,
            ])) }}"
        >
            Ver atención
        </a>
    </x-slot:actions>
</x-operational-focus-banner>

<form class="filters" method="get" action="{{ route('project-ops.show') }}">
<select name="scope" aria-label="Ámbito">
<option value="">Todos los ámbitos</option>
@foreach($organizations as $organization)
<option value="{{ $organization->id }}" @selected($selectedScope === $organization->id)>{{ $organization->name }}</option>
@endforeach
</select>
<select name="focus" aria-label="Foco">
<option value="attention" @selected($focus === 'attention')>Requieren atención</option>
<option value="stagnant" @selected($focus === 'stagnant')>Estancados</option>
<option value="no_next_action" @selected($focus === 'no_next_action')>Sin siguiente acción</option>
<option value="active" @selected($focus === 'active')>En ejecución</option>
<option value="all" @selected($focus === 'all')>Todos</option>
</select>
<input name="q" value="{{ $search }}" maxlength="120" placeholder="Buscar proyecto, acción o bloqueo">
<button type="submit">Filtrar</button>
</form>

<section class="stats">
<div class="stat"><div class="stat-value">{{ $summary['total'] }}</div><div class="stat-label">Abiertos</div></div>
<div class="stat"><div class="stat-value">{{ $summary['critical'] }}</div><div class="stat-label">Críticos</div></div>
<div class="stat"><div class="stat-value">{{ $summary['attention'] }}</div><div class="stat-label">A revisar</div></div>
<div class="stat"><div class="stat-value">{{ $summary['stagnant'] }}</div><div class="stat-label">Estancados</div></div>
<div class="stat"><div class="stat-value">{{ $summary['no_next_action'] }}</div><div class="stat-label">Sin próxima acción</div></div>
</section>

<section class="list">
@forelse($rows as $row)
@php
$project=$row['project'];
$signal=$row['signal'];
$progress=max(0,min(100,(int)$project->progress_percent));
$canWriteProject=in_array((int)$project->organization_id,$writableOrganizationIds,true);
@endphp
<article class="card {{ $signal['level'] }}">
<div class="card-head">
<div><div class="title">{{ $project->name }}</div><div class="org">{{ $project->organization?->name ?? 'Sin ámbito' }}</div></div>
<div class="badges">
<span class="badge {{ $signal['level'] }}">{{ $signal['level_label'] }}</span>
<span class="badge">{{ $statusOptions[$project->status] ?? $project->status }}</span>
<span class="badge">{{ $typeOptions[$project->type] ?? $project->type }}</span>
</div>
</div>

<div class="progress" title="Avance {{ $progress }}%"><span style="width: {{ $progress }}%"></span></div>

<div class="meta-row">
<span>Avance {{ $progress }}%</span><span>·</span><span>{{ $project->stagnation_label }}</span>
@if($project->target_date)<span>·</span><span>Objetivo {{ $project->target_date->format('d/m/Y') }}</span>@endif
@if($project->horizon)<span>·</span><span>{{ $horizonOptions[$project->horizon] ?? $project->horizon }}</span>@endif
@if($project->budget !== null)<span>·</span><span>{{ $project->currency }} {{ number_format((float)$project->budget,2) }}</span>@endif
</div>

<div class="next"><strong>Siguiente acción:</strong> {{ filled($project->next_action) ? $project->next_action : 'No definida.' }}</div>
@if(filled($project->blockers))<div class="blockers"><strong>Bloqueos:</strong> {{ $project->blockers }}</div>@endif

@if(!empty($signal['reasons']))
<div class="reasons">@foreach($signal['reasons'] as $reason)<span class="reason">{{ $reason }}</span>@endforeach</div>
@endif

@if($canWriteProject)
<details class="quick">
<summary>Acciones rápidas</summary>
<div class="quick-grid">

<div class="quick-panel">
<div class="quick-title">Actualizar proyecto</div>
<form class="quick-form" method="post" action="{{ route('project-ops.update', $project) }}">
@csrf
<input type="hidden" name="scope" value="{{ $selectedScope }}">
<input type="hidden" name="focus" value="{{ $focus }}">
<input type="hidden" name="q" value="{{ $search }}">
<label class="form-label" for="status-{{ $project->id }}">Estado</label>
<select id="status-{{ $project->id }}" name="status" required>
@foreach($statusOptions as $value => $label)
<option value="{{ $value }}" @selected($project->status === $value)>{{ $label }}</option>
@endforeach
</select>
<label class="form-label" for="next-{{ $project->id }}">Siguiente acción</label>
<input id="next-{{ $project->id }}" name="next_action" maxlength="255" value="{{ $project->next_action }}" placeholder="Define la siguiente acción">
<label class="form-label" for="blockers-{{ $project->id }}">Bloqueos</label>
<textarea id="blockers-{{ $project->id }}" name="blockers" maxlength="5000" placeholder="Sin bloqueos">{{ $project->blockers }}</textarea>
<button type="submit">Guardar cambios</button>
</form>
</div>

<div class="quick-panel">
<div class="quick-title">Crear tarea vinculada</div>
<form class="quick-form" method="post" action="{{ route('project-ops.task.store', $project) }}">
@csrf
<input type="hidden" name="scope" value="{{ $selectedScope }}">
<input type="hidden" name="focus" value="{{ $focus }}">
<input type="hidden" name="q" value="{{ $search }}">
<label class="form-label" for="task-title-{{ $project->id }}">Tarea</label>
<input id="task-title-{{ $project->id }}" name="title" maxlength="255" required placeholder="Nueva tarea del proyecto">
<div class="form-row">
<div>
<label class="form-label" for="due-{{ $project->id }}">Vencimiento</label>
<input id="due-{{ $project->id }}" type="date" name="due_date">
</div>
<div>
<label class="form-label" for="urgency-{{ $project->id }}">Urgencia</label>
<select id="urgency-{{ $project->id }}" name="urgency" required>
<option value="normal">Normal</option>
<option value="high">Alta</option>
<option value="critical">Crítica</option>
<option value="low">Baja</option>
</select>
</div>
</div>
<button class="secondary" type="submit">Crear tarea</button>
</form>
</div>

</div>
</details>
@endif

<div class="card-foot">
<span class="muted">{{ $project->tasks_count }} tareas · {{ $project->completed_tasks_count }} completadas</span>
<a class="card-link" href="{{ route('project-front.edit', $project) }}">{{ $canWriteProject ? 'Editar proyecto' : 'Ver proyecto' }} →</a>
</div>
</article>
@empty
<div class="empty">No hay proyectos para los filtros seleccionados.</div>
@endforelse
</section>
</div>
</body>
</html>
