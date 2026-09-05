<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Agenda · Central ARPYNET</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:light dark}
*{box-sizing:border-box}body{margin:0;background:#0b1020;color:#f8fafc}a{color:inherit}
.shell{width:min(100%,1240px);margin:0 auto;padding:22px 18px 72px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px}.brand{font-weight:900;letter-spacing:-.035em}
.hero{margin-top:30px}.hero-row{display:flex;justify-content:space-between;gap:24px;align-items:flex-end}
h1{margin:0;font-size:clamp(34px,5vw,52px);letter-spacing:-.06em}.subtitle{margin-top:7px;color:#94a3b8;font-size:13px}
.today-chip{display:inline-flex;padding:7px 10px;border:1px solid #1d4ed8;border-radius:999px;background:#172554;color:#bfdbfe;font-size:11px;font-weight:850}
.toolbar{display:flex;flex-wrap:wrap;align-items:end;gap:9px;margin-top:20px}.nav-date{display:flex;gap:8px}
.toolbar a{min-height:40px;display:inline-flex;align-items:center;padding:8px 12px;border:1px solid #334155;border-radius:11px;background:#11182b;color:#cbd5e1;font-size:12px;font-weight:800;text-decoration:none}
.toolbar a:hover{border-color:#3b82f6}.scope{display:grid;gap:5px;min-width:230px;color:#94a3b8;font-size:11px;font-weight:800}
.scope select{min-height:40px;padding:8px 10px;border:1px solid #334155;border-radius:11px;background:#11182b;color:#f8fafc;font:inherit}
.stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px;margin-top:18px}
.stat{padding:13px 14px;border:1px solid #24304b;border-radius:14px;background:#11182b}.stat strong{display:block;font-size:23px;letter-spacing:-.04em}.stat span{color:#94a3b8;font-size:11px}
.stat.overdue{border-color:#7f1d1d;background:rgba(127,29,29,.13)}.stat.overdue strong{color:#fca5a5}
.calendar-status{margin-top:12px;padding:10px 12px;border:1px solid #24304b;border-radius:12px;background:#0f172a;color:#94a3b8;font-size:11px}.calendar-status.error{border-color:#92400e;color:#fde68a}
.section{margin-top:24px}.section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
.section-title{font-size:13px;font-weight:900;letter-spacing:.02em;text-transform:uppercase;color:#cbd5e1}.section-count{font-size:11px;color:#94a3b8}
.overdue-board{display:grid;gap:8px}.overdue-card{display:grid;grid-template-columns:120px minmax(0,1fr) auto;gap:14px;align-items:center;padding:13px 14px;border:1px solid #7f1d1d;border-radius:15px;background:rgba(69,10,10,.18);text-decoration:none}
.overdue-card:hover{border-color:#ef4444}.overdue-date{color:#fca5a5;font-size:12px;font-weight:900}
.timeline{position:relative;display:grid;gap:8px}.timeline:before{content:"";position:absolute;left:42px;top:8px;bottom:8px;width:1px;background:#24304b}
.item{position:relative;display:grid;grid-template-columns:84px minmax(0,1fr) auto;gap:14px;align-items:center;min-height:72px;padding:13px 14px;border:1px solid #24304b;border-radius:15px;background:#11182b;text-decoration:none}
.item:hover{border-color:#3b82f6}.item:before{content:"";position:absolute;left:38px;width:9px;height:9px;border-radius:999px;background:#60a5fa;box-shadow:0 0 0 4px #11182b}
.time{padding-left:18px;color:#93c5fd;font-size:13px;font-weight:900}.title{font-weight:850}.meta{margin-top:4px;color:#94a3b8;font-size:11px}
.badge{padding:5px 8px;border-radius:999px;background:#172554;color:#bfdbfe;font-size:10px;font-weight:850;white-space:nowrap}
.badge.calendar{background:#312e81;color:#e0e7ff}.badge.obligation{background:#3f1d0b;color:#fed7aa}.badge.waiting{background:#3f3f46;color:#e4e4e7}.badge.service{background:#064e3b;color:#a7f3d0}.badge.incident{background:#4c1d95;color:#ddd6fe}
.empty{padding:30px;border:1px dashed #334155;border-radius:16px;color:#94a3b8;text-align:center}
@media(max-width:900px){.stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:720px){.shell{padding-left:14px;padding-right:14px}.topbar,.hero-row{align-items:flex-start}.hero-row{display:grid}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.overdue-card,.item{grid-template-columns:72px minmax(0,1fr)}.overdue-card .badge,.item .badge{grid-column:2;justify-self:start}.timeline:before{left:36px}.item:before{left:32px}.time{padding-left:14px}}
@media(prefers-color-scheme:light){body{background:#f8fafc;color:#0f172a}.toolbar a,.scope select,.stat,.item,.calendar-status{background:#fff;border-color:#e2e8f0}.scope select{color:#0f172a}.timeline:before{background:#e2e8f0}.item:before{box-shadow:0 0 0 4px #fff}.overdue-card,.stat.overdue{background:#fff7f7}}
</style>
</head>
<body>
<div class="shell">
<div class="topbar"><div class="brand">Central ARPYNET</div><x-operational-nav active="agenda" /></div>
<section class="hero">
<div class="hero-row"><div><h1>Agenda</h1><div class="subtitle">{{ $date->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }} · Central + Google Calendar</div></div>@if($isToday)<div class="today-chip">● Hoy operativo</div>@endif</div>
<div class="toolbar">
<div class="nav-date"><a href="{{ route('operational-agenda.show', array_filter(['date'=>$previousDate,'scope'=>$scope])) }}">← Anterior</a><a href="{{ route('operational-agenda.show', array_filter(['date'=>$todayDate,'scope'=>$scope])) }}">Hoy</a><a href="{{ route('operational-agenda.show', array_filter(['date'=>$nextDate,'scope'=>$scope])) }}">Siguiente →</a></div>
<form method="GET" action="{{ route('operational-agenda.show') }}"><input type="hidden" name="date" value="{{ $date->toDateString() }}"><label class="scope">Ámbito<select name="scope" onchange="this.form.submit()"><option value="">Todos</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)$scope === (string)$organization->id)>{{ $organization->name }}</option>@endforeach</select></label></form>
</div>
<div class="stats">
<div class="stat"><strong>{{ $counts['total'] }}</strong><span>Total visible</span></div>
<div class="stat"><strong>{{ $counts['scheduled'] }}</strong><span>Programados</span></div>
<div class="stat"><strong>{{ $counts['calendar'] }}</strong><span>Calendario</span></div>
<div class="stat"><strong>{{ $counts['tasks'] }}</strong><span>Tareas</span></div>
<div class="stat"><strong>{{ $counts['followups'] }}</strong><span>Seguimientos</span></div>
<div class="stat overdue"><strong>{{ $counts['overdue'] }}</strong><span>Vencidos</span></div>
</div>
@if(($calendar['status']??null)==='disconnected')<div class="calendar-status">Google Calendar no está conectado. La agenda sigue mostrando la información interna de Central.</div>@elseif(($calendar['status']??null)==='error')<div class="calendar-status error">{{ $calendar['error'] }} La información interna de Central sigue disponible.</div>@else<div class="calendar-status">Google Calendar conectado · {{ $counts['calendar'] }} evento(s) externo(s).</div>@endif
</section>

@if($isToday && $overdueItems->isNotEmpty())
<section class="section">
<div class="section-head"><div class="section-title">Pendientes vencidos</div><div class="section-count">{{ $overdueItems->count() }} por atender</div></div>
<div class="overdue-board">
@foreach($overdueItems as $item)
<a class="overdue-card" href="{{ $item['url'] ?: '#' }}">
<div class="overdue-date">Vencido<br>{{ $item['starts_at']->format('d/m') }}@if(!$item['all_day']) · {{ $item['starts_at']->format('H:i') }}@endif</div>
<div><div class="title">{{ $item['title'] }}</div><div class="meta">@if($item['organization']){{ $item['organization'] }} · @endif{{ $item['subtitle'] ?: ucfirst($item['kind']) }}</div></div>
<div class="badge {{ $item['kind'] }}">@switch($item['kind'])@case('task')Tarea@break @case('waiting')En espera@break @case('obligation')Vencimiento@break @case('service')Servicio@break @case('incident')Incidente@break @default{{ ucfirst($item['kind']) }}@endswitch</div>
</a>
@endforeach
</div>
</section>
@endif

<section class="section">
<div class="section-head"><div class="section-title">Programado para el día</div><div class="section-count">{{ $scheduledItems->count() }} elemento(s)</div></div>
@if($scheduledItems->isEmpty())
<div class="empty">No hay elementos programados para este día.@if($isToday && $overdueItems->isNotEmpty()) Los pendientes vencidos se muestran arriba.@endif</div>
@else
<div class="timeline">
@foreach($scheduledItems as $item)
<a class="item" href="{{ $item['url'] ?: '#' }}" @if($item['external']) target="_blank" rel="noopener noreferrer" @endif>
<div class="time">{{ $item['all_day'] ? 'Todo el día' : $item['starts_at']->format('H:i') }}</div>
<div><div class="title">{{ $item['title'] }}</div><div class="meta">@if($item['organization']){{ $item['organization'] }} · @endif{{ $item['subtitle'] ?: ucfirst($item['kind']) }}</div></div>
<div class="badge {{ $item['kind'] }}">@switch($item['kind'])@case('calendar')Calendario@break @case('task')Tarea@break @case('waiting')En espera@break @case('obligation')Vencimiento@break @case('service')Servicio@break @case('incident')Incidente@break @default{{ ucfirst($item['kind']) }}@endswitch</div>
</a>
@endforeach
</div>
@endif
</section>
</div>
<x-operational-theme /><x-operational-interactions />
</body></html>
