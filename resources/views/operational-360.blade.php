<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Vista 360 · Central ARPYNET</title>
<x-operational-theme />
<style>
.o360{width:min(1200px,calc(100% - 28px));margin:0 auto;padding:22px 0 60px}
.o360-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}
.o360-head h1{margin:0 0 5px;font-size:clamp(29px,6vw,44px);letter-spacing:-.045em}
.muted,.meta,.empty{color:var(--op-muted,#94a3b8)}
.meta{font-size:12px;line-height:1.45}.scopes{display:flex;gap:7px;overflow-x:auto;padding:2px 0 14px}
.chip{flex:0 0 auto;padding:7px 10px;border:1px solid var(--op-border,#334155);border-radius:999px;color:inherit;text-decoration:none;font-size:11px;font-weight:800}
.chip.active{border-color:#3b82f6;background:#172554;color:#dbeafe}
.kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-bottom:20px}
.kpi,.card,.finance{border:1px solid var(--op-border,#334155);background:var(--op-card,#0f172a);border-radius:15px}
.kpi{padding:13px}.kpi strong{display:block;font-size:25px}.kpi span{font-size:10px;color:var(--op-muted,#94a3b8)}
.grid{display:grid;gap:16px}.section{margin-top:18px}.section-head{display:flex;justify-content:space-between;gap:12px;align-items:baseline;margin-bottom:9px}
.section-head h2{margin:0;font-size:17px}.section-link{color:#93c5fd;text-decoration:none;font-size:11px;font-weight:800}
.list{display:grid;gap:8px}.card{display:block;padding:12px;color:inherit;text-decoration:none}.card-title{font-weight:820;line-height:1.3}
.row{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.pills{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px}
.pill{display:inline-flex;padding:4px 7px;border-radius:999px;background:#1e293b;color:#cbd5e1;font-size:10px;font-weight:800}
.pill.critical{background:#450a0a;color:#fecaca}.pill.attention{background:#431407;color:#fed7aa}.pill.watch{background:#422006;color:#fde68a}
.reason{margin-top:6px;color:#fbbf24;font-size:11px}.links{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}.links a{color:#93c5fd;font-size:10px;font-weight:800;text-decoration:none}
.finance-groups{display:grid;gap:12px}.finance-title{margin-bottom:7px;color:#93c5fd;font-size:11px;font-weight:850}
.finance-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.finance{padding:11px}.finance strong{display:block;font-size:17px}.finance span{font-size:9px;color:var(--op-muted,#94a3b8)}
.empty{padding:15px;border:1px dashed var(--op-border,#334155);border-radius:14px;text-align:center;font-size:11px}
@media(min-width:760px){.kpis{grid-template-columns:repeat(6,minmax(0,1fr))}.grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:620px){.o360-head{display:grid}.row{display:grid}}
@media(prefers-color-scheme:light){.chip.active{background:#eff6ff;color:#1d4ed8}.pill{background:#f1f5f9;color:#475569}.pill.critical{background:#fef2f2;color:#b91c1c}.pill.attention,.pill.watch{background:#fffbeb;color:#a16207}}
</style>
</head>
<body>
<div class="o360">
<header class="o360-head">
<div>
<h1>Vista 360</h1>
<div class="meta">Ámbito → clientes → servicios → proyectos → tareas → vencimientos → incidentes → finanzas.</div>
</div>
<x-operational-nav active="overview360" />
</header>

<nav class="scopes" aria-label="Filtrar por ámbito">
<a class="chip {{ $selectedScope ? '' : 'active' }}" href="{{ route('operational-360.show') }}">Todos</a>
@foreach($organizations as $organization)
<a class="chip {{ $selectedScope === $organization->id ? 'active' : '' }}" href="{{ route('operational-360.show',['scope'=>$organization->id]) }}">{{ $organization->name }}</a>
@endforeach
</nav>

<section class="kpis">
@foreach([
'Clientes'=>$counts['clients'],
'Servicios abiertos'=>$counts['services'],
'Proyectos abiertos'=>$counts['projects'],
'Tareas abiertas'=>$counts['tasks'],
'Vencimientos'=>$counts['obligations'],
'Incidentes abiertos'=>$counts['incidents'],
] as $label=>$value)
<div class="kpi"><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
@endforeach
</section>

<section class="section">
<div class="section-head"><h2>Atención transversal</h2><a class="section-link" href="{{ route('global-tracking.show',array_filter(['scope'=>$selectedScope])) }}">Seguimiento →</a></div>
<div class="list">
@forelse($attention as $item)
<a class="card" href="{{ $item['url'] }}">
<div class="row">
<div>
<div class="card-title">{{ $item['title'] }}</div>
<div class="meta">{{ $item['organization'] }} · {{ $item['type_label'] }}</div>
</div>
<span class="pill {{ $item['level'] }}">{{ $item['level_label'] }}</span>
</div>
<div class="meta">{{ $item['meta'] }} @if($item['date_label']) · {{ $item['date_label'] }} @endif</div>
@if(!empty($item['reasons']))<div class="reason">{{ implode(' · ',array_slice($item['reasons'],0,2)) }}</div>@endif
</a>
@empty
<div class="empty">No hay señales transversales que requieran atención.</div>
@endforelse
</div>
</section>

<div class="grid two">
<section class="section">
<div class="section-head"><h2>Clientes</h2><a class="section-link" href="{{ url('/admin/clientes') }}">Administrar →</a></div>
<div class="list">
@forelse($clients->take(10) as $client)
<div class="card">
<div class="card-title">{{ $client->name }}</div>
<div class="meta">{{ $client->organization?->name ?? 'Sin ámbito' }} · {{ $client->open_services_count }} servicio(s) abierto(s)</div>
@if($client->contact_name || $client->email)<div class="meta">{{ $client->contact_name }} @if($client->contact_name && $client->email) · @endif {{ $client->email }}</div>@endif
</div>
@empty
<div class="empty">Sin clientes activos.</div>
@endforelse
</div>
</section>

<section class="section">
<div class="section-head"><h2>Servicios</h2><a class="section-link" href="{{ route('service-orders-ops.show',array_filter(['scope'=>$selectedScope])) }}">Ver servicios →</a></div>
<div class="list">
@forelse($services->sortByDesc('updated_at')->take(10) as $order)
<a class="card" href="{{ route('service-orders-ops.show',['scope'=>$order->organization_id]) }}">
<div class="card-title">{{ $order->title }}</div>
<div class="meta">{{ $order->client?->name ?? 'Sin cliente' }} · {{ \App\Models\ServiceOrder::stageOptions()[$order->stage] ?? $order->stage }}</div>
@if($order->next_action)<div class="reason">Siguiente: {{ $order->next_action }}</div>@endif
</a>
@empty
<div class="empty">Sin servicios abiertos.</div>
@endforelse
</div>
</section>

<section class="section">
<div class="section-head"><h2>Proyectos</h2><a class="section-link" href="{{ route('project-ops.show',array_filter(['scope'=>$selectedScope,'focus'=>'all'])) }}">Ver proyectos →</a></div>
<div class="list">
@forelse($projects->sortByDesc('updated_at')->take(10) as $project)
<a class="card" href="{{ route('project-ops.show',['scope'=>$project->organization_id,'focus'=>'all']) }}">
<div class="row"><div class="card-title">{{ $project->name }}</div><span class="pill">Avance {{ $project->progress_percent }}%</span></div>
<div class="meta">{{ $project->organization?->name }} @if($project->target_date) · objetivo {{ $project->target_date->format('d/m/Y') }} @endif</div>
@if($project->next_action)<div class="reason">Siguiente: {{ $project->next_action }}</div>@endif
@if($project->blockers)<div class="reason">Bloqueo: {{ $project->blockers }}</div>@endif
</a>
@empty
<div class="empty">Sin proyectos abiertos.</div>
@endforelse
</div>
</section>

<section class="section">
<div class="section-head"><h2>Incidentes</h2><a class="section-link" href="{{ url('/admin/incidentes') }}">Administrar →</a></div>
<div class="list">
@forelse($incidents->take(10) as $incident)
<div class="card">
<div class="row">
<div><div class="card-title">{{ $incident->title }}</div><div class="meta">{{ $incident->organization?->name }} · {{ \App\Models\Incident::statusOptions()[$incident->status] ?? $incident->status }}</div></div>
<span class="pill {{ in_array($incident->severity,['critical','high'],true) ? 'critical' : '' }}">{{ \App\Models\Incident::severityOptions()[$incident->severity] ?? $incident->severity }}</span>
</div>
<div class="links">
@if($incident->client)<a href="{{ url('/admin/clientes') }}">Cliente: {{ $incident->client->name }}</a>@endif
@if($incident->serviceOrder)<a href="{{ route('service-orders-ops.show',['scope'=>$incident->organization_id]) }}">Servicio: {{ $incident->serviceOrder->title }}</a>@endif
@if($incident->project)<a href="{{ route('project-ops.show',['scope'=>$incident->organization_id,'focus'=>'all']) }}">Proyecto: {{ $incident->project->name }}</a>@endif
</div>
@if($incident->next_action)<div class="reason">Siguiente: {{ $incident->next_action }}</div>@endif
</div>
@empty
<div class="empty">Sin incidentes abiertos.</div>
@endforelse
</div>
</section>
</div>

<section class="section">
<div class="section-head"><h2>Finanzas consolidadas</h2><a class="section-link" href="{{ route('executive-summary.show',array_filter(['scope'=>$selectedScope])) }}">Resumen ejecutivo →</a></div>
<div class="finance-groups">
@forelse($finances as $currency=>$money)
<div>
<div class="finance-title">{{ $currency }}</div>
<div class="finance-grid">
@foreach([
'Cartera de servicios abierta'=>$money['service_open'],
'Facturado registrado'=>$money['invoiced'],
'Por cobrar'=>$money['receivable'],
'Por cobrar vencido'=>$money['receivable_overdue'],
'Presupuesto proyectos abiertos'=>$money['project_budget'],
'Obligaciones pendientes'=>$money['obligation_pending'],
'Obligaciones vencidas'=>$money['obligation_overdue'],
] as $label=>$value)
<div class="finance"><strong>{{ $currency }} {{ number_format($value,2,'.',',') }}</strong><span>{{ $label }}</span></div>
@endforeach
</div>
</div>
@empty
<div class="empty">No hay importes registrados para consolidar.</div>
@endforelse
</div>
</section>

<section class="section">
<div class="section-head"><h2>Accesos operativos</h2></div>
<div class="links">
<a href="{{ route('daily-ops.show',array_filter(['scope'=>$selectedScope])) }}">Mi día</a>
<a href="{{ route('operational-agenda.show',array_filter(['scope'=>$selectedScope])) }}">Agenda</a>
<a href="{{ route('service-orders-ops.show',array_filter(['scope'=>$selectedScope])) }}">Servicios</a>
<a href="{{ route('project-ops.show',array_filter(['scope'=>$selectedScope,'focus'=>'all'])) }}">Proyectos</a>
<a href="{{ route('obligation-ops.show',array_filter(['scope'=>$selectedScope])) }}">Vencimientos</a>
<a href="{{ route('global-tracking.show',array_filter(['scope'=>$selectedScope])) }}">Seguimiento</a>
</div>
</section>
</div>
<x-operational-interactions />
</body>
</html>
