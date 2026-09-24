<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Incidentes 360 · Central ARPYNET</title>
    <x-operational-theme />

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/incident-360.css') }}?v=2.39.1"
    >
</head>
<body>
<div class="shell">
    <x-operational-page-header
        active="incidents"
        title="Incidentes 360"
        subtitle="SLA, prioridad, contexto y ciclo de vida en una sola vista."
    >
        <x-slot:actions>
            @if ($writableOrganizations->isNotEmpty())
                <a
                    class="admin-link"
                    href="#nuevo-incidente"
                >
                    + Nuevo incidente
                </a>
            @endif
        </x-slot:actions>
    </x-operational-page-header>

    @if(session('incident_success'))
        <div class="incident-flash">{{ session('incident_success') }}</div>
    @endif

    @if($errors->any())
        <div class="incident-flash error">{{ $errors->first() }}</div>
    @endif

    @php
        $incidentFocusTitle = match (true) {
            $summary['critical'] > 0 =>
                $summary['critical'].' incidentes críticos abiertos',
            (
                $summary['response_breached']
                + $summary['resolution_breached']
            ) > 0 =>
                (
                    $summary['response_breached']
                    + $summary['resolution_breached']
                ).' incumplimientos SLA requieren atención',
            $summary['open'] > 0 =>
                $summary['open'].' incidentes permanecen abiertos',
            default => 'Sin incidentes abiertos',
        };

        $incidentFocusTone = $summary['critical'] > 0
            || (
                $summary['response_breached']
                + $summary['resolution_breached']
            ) > 0
                ? 'danger'
                : ($summary['open'] > 0 ? 'warning' : 'success');

        $incidentFocusMeta =
            $summary['monitoring'].' en observación · '
            .$summary['resolved_7d'].' resueltos en 7 días';
    @endphp

    <x-operational-focus-banner
        :title="$incidentFocusTitle"
        :meta="$incidentFocusMeta"
        :tone="$incidentFocusTone"
    >
        <x-slot:actions>
            <a
                class="admin-link"
                href="{{ route('incident-360.index', array_filter([
                    'scope' => $selectedScope,
                    'focus' => 'attention',
                ])) }}"
            >
                Ver atención
            </a>
        </x-slot:actions>
    </x-operational-focus-banner>

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
<script src="{{ asset('central-assets/pages/incident-360.js') }}?v=2.39.1"></script>
</body>
</html>
