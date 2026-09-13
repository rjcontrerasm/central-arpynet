<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Incidentes 360 · Central ARPYNET</title>

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
        <a class="admin-link" href="{{ url('/admin/incidentes') }}">+ Crear / administrar</a>
    </section>

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
</body>
</html>
