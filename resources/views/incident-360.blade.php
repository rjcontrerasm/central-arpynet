<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Incidentes 360 · Central ARPYNET</title>
    <x-operational-theme />

    <style>
        :root {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color-scheme: light dark;
        }
        * { box-sizing: border-box; }
        body { margin:0; background:#0b1020; color:#f8fafc; }
        a { color:inherit; text-decoration:none; }
        input, button { font:inherit; }
        .shell { width:min(100%, 1320px); margin:0 auto; padding:24px 16px 80px; }
        .topbar,.hero,.section-head,.item-head,.detail-head { display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .topbar { margin-bottom:24px; }
        .brand { font-weight:850; letter-spacing:-.03em; }
        .hero { align-items:end; margin-bottom:18px; }
        h1 { margin:0; font-size:clamp(30px,7vw,46px); line-height:.98; letter-spacing:-.05em; }
        h2,h3 { margin:0; }
        .subtitle,.meta { color:#94a3b8; font-size:12px; line-height:1.45; }
        .subtitle { margin-top:7px; font-size:13px; }
        .admin-link { padding:10px 13px; border-radius:11px; background:#2563eb; color:#fff; font-size:12px; font-weight:850; }
        .stats { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; margin-bottom:18px; }
        .stat,.panel,.item,.detail-card { border:1px solid #24304b; background:#11182b; }
        .stat { padding:13px; border-radius:15px; }
        .stat-value { font-size:27px; font-weight:850; letter-spacing:-.05em; }
        .stat-label { color:#94a3b8; font-size:11px; margin-top:5px; }
        .filters { display:grid; gap:9px; margin-bottom:18px; }
        .filter-label { color:#64748b; font-size:10px; font-weight:850; letter-spacing:.06em; text-transform:uppercase; }
        .scroll { display:flex; gap:7px; overflow-x:auto; padding-bottom:2px; }
        .chip { flex:0 0 auto; padding:7px 10px; border:1px solid #334155; border-radius:999px; background:#0f172a; color:#cbd5e1; font-size:12px; font-weight:750; }
        .chip.active { border-color:#60a5fa; background:#172554; color:#dbeafe; }
        .search { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; }
        .search input { min-height:40px; width:100%; padding:8px 10px; border:1px solid #334155; border-radius:10px; background:#11182b; color:#f8fafc; }
        .search button { border:0; border-radius:10px; padding:8px 13px; background:#2563eb; color:#fff; font-weight:850; cursor:pointer; }
        .layout { display:grid; gap:16px; }
        .panel { border-radius:17px; padding:14px; min-width:0; }
        .section-head { margin-bottom:12px; }
        .list { display:grid; gap:9px; }
        .item { display:block; padding:13px; border-radius:14px; }
        .item:hover,.item.selected { border-color:#3b82f6; background:#111c35; }
        .item-title { font-weight:820; line-height:1.3; }
        .pills { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
        .pill { display:inline-flex; align-items:center; gap:4px; padding:4px 8px; border-radius:999px; background:#1e293b; color:#cbd5e1; font-size:10px; font-weight:820; }
        .pill.critical,.pill.breached,.reason.critical { background:#450a0a; color:#fecaca; }
        .pill.high,.pill.overdue { background:#431407; color:#fed7aa; }
        .pill.medium,.pill.pending { background:#422006; color:#fde68a; }
        .pill.met,.pill.resolved { background:#052e16; color:#bbf7d0; }
        .reason { margin-top:7px; color:#fbbf24; font-size:11px; }
        .detail-head { align-items:start; margin-bottom:14px; }
        .detail-title { font-size:22px; font-weight:850; letter-spacing:-.03em; }
        .detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; }
        .detail-card { padding:12px; border-radius:13px; }
        .detail-label { color:#64748b; font-size:10px; font-weight:850; text-transform:uppercase; letter-spacing:.05em; }
        .detail-value { margin-top:5px; font-size:13px; font-weight:760; overflow-wrap:anywhere; }
        .full { grid-column:1/-1; }
        .timeline { display:grid; gap:8px; margin-top:10px; }
        .timeline-item { display:grid; grid-template-columns:16px minmax(0,1fr); gap:8px; align-items:start; }
        .dot { width:9px; height:9px; margin-top:4px; border-radius:999px; background:#60a5fa; }
        .empty { padding:28px 15px; border:1px dashed #334155; border-radius:15px; color:#94a3b8; text-align:center; font-size:13px; }
        .external { color:#93c5fd; text-decoration:underline; text-underline-offset:2px; }
        .incident-flash { margin:0 0 14px; padding:11px 13px; border:1px solid #93c5fd; border-radius:12px; background:var(--op-card,#fff); color:var(--op-text,#10213a); font-size:12px; }
        .incident-flash.error { border-color:#ef9a9a; }
        .incident-compose,.incident-editor { margin:0 0 16px; border:1px solid var(--op-border,#d2dde9); border-radius:16px; background:var(--op-card,#fff); overflow:hidden; }
        .incident-compose > summary,.incident-editor > summary { cursor:pointer; list-style:none; padding:12px 14px; font-size:12px; font-weight:850; color:var(--op-text,#10213a); }
        .incident-compose > summary::-webkit-details-marker,.incident-editor > summary::-webkit-details-marker { display:none; }
        .incident-form { padding:0 14px 14px; }
        .incident-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .incident-field { display:grid; gap:5px; min-width:0; }
        .incident-field.full { grid-column:1/-1; }
        .incident-field label { color:var(--op-muted,#5e6f85); font-size:10px; font-weight:850; text-transform:uppercase; letter-spacing:.04em; }
        .incident-field input,.incident-field select,.incident-field textarea { width:100%; min-height:40px; padding:8px 10px; border:1px solid var(--op-border-strong,#bdcad9); border-radius:10px; background:var(--op-card,#fff); color:inherit; font:inherit; font-size:12px; }
        .incident-field textarea { min-height:78px; resize:vertical; }
        .incident-actions { display:flex; align-items:center; gap:9px; flex-wrap:wrap; margin-top:12px; }
        .incident-save { min-height:38px; padding:8px 13px; border:0; border-radius:10px; background:var(--op-primary,#245fd7); color:#fff; font:inherit; font-size:12px; font-weight:850; cursor:pointer; }
        .incident-checkbox { display:flex; align-items:center; gap:8px; min-height:40px; font-size:12px; }
        .incident-checkbox input { width:auto; min-height:auto; }
        .admin-hint { color:var(--op-muted,#5e6f85); font-size:10px; }
        @media(max-width:720px){ .incident-form-grid{grid-template-columns:1fr}.incident-field.full{grid-column:auto} }
        @media (min-width:760px) { .stats { grid-template-columns:repeat(6,minmax(0,1fr)); } }
        @media (min-width:980px) { .layout { grid-template-columns:minmax(360px,.9fr) minmax(0,1.35fr); align-items:start; } .detail-panel { position:sticky; top:16px; } }
        @media (prefers-color-scheme:light) {
            body { background:#f8fafc; color:#0f172a; }
            .subtitle,.meta,.stat-label { color:#64748b; }
            .stat,.panel,.item,.detail-card { background:#fff; border-color:#e2e8f0; }
            .item:hover,.item.selected { background:#eff6ff; border-color:#60a5fa; }
            .chip { background:#fff; color:#475569; border-color:#cbd5e1; }
            .chip.active { background:#eff6ff; color:#1d4ed8; }
            .search input { background:#fff; color:#0f172a; border-color:#cbd5e1; }
            .pill { background:#f1f5f9; color:#475569; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="incidents" />
    </div>

    <section class="hero">
        <div>
            <h1>Incidentes 360</h1>
            <div class="subtitle">SLA, prioridad, contexto y ciclo de vida en una sola vista.</div>
        </div>
        @if ($writableOrganizations->isNotEmpty())
            <a class="admin-link" href="#nuevo-incidente">+ Nuevo incidente</a>
        @endif
    </section>

    @if(session('incident_success'))
        <div class="incident-flash">{{ session('incident_success') }}</div>
    @endif

    @if($errors->any())
        <div class="incident-flash error">{{ $errors->first() }}</div>
    @endif

    @if ($writableOrganizations->isNotEmpty())
        <details
            class="incident-compose"
            id="nuevo-incidente"
            @if(old('_incident_form') === 'create') open @endif
        >
            <summary>＋ Nuevo incidente sin salir de CENTRAL</summary>
            <form
                class="incident-form"
                method="POST"
                action="{{ route('incident-360.store') }}"
                data-incident-create
            >
                @csrf
                <input type="hidden" name="_incident_form" value="create">
                @php
                    $defaultOrganization = (int) old('organization_id', $selectedScope ?: auth()->user()->current_organization_id);
                @endphp
                <div class="incident-form-grid">
                    <div class="incident-field full">
                        <label>Título</label>
                        <input name="title" value="{{ old('title') }}" maxlength="255" required>
                    </div>
                    <div class="incident-field">
                        <label>Ámbito</label>
                        <select name="organization_id" data-incident-organization required>
                            @foreach($writableOrganizations as $organization)
                                <option value="{{ $organization->id }}" {{ $defaultOrganization == $organization->id ? 'selected' : '' }}>{{ $organization->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="incident-field">
                        <label>Responsable</label>
                        <select name="assigned_to" data-incident-scoped>
                            <option value="">Sin asignar</option>
                            @foreach($assigneeOptions as $organizationId => $options)
                                @foreach($options as $userId => $name)
                                    <option value="{{ $userId }}" data-org="{{ $organizationId }}" {{ old('assigned_to', auth()->id()) == $userId ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="incident-field">
                        <label>Severidad</label>
                        <select name="severity" required>
                            @foreach($severityOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('severity','medium') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="incident-field">
                        <label>Estado</label>
                        <select name="status" required>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('status','new') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="incident-field">
                        <label>Categoría</label>
                        <select name="category" required>
                            @foreach($categoryOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('category','availability') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="incident-field">
                        <label>Origen</label>
                        <select name="source" required>
                            @foreach($sourceOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('source','manual') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="incident-field">
                        <label>Cliente</label>
                        <select name="client_id" data-incident-scoped>
                            <option value="">Sin cliente</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" data-org="{{ $client->organization_id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="incident-field">
                        <label>Servicio / orden</label>
                        <select name="service_order_id" data-incident-scoped>
                            <option value="">Sin servicio</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}" data-org="{{ $service->organization_id }}" {{ old('service_order_id') == $service->id ? 'selected' : '' }}>{{ $service->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="incident-field">
                        <label>Proyecto</label>
                        <select name="project_id" data-incident-scoped>
                            <option value="">Sin proyecto</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" data-org="{{ $project->organization_id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="incident-field">
                        <label>Servicio afectado</label>
                        <input name="affected_service" value="{{ old('affected_service') }}" maxlength="255">
                    </div>
                    <div class="incident-field">
                        <label>Detectado</label>
                        <input type="datetime-local" name="detected_at" value="{{ old('detected_at', now()->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="incident-field">
                        <label>SLA respuesta</label>
                        <input type="datetime-local" name="response_due_at" value="{{ old('response_due_at') }}">
                    </div>
                    <div class="incident-field">
                        <label>SLA solución</label>
                        <input type="datetime-local" name="resolution_due_at" value="{{ old('resolution_due_at') }}">
                    </div>
                    <div class="incident-field full">
                        <label>Próxima acción</label>
                        <input name="next_action" value="{{ old('next_action') }}" maxlength="255">
                    </div>
                    <div class="incident-field">
                        <label>Próximo seguimiento</label>
                        <input type="datetime-local" name="next_action_at" value="{{ old('next_action_at') }}">
                    </div>
                    <div class="incident-field">
                        <label>ID externo</label>
                        <input name="external_id" value="{{ old('external_id') }}" maxlength="255">
                    </div>
                    <div class="incident-field full">
                        <label>Descripción</label>
                        <textarea name="description">{{ old('description') }}</textarea>
                    </div>
                    <div class="incident-field full">
                        <label>Evidencia externa</label>
                        <input type="url" name="external_url" value="{{ old('external_url') }}" maxlength="255" placeholder="https://...">
                    </div>
                    <label class="incident-checkbox full">
                        <input type="checkbox" name="is_private" value="1" {{ old('is_private') ? 'checked' : '' }}>
                        Incidente privado
                    </label>
                </div>
                <div class="incident-actions">
                    <button class="incident-save" type="submit">Crear incidente</button>
                    <span class="admin-hint">La operación queda dentro del front de CENTRAL.</span>
                </div>
            </form>
        </details>
    @endif

    <section class="stats">
        @foreach ([
            'Abiertos' => $summary['open'],
            'Críticos' => $summary['critical'],
            'SLA respuesta vencido' => $summary['response_breached'],
            'SLA solución vencido' => $summary['resolution_breached'],
            'En observación' => $summary['monitoring'],
            'Resueltos 7 días' => $summary['resolved_7d'],
        ] as $label => $value)
            <div class="stat">
                <div class="stat-value">{{ $value }}</div>
                <div class="stat-label">{{ $label }}</div>
            </div>
        @endforeach
    </section>

    @php
        $base = array_filter([
            'scope' => $selectedScope,
            'severity' => $selectedSeverity,
            'status' => $selectedStatus,
            'focus' => $focus,
            'q' => $search !== '' ? $search : null,
        ]);
    @endphp

    <section class="filters">
        <div class="filter-label">Ámbito</div>
        <div class="scroll">
            <a class="chip {{ $selectedScope ? '' : 'active' }}" href="{{ route('incident-360.index', array_filter(['severity'=>$selectedSeverity,'status'=>$selectedStatus,'focus'=>$focus,'q'=>$search !== '' ? $search : null])) }}">Todos</a>
            @foreach ($organizations as $organization)
                <a class="chip {{ $selectedScope === $organization->id ? 'active' : '' }}" href="{{ route('incident-360.index', array_merge($base, ['scope'=>$organization->id])) }}">{{ $organization->name }}</a>
            @endforeach
        </div>

        <div class="filter-label">Foco</div>
        <div class="scroll">
            @foreach (['open'=>'Abiertos','attention'=>'Requieren atención','resolved'=>'Resueltos','all'=>'Todos'] as $value => $label)
                <a class="chip {{ $focus === $value ? 'active' : '' }}" href="{{ route('incident-360.index', array_merge($base, ['focus'=>$value])) }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="filter-label">Severidad</div>
        <div class="scroll">
            <a class="chip {{ $selectedSeverity ? '' : 'active' }}" href="{{ route('incident-360.index', array_filter(array_merge($base, ['severity'=>null]))) }}">Todas</a>
            @foreach ($severityOptions as $value => $label)
                <a class="chip {{ $selectedSeverity === $value ? 'active' : '' }}" href="{{ route('incident-360.index', array_merge($base, ['severity'=>$value])) }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="filter-label">Estado</div>
        <div class="scroll">
            <a class="chip {{ $selectedStatus ? '' : 'active' }}" href="{{ route('incident-360.index', array_filter(array_merge($base, ['status'=>null]))) }}">Todos</a>
            @foreach ($statusOptions as $value => $label)
                <a class="chip {{ $selectedStatus === $value ? 'active' : '' }}" href="{{ route('incident-360.index', array_merge($base, ['status'=>$value])) }}">{{ $label }}</a>
            @endforeach
        </div>

        <form class="search" method="GET" action="{{ route('incident-360.index') }}">
            @foreach (array_filter(['scope'=>$selectedScope,'severity'=>$selectedSeverity,'status'=>$selectedStatus,'focus'=>$focus]) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <input type="search" name="q" value="{{ $search }}" placeholder="Buscar incidente, cliente, servicio o ID externo…">
            <button type="submit">Buscar</button>
        </form>
    </section>

    <div class="layout">
        <section class="panel">
            <div class="section-head">
                <h2>Incidentes</h2>
                <span class="meta">{{ $rows->count() }} mostrados</span>
            </div>

            <div class="list">
                @forelse ($rows as $row)
                    @php($incident = $row['incident'])
                    @php($state = $row['state'])
                    <a class="item {{ $selected && $selected['incident']->id === $incident->id ? 'selected' : '' }}" href="{{ route('incident-360.index', array_merge($base, ['incident'=>$incident->id])) }}">
                        <div class="item-head">
                            <div>
                                <div class="item-title">{{ $incident->title }}</div>
                                <div class="meta">{{ $incident->organization?->name ?? 'Sin ámbito' }} · {{ $incident->client?->name ?? 'Sin cliente' }}</div>
                            </div>
                            <span class="pill {{ $incident->severity }}">{{ $state['severity_label'] }}</span>
                        </div>
                        <div class="pills">
                            <span class="pill">{{ $state['status_label'] }}</span>
                            <span class="pill {{ $state['response_sla']['status'] }}">Respuesta: {{ $state['response_sla']['label'] }}</span>
                            <span class="pill {{ $state['resolution_sla']['status'] }}">Solución: {{ $state['resolution_sla']['label'] }}</span>
                            <span class="pill">{{ $state['open_duration'] }}</span>
                        </div>
                        @if ($state['reasons']->isNotEmpty())
                            <div class="reason">{{ $state['reasons']->implode(' · ') }}</div>
                        @endif
                    </a>
                @empty
                    <div class="empty">No hay incidentes para los filtros seleccionados.</div>
                @endforelse
            </div>
        </section>

        <section class="panel detail-panel">
            @if ($selected)
                @php($incident = $selected['incident'])
                @php($state = $selected['state'])
                <div class="detail-head">
                    <div>
                        <div class="detail-title">{{ $incident->title }}</div>
                        <div class="meta">{{ $incident->organization?->name ?? 'Sin ámbito' }} · detectado {{ $incident->detected_at?->format('d/m/Y H:i') ?? '—' }}</div>
                    </div>
                    <span class="pill {{ $incident->severity }}">{{ $state['severity_label'] }} · prioridad {{ $state['rank'] }}</span>
                </div>

                @if(auth()->user()->canWriteToOrganization((int)$incident->organization_id))
                    <details
                        class="incident-editor"
                        @if(old('_incident_form') === 'edit') open @endif
                    >
                        <summary>Editar incidente</summary>
                        <form class="incident-form" method="POST" action="{{ route('incident-360.update', $incident) }}">
                            @csrf
                            <input type="hidden" name="_incident_form" value="edit">
                            <input type="hidden" name="organization_id" value="{{ $incident->organization_id }}">
                            <div class="incident-form-grid">
                                <div class="incident-field full"><label>Título</label><input name="title" value="{{ old('title',$incident->title) }}" maxlength="255" required></div>
                                <div class="incident-field"><label>Severidad</label><select name="severity" required>@foreach($severityOptions as $value=>$label)<option value="{{ $value }}" {{ old('severity',$incident->severity) === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                                <div class="incident-field"><label>Estado</label><select name="status" required>@foreach($statusOptions as $value=>$label)<option value="{{ $value }}" {{ old('status',$incident->status) === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                                <div class="incident-field"><label>Categoría</label><select name="category" required>@foreach($categoryOptions as $value=>$label)<option value="{{ $value }}" {{ old('category',$incident->category) === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                                <div class="incident-field"><label>Origen</label><select name="source" required>@foreach($sourceOptions as $value=>$label)<option value="{{ $value }}" {{ old('source',$incident->source) === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                                <div class="incident-field"><label>Responsable</label><select name="assigned_to"><option value="">Sin asignar</option>@foreach($assigneeOptions->get((int)$incident->organization_id,[]) as $userId=>$name)<option value="{{ $userId }}" {{ old('assigned_to',$incident->assigned_to) == $userId ? 'selected' : '' }}>{{ $name }}</option>@endforeach</select></div>
                                <div class="incident-field"><label>Cliente</label><select name="client_id"><option value="">Sin cliente</option>@foreach($clients->where('organization_id',$incident->organization_id) as $client)<option value="{{ $client->id }}" {{ old('client_id',$incident->client_id) == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>@endforeach</select></div>
                                <div class="incident-field"><label>Servicio / orden</label><select name="service_order_id"><option value="">Sin servicio</option>@foreach($services->where('organization_id',$incident->organization_id) as $service)<option value="{{ $service->id }}" {{ old('service_order_id',$incident->service_order_id) == $service->id ? 'selected' : '' }}>{{ $service->title }}</option>@endforeach</select></div>
                                <div class="incident-field"><label>Proyecto</label><select name="project_id"><option value="">Sin proyecto</option>@foreach($projects->where('organization_id',$incident->organization_id) as $project)<option value="{{ $project->id }}" {{ old('project_id',$incident->project_id) == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>@endforeach</select></div>
                                <div class="incident-field"><label>Servicio afectado</label><input name="affected_service" value="{{ old('affected_service',$incident->affected_service) }}" maxlength="255"></div>
                                <div class="incident-field"><label>Detectado</label><input type="datetime-local" name="detected_at" value="{{ old('detected_at',$incident->detected_at?->format('Y-m-d\TH:i')) }}"></div>
                                <div class="incident-field"><label>SLA respuesta</label><input type="datetime-local" name="response_due_at" value="{{ old('response_due_at',$incident->response_due_at?->format('Y-m-d\TH:i')) }}"></div>
                                <div class="incident-field"><label>SLA solución</label><input type="datetime-local" name="resolution_due_at" value="{{ old('resolution_due_at',$incident->resolution_due_at?->format('Y-m-d\TH:i')) }}"></div>
                                <div class="incident-field full"><label>Próxima acción</label><input name="next_action" value="{{ old('next_action',$incident->next_action) }}" maxlength="255"></div>
                                <div class="incident-field"><label>Próximo seguimiento</label><input type="datetime-local" name="next_action_at" value="{{ old('next_action_at',$incident->next_action_at?->format('Y-m-d\TH:i')) }}"></div>
                                <div class="incident-field"><label>ID externo</label><input name="external_id" value="{{ old('external_id',$incident->external_id) }}" maxlength="255"></div>
                                <div class="incident-field full"><label>Descripción</label><textarea name="description">{{ old('description',$incident->description) }}</textarea></div>
                                <div class="incident-field full"><label>Causa raíz</label><textarea name="root_cause">{{ old('root_cause',$incident->root_cause) }}</textarea></div>
                                <div class="incident-field full"><label>Resumen de solución</label><textarea name="resolution_summary">{{ old('resolution_summary',$incident->resolution_summary) }}</textarea></div>
                                <div class="incident-field full"><label>Evidencia externa</label><input type="url" name="external_url" value="{{ old('external_url',$incident->external_url) }}" maxlength="255"></div>
                                <div class="incident-field full"><label>Notas</label><textarea name="notes">{{ old('notes',$incident->notes) }}</textarea></div>
                                <label class="incident-checkbox full"><input type="checkbox" name="is_private" value="1" {{ old('is_private',$incident->is_private) ? 'checked' : '' }}> Incidente privado</label>
                            </div>
                            <div class="incident-actions"><button class="incident-save" type="submit">Guardar cambios</button></div>
                        </form>
                    </details>
                @endif

                <div class="detail-grid">
                    <div class="detail-card">
                        <div class="detail-label">Estado</div>
                        <div class="detail-value">{{ $state['status_label'] }}</div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-label">Tiempo abierto</div>
                        <div class="detail-value">{{ $state['open_duration'] }}</div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-label">SLA respuesta</div>
                        <div class="detail-value">{{ $state['response_sla']['label'] }}@if($state['response_sla']['late_minutes'] > 0) · +{{ $state['response_sla']['late_minutes'] }} min @endif</div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-label">SLA solución</div>
                        <div class="detail-value">{{ $state['resolution_sla']['label'] }}@if($state['resolution_sla']['late_minutes'] > 0) · +{{ $state['resolution_sla']['late_minutes'] }} min @endif</div>
                    </div>
                    <div class="detail-card full">
                        <div class="detail-label">Próxima acción</div>
                        <div class="detail-value">{{ $incident->next_action ?: 'Sin definir' }} @if($state['next_action']['at']) · {{ $state['next_action']['at']->format('d/m/Y H:i') }} @endif</div>
                        <div class="meta">{{ $state['next_action']['label'] }}</div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-label">Cliente</div>
                        <div class="detail-value">{{ $incident->client?->name ?? '—' }}</div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-label">Responsable</div>
                        <div class="detail-value">{{ $incident->assignee?->name ?? 'Sin asignar' }}</div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-label">Servicio / orden</div>
                        <div class="detail-value">{{ $incident->serviceOrder?->title ?? $incident->affected_service ?? '—' }}</div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-label">Proyecto</div>
                        <div class="detail-value">{{ $incident->project?->name ?? '—' }}</div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-label">Categoría / origen</div>
                        <div class="detail-value">{{ $state['category_label'] }} · {{ $state['source_label'] }}</div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-label">ID externo</div>
                        <div class="detail-value">{{ $incident->external_id ?: '—' }}</div>
                    </div>
                    @if ($incident->description)
                        <div class="detail-card full">
                            <div class="detail-label">Descripción</div>
                            <div class="detail-value">{{ $incident->description }}</div>
                        </div>
                    @endif
                    @if ($incident->root_cause)
                        <div class="detail-card full">
                            <div class="detail-label">Causa raíz</div>
                            <div class="detail-value">{{ $incident->root_cause }}</div>
                        </div>
                    @endif
                    @if ($incident->resolution_summary)
                        <div class="detail-card full">
                            <div class="detail-label">Resolución</div>
                            <div class="detail-value">{{ $incident->resolution_summary }}</div>
                        </div>
                    @endif
                    @if ($incident->external_url)
                        <div class="detail-card full">
                            <div class="detail-label">Evidencia externa</div>
                            <div class="detail-value"><a class="external" href="{{ $incident->external_url }}" target="_blank" rel="noopener noreferrer">Abrir evidencia ↗</a></div>
                        </div>
                    @endif
                    <div class="detail-card full">
                        <div class="detail-label">Línea de tiempo</div>
                        <div class="timeline">
                            @forelse ($state['timeline'] as $event)
                                <div class="timeline-item">
                                    <span class="dot"></span>
                                    <div><strong>{{ $event['label'] }}</strong><div class="meta">{{ $event['at']->format('d/m/Y H:i') }}</div></div>
                                </div>
                            @empty
                                <div class="meta">Sin hitos registrados.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @else
                <div class="empty">Selecciona un incidente para ver su contexto 360.</div>
            @endif
        </section>
    </div>
</div>
<script>
document.querySelectorAll('[data-incident-create]').forEach((form) => {
    const organization = form.querySelector('[data-incident-organization]');
    if (!organization) return;

    const sync = () => {
        const organizationId = organization.value;
        form.querySelectorAll('[data-incident-scoped]').forEach((select) => {
            Array.from(select.options).forEach((option) => {
                const optionOrganization = option.dataset.org;
                const visible = !optionOrganization || optionOrganization === organizationId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible && option.selected) select.value = '';
            });
        });
    };

    organization.addEventListener('change', sync);
    sync();
});
</script>
</body>
</html>
