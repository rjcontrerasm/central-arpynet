<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark"><title>{{ $rule?'Editar recurrencia':'Nueva recurrencia' }} · Central ARPYNET</title>
<link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/recurring-task-front-form.css') }}?v=2.39.1"
    ></head>
<body>
<div class="shell"><header class="topbar"><a class="brand" href="{{ route('daily-ops.show') }}">Central ARPYNET</a><x-operational-nav active="daily" /></header>
@if(session('recurring_task_success'))<div class="success">{{ session('recurring_task_success') }}</div>@endif
@if($errors->any())<div class="errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="hero"><h1>{{ $rule?'Editar recurrencia':'Nueva recurrencia' }}</h1><div class="subtitle">Define el ciclo; CENTRAL generará las tareas operativas correspondientes.</div></section>
@if(!$canWrite)<div class="readonly">Tienes acceso de solo lectura a esta recurrencia.</div>@endif
@php $organizationId=(int)old('organization_id',$rule?->organization_id ?? $defaultOrganizationId);$selectedProject=(int)old('project_id',$rule?->project_id ?? 0);$selectedAssignee=(int)old('assigned_to',$rule?->assigned_to ?? auth()->id()); @endphp
<section class="panel"><form method="post" action="{{ $rule?route('recurring-task-front.update',$rule):route('recurring-task-front.store') }}">@csrf
<div class="section-title">Tarea</div><div class="grid">
<div class="field"><label for="organization_id">Empresa / ámbito</label><select id="organization_id" name="organization_id" {{ $rule||!$canWrite?'disabled':'' }} required>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected($organizationId===(int)$organization->id)>{{ $organization->name }}</option>@endforeach</select>@if($rule)<input type="hidden" name="organization_id" value="{{ $rule->organization_id }}"><div class="help">El ámbito de una recurrencia existente no se cambia desde esta ficha.</div>@endif</div>
<div class="field"><label for="project_id">Proyecto opcional</label><select id="project_id" name="project_id" {{ !$canWrite?'disabled':'' }}><option value="">Sin proyecto</option>@foreach($projectOptions as $id=>$project)<option value="{{ $id }}" data-organization="{{ $project['organization_id'] }}" @selected($selectedProject===(int)$id)>{{ $project['name'] }} — {{ $project['organization_name'] }}</option>@endforeach</select></div>
<div class="field span-2"><label for="title">Título</label><input id="title" name="title" maxlength="255" required value="{{ old('title',$rule?->title) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field span-2"><label for="next_action">Próxima acción</label><input id="next_action" name="next_action" maxlength="255" value="{{ old('next_action',$rule?->next_action) }}" {{ !$canWrite?'disabled':'' }}><div class="help">Paso concreto que aparecerá en cada tarea generada.</div></div>
<div class="field span-2"><label for="description">Descripción</label><textarea id="description" name="description" {{ !$canWrite?'disabled':'' }}>{{ old('description',$rule?->description) }}</textarea></div>
<div class="field"><label for="assigned_to">Responsable</label><select id="assigned_to" name="assigned_to" {{ !$canWrite?'disabled':'' }}><option value="">Usuario actual</option>@foreach($assigneeOptions as $id=>$assignee)<option value="{{ $id }}" data-organizations="{{ implode(',',$assignee['organization_ids']) }}" @selected($selectedAssignee===(int)$id)>{{ $assignee['name'] }}</option>@endforeach</select></div>
</div>
<div class="section-title">Recurrencia</div><div class="grid">
<div class="field"><label for="frequency">Frecuencia</label><select id="frequency" name="frequency" {{ !$canWrite?'disabled':'' }} required>@foreach(\App\Models\RecurringTaskRule::frequencyOptions() as $value=>$label)<option value="{{ $value }}" @selected(old('frequency',$rule?->frequency ?? 'weekly')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="anchor_date">Primera fecha</label><input id="anchor_date" type="date" name="anchor_date" required value="{{ old('anchor_date',$rule?->anchor_date?->format('Y-m-d') ?? now()->toDateString()) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="end_date">Finaliza opcionalmente</label><input id="end_date" type="date" name="end_date" value="{{ old('end_date',$rule?->end_date?->format('Y-m-d')) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="create_days_before">Crear con anticipación</label><input id="create_days_before" type="number" min="0" max="90" name="create_days_before" required value="{{ old('create_days_before',$rule?->create_days_before ?? 0) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="due_time">Hora de vencimiento</label><input id="due_time" name="due_time" maxlength="5" pattern="(?:[01][0-9]|2[0-3]):[0-5][0-9]" required value="{{ old('due_time',$rule?->due_time ?? '17:00') }}" {{ !$canWrite?'disabled':'' }}></div>
<div><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$rule?->is_active ?? true)) {{ !$canWrite?'disabled':'' }}> Regla activa</label></div>
</div>
<div class="section-title">Prioridad</div><div class="grid">
<div class="field"><label for="urgency">Urgencia</label><select id="urgency" name="urgency" {{ !$canWrite?'disabled':'' }} required>@foreach(\App\Models\Task::urgencyOptions() as $value=>$label)<option value="{{ $value }}" @selected(old('urgency',$rule?->urgency ?? 'normal')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="impact">Impacto</label><select id="impact" name="impact" {{ !$canWrite?'disabled':'' }} required>@foreach(\App\Models\Task::impactOptions() as $value=>$label)<option value="{{ $value }}" @selected(old('impact',$rule?->impact ?? 'normal')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="span-2"><label class="check"><input type="checkbox" name="is_private" value="1" @checked(old('is_private',$rule?->is_private ?? false)) {{ !$canWrite?'disabled':'' }}> Privada</label></div>
</div>
<div class="actions">@if($canWrite)<button class="primary" type="submit" data-busy-label="Guardando…">{{ $rule?'Guardar cambios':'Crear recurrencia' }}</button>@endif<a class="secondary" href="{{ route('recurring-task-front.index') }}">Volver a recurrentes</a><a class="secondary" href="{{ route('daily-ops.show') }}">Volver a Mi día</a></div>
</form></section></div>
<script src="{{ asset('central-assets/pages/recurring-task-front-form.js') }}?v=2.39.1"></script>
<x-operational-theme /><x-operational-interactions /></body></html>
