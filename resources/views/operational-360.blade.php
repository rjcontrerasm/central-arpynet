<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Vista 360 · Central ARPYNET</title>
<x-operational-theme />
<link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/operational-360.css') }}?v=2.39.2"
    >
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
<div class="section-head"><h2>Clientes</h2><span class="meta">Vista consolidada</span></div>
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
<div class="section-head"><h2>Incidentes</h2><a class="section-link" href="{{ route('incident-360.index',array_filter(['scope'=>$selectedScope])) }}">Incident 360 →</a></div>
<div class="list">
@forelse($incidents->take(10) as $incident)
<div class="card">
<div class="row">
<div><div class="card-title">{{ $incident->title }}</div><div class="meta">{{ $incident->organization?->name }} · {{ \App\Models\Incident::statusOptions()[$incident->status] ?? $incident->status }}</div></div>
<span class="pill {{ in_array($incident->severity,['critical','high'],true) ? 'critical' : '' }}">{{ \App\Models\Incident::severityOptions()[$incident->severity] ?? $incident->severity }}</span>
</div>
<div class="links">
@if($incident->client)<span class="meta">Cliente: {{ $incident->client->name }}</span>@endif
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
