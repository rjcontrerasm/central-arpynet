<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Proyectos · Central ARPYNET</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:dark}
*{box-sizing:border-box}body{margin:0;background:#0b1020;color:#f8fafc}a{color:inherit;text-decoration:none}button,input,select{font:inherit}
.shell{width:min(100%,1200px);margin:0 auto;padding:20px 16px 80px}.topbar,.hero,.card-head,.card-foot{display:flex;align-items:center;justify-content:space-between;gap:12px}.topbar{margin-bottom:24px}
.brand{font-weight:850;letter-spacing:-.03em}.nav{display:flex;flex-wrap:wrap;gap:8px;font-size:13px}.nav a{padding:7px 9px;border-radius:9px;color:#94a3b8}.nav a.active{background:#172554;color:#dbeafe}
.hero{align-items:end;margin-bottom:18px}h1{margin:0;font-size:clamp(31px,7vw,48px);line-height:.96;letter-spacing:-.05em}.subtitle,.muted,.empty{color:#94a3b8}.subtitle{margin-top:8px;font-size:13px}
.admin{padding:10px 13px;border:1px solid #334155;border-radius:11px;background:#11182b;font-size:12px;font-weight:800}
.filters{display:grid;grid-template-columns:1fr 1fr minmax(180px,2fr) auto;gap:8px;margin-bottom:18px}.filters select,.filters input{min-width:0;height:40px;padding:8px 10px;border:1px solid #334155;border-radius:10px;background:#11182b;color:#f8fafc}.filters button{border:0;border-radius:10px;padding:0 14px;background:#2563eb;color:#fff;font-weight:850;cursor:pointer}
.stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px;margin-bottom:22px}.stat,.card{border:1px solid #24304b;background:#11182b}.stat{padding:13px;border-radius:15px}.stat-value{font-size:27px;font-weight:850;line-height:1}.stat-label{margin-top:5px;color:#94a3b8;font-size:11px}
.list{display:grid;gap:11px}.card{padding:15px;border-radius:17px}.card.critical{border-color:#7f1d1d}.card.attention{border-color:#92400e}.card.watch{border-color:#1e40af}.title{font-size:17px;font-weight:850}.org{margin-top:4px;color:#94a3b8;font-size:12px}
.badges{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}.badge{padding:4px 8px;border-radius:999px;background:#1e293b;color:#cbd5e1;font-size:10px;font-weight:800}.badge.critical{background:#450a0a;color:#fecaca}.badge.attention{background:#451a03;color:#fde68a}.badge.watch{background:#172554;color:#bfdbfe}
.progress{height:7px;margin-top:13px;overflow:hidden;border-radius:999px;background:#1e293b}.progress>span{display:block;height:100%;background:#3b82f6;border-radius:inherit}.meta-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;color:#94a3b8;font-size:11px}
.next,.blockers{margin-top:11px;padding:10px 11px;border-radius:11px;font-size:12px;line-height:1.45}.next{background:#0f172a;border-left:3px solid #3b82f6;color:#cbd5e1}.blockers{background:#2a1608;border-left:3px solid #d97706;color:#fde68a}
.reasons{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.reason{padding:4px 7px;border-radius:8px;background:#1e293b;color:#cbd5e1;font-size:10px;font-weight:750}.card-foot{margin-top:12px;padding-top:11px;border-top:1px solid #24304b}.card-link{color:#93c5fd;font-size:11px;font-weight:850}
.empty{padding:30px 18px;text-align:center;border:1px dashed #334155;border-radius:16px}
@media(max-width:800px){.topbar,.hero{align-items:flex-start;flex-direction:column}.filters{grid-template-columns:1fr 1fr}.filters input{grid-column:1/-1}.filters button{height:40px}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.shell{padding:16px 12px 72px}.filters{grid-template-columns:1fr}.filters input{grid-column:auto}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.card-head,.card-foot{align-items:flex-start;flex-direction:column}.badges{justify-content:flex-start}}
</style>
</head>
<body>
<div class="shell">
<header class="topbar">
<a class="brand" href="{{ route('daily-ops.show') }}">Central ARPYNET</a>
<nav class="nav">
<a href="{{ route('daily-ops.show') }}">Mi día</a>
<a href="{{ route('operational-agenda.show') }}">Agenda</a>
<a href="{{ route('global-tracking.show') }}">Seguimiento</a>
<a class="active" aria-current="page" href="{{ route('project-ops.show') }}">Proyectos</a>
</nav>
</header>

<section class="hero">
<div><h1>Proyectos</h1><div class="subtitle">Avance, bloqueos, siguiente acción y señales de estancamiento.</div></div>
<a class="admin" href="{{ url('/admin/proyectos') }}">Administrar proyectos</a>
</section>

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
<div class="card-foot">
<span class="muted">{{ $project->tasks_count }} tareas · {{ $project->completed_tasks_count }} completadas</span>
<a class="card-link" href="{{ url('/admin/proyectos') }}">Abrir administración →</a>
</div>
</article>
@empty
<div class="empty">No hay proyectos para los filtros seleccionados.</div>
@endforelse
</section>
</div>
</body>
</html>
