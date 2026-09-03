<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Agenda · Central ARPYNET</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:light dark}*{box-sizing:border-box}body{margin:0;background:#0b1020;color:#f8fafc}a{color:inherit}.shell{width:min(100%,1200px);margin:0 auto;padding:24px 16px 70px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px}.brand{font-weight:850;letter-spacing:-.03em}.hero{margin-top:28px}h1{margin:0;font-size:clamp(32px,5vw,48px);letter-spacing:-.055em}.subtitle{margin-top:7px;color:#94a3b8;font-size:13px}.toolbar{display:flex;flex-wrap:wrap;align-items:end;gap:9px;margin-top:20px}.toolbar a{min-height:40px;display:inline-flex;align-items:center;padding:8px 11px;border:1px solid #334155;border-radius:10px;background:#11182b;color:#cbd5e1;font-size:12px;font-weight:780;text-decoration:none}.scope{display:grid;gap:5px;min-width:220px;color:#94a3b8;font-size:11px;font-weight:760}.scope select{min-height:40px;padding:8px 10px;border:1px solid #334155;border-radius:10px;background:#11182b;color:#f8fafc;font:inherit}.stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px;margin-top:18px}.stat{padding:13px;border:1px solid #24304b;border-radius:14px;background:#11182b}.stat strong{display:block;font-size:22px}.stat span{color:#94a3b8;font-size:11px}.calendar-status{margin-top:12px;padding:10px 12px;border:1px solid #24304b;border-radius:12px;background:#0f172a;color:#94a3b8;font-size:11px}.calendar-status.error{border-color:#92400e;color:#fde68a}.agenda{display:grid;gap:8px;margin-top:20px}.item{display:grid;grid-template-columns:88px minmax(0,1fr) auto;gap:14px;align-items:center;min-height:72px;padding:13px 14px;border:1px solid #24304b;border-radius:15px;background:#11182b;text-decoration:none}.item:hover{border-color:#3b82f6}.time{color:#93c5fd;font-size:13px;font-weight:850}.title{font-weight:820}.meta{margin-top:4px;color:#94a3b8;font-size:11px}.badge{padding:5px 8px;border-radius:999px;background:#172554;color:#bfdbfe;font-size:10px;font-weight:820;white-space:nowrap}.badge.calendar{background:#312e81;color:#e0e7ff}.badge.obligation{background:#3f1d0b;color:#fed7aa}.badge.waiting{background:#3f3f46;color:#e4e4e7}.empty{margin-top:20px;padding:30px;border:1px dashed #334155;border-radius:16px;color:#94a3b8;text-align:center}@media(max-width:720px){.topbar{align-items:flex-start}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.item{grid-template-columns:68px minmax(0,1fr)}.badge{grid-column:2;justify-self:start}}@media(prefers-color-scheme:light){body{background:#f8fafc;color:#0f172a}.toolbar a,.scope select,.stat,.item,.calendar-status{background:#fff;border-color:#e2e8f0}.scope select{color:#0f172a}}
</style>
</head>
<body>
<div class="shell">
<div class="topbar"><div class="brand">Central ARPYNET</div><x-operational-nav active="agenda" /></div>
<section class="hero">
<h1>Agenda</h1>
<div class="subtitle">{{ $date->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }} · Central + Google Calendar sin duplicar eventos sincronizados.</div>
<div class="toolbar">
<a href="{{ route('operational-agenda.show', array_filter(['date'=>$previousDate,'scope'=>$scope])) }}">← Día anterior</a>
<a href="{{ route('operational-agenda.show', array_filter(['date'=>$todayDate,'scope'=>$scope])) }}">Hoy</a>
<a href="{{ route('operational-agenda.show', array_filter(['date'=>$nextDate,'scope'=>$scope])) }}">Día siguiente →</a>
<form method="GET" action="{{ route('operational-agenda.show') }}"><input type="hidden" name="date" value="{{ $date->toDateString() }}"><label class="scope">Ámbito<select name="scope" onchange="this.form.submit()"><option value="">Todos</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)$scope === (string)$organization->id)>{{ $organization->name }}</option>@endforeach</select></label></form>
</div>
<div class="stats">
<div class="stat"><strong>{{ $counts['total'] }}</strong><span>Total</span></div>
<div class="stat"><strong>{{ $counts['calendar'] }}</strong><span>Calendario</span></div>
<div class="stat"><strong>{{ $counts['tasks'] }}</strong><span>Tareas</span></div>
<div class="stat"><strong>{{ $counts['followups'] }}</strong><span>Seguimientos</span></div>
<div class="stat"><strong>{{ $counts['obligations'] }}</strong><span>Vencimientos</span></div>
</div>
@if(($calendar['status']??null)==='disconnected')<div class="calendar-status">Google Calendar no está conectado. La agenda sigue mostrando la información interna de Central.</div>@elseif(($calendar['status']??null)==='error')<div class="calendar-status error">{{ $calendar['error'] }} La información interna de Central sigue disponible.</div>@else<div class="calendar-status">Google Calendar conectado · {{ $counts['calendar'] }} evento(s) externo(s).</div>@endif
</section>
@if($items->isEmpty())<div class="empty">No hay elementos programados para este día.</div>@else<section class="agenda">@foreach($items as $item)<a class="item" href="{{ $item['url'] ?: '#' }}" @if($item['external']) target="_blank" rel="noopener noreferrer" @endif><div class="time">{{ $item['all_day'] ? 'Todo el día' : $item['starts_at']->format('H:i') }}</div><div><div class="title">{{ $item['title'] }}</div><div class="meta">@if($item['organization']){{ $item['organization'] }} · @endif{{ $item['subtitle'] ?: ucfirst($item['kind']) }}</div></div><div class="badge {{ $item['kind'] }}">@switch($item['kind'])@case('calendar')Calendario@break @case('task')Tarea@break @case('waiting')En espera@break @case('obligation')Vencimiento@break @case('service')Servicio@break @case('incident')Incidente@break @default{{ ucfirst($item['kind']) }}@endswitch</div></a>@endforeach</section>@endif
</div>
<x-operational-theme /><x-operational-interactions />
</body></html>
