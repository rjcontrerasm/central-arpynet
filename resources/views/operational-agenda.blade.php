<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Agenda · Central ARPYNET</title>
<style>
:root{
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  color-scheme:light dark;
  --ag-bg:#f5f8fc;
  --ag-card:#ffffff;
  --ag-text:#102a56;
  --ag-muted:#6c7fa3;
  --ag-line:#dbe5f0;
  --ag-blue:#0f67ff;
  --ag-blue-soft:#eaf3ff;
  --ag-green:#16a36f;
  --ag-green-soft:#e8f8f2;
  --ag-purple:#6947ff;
  --ag-purple-soft:#efeaff;
  --ag-amber:#a85f13;
  --ag-amber-soft:#fff4e5;
  --ag-red:#d96969;
  --ag-red-strong:#c95353;
  --ag-red-soft:#fff4f3;
  --ag-shadow:0 8px 28px rgba(28,57,96,.06);
}
*{box-sizing:border-box}
body{margin:0;background:var(--ag-bg);color:var(--ag-text)}
a{color:inherit}
.shell{width:min(100%,1240px);margin:0 auto;padding:18px 18px 68px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px}
.brand{font-weight:900;letter-spacing:-.035em;color:#0d2b63}

.hero{margin-top:22px}
.hero-row{display:flex;justify-content:space-between;align-items:center;gap:20px}
.hero-title{display:flex;align-items:center;gap:15px}
.hero-icon{width:58px;height:58px;display:grid;place-items:center;border-radius:16px;background:linear-gradient(180deg,#edf5ff,#e1efff);color:var(--ag-blue);font-size:28px;font-weight:900}
h1{margin:0;font-size:clamp(34px,4.5vw,48px);letter-spacing:-.055em;line-height:1}
.subtitle{margin-top:7px;color:var(--ag-muted);font-size:13px}
.today-chip{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border-radius:12px;background:#0d63e8;color:white;font-size:12px;font-weight:850;box-shadow:0 6px 16px rgba(13,99,232,.18)}

.toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:18px}
.nav-date{display:flex;gap:8px}
.toolbar a{min-height:40px;display:inline-flex;align-items:center;padding:8px 13px;border:1px solid var(--ag-line);border-radius:10px;background:var(--ag-card);color:#1b4b91;font-size:12px;font-weight:800;text-decoration:none;box-shadow:0 2px 8px rgba(28,57,96,.03)}
.toolbar a:hover{border-color:#b7ccef;background:#fbfdff}
.scope{display:flex;align-items:center;gap:10px;margin-left:12px;color:#263f67;font-size:12px;font-weight:850}
.scope:before{content:"";width:1px;height:30px;background:#d5e0ed;margin-right:2px}
.scope select{min-width:220px;min-height:40px;padding:8px 12px;border:1px solid var(--ag-line);border-radius:10px;background:var(--ag-card);color:#15396d;font:inherit;box-shadow:0 2px 8px rgba(28,57,96,.03)}

.stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-top:18px}
.stat{display:grid;grid-template-columns:42px 1fr;gap:10px;align-items:center;padding:13px 14px;border:1px solid var(--ag-line);border-radius:13px;background:var(--ag-card);box-shadow:var(--ag-shadow)}
.stat-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:var(--ag-blue-soft);color:var(--ag-blue);font-size:18px;font-weight:900}
.stat strong{display:block;font-size:22px;letter-spacing:-.04em;line-height:1}
.stat span{display:block;margin-top:5px;color:var(--ag-muted);font-size:11px}
.stat.programmed .stat-icon{background:var(--ag-green-soft);color:var(--ag-green)}
.stat.calendar .stat-icon{background:var(--ag-purple-soft);color:var(--ag-purple)}
.stat.overdue .stat-icon{background:#fdeaea;color:var(--ag-red-strong)}
.stat.overdue strong{color:var(--ag-red-strong)}

.calendar-status{margin-top:11px;padding:9px 12px;border:1px solid var(--ag-line);border-radius:10px;background:var(--ag-card);color:#54729e;font-size:11px}
.calendar-status.error{border-color:#e9c887;background:#fffaf0;color:#9a6b10}

.section{margin-top:22px}
.section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:9px}
.section-title{display:flex;align-items:center;gap:9px;font-size:17px;font-weight:900;letter-spacing:-.02em;color:#19345f;text-transform:none}
.section-title .dot{width:26px;height:26px;display:grid;place-items:center;border-radius:999px;background:#fdecec;color:var(--ag-red-strong);font-size:14px}
.section-count{font-size:11px;color:#6e82a4}

.overdue-board{display:grid;gap:7px}
.overdue-card{display:grid;grid-template-columns:132px minmax(0,1fr) auto 18px;gap:14px;align-items:center;min-height:58px;padding:8px 12px 8px 0;border:1px solid #ecd1cf;border-left:5px solid var(--ag-red);border-radius:11px;background:linear-gradient(90deg,#fff8f7 0%,#fff 34%);text-decoration:none;box-shadow:0 2px 8px rgba(28,57,96,.03)}
.overdue-card:hover{border-color:#deb6b3;border-left-color:var(--ag-red-strong);background:#fffdfd}
.overdue-date{padding-left:16px;color:var(--ag-red-strong);font-size:11px;font-weight:900;line-height:1.25}
.overdue-date strong{display:block;font-size:13px}
.title{font-weight:850;color:#17376b;line-height:1.22}
.meta{margin-top:3px;color:#7184a4;font-size:11px}
.badge-group{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}
.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:var(--ag-blue-soft);color:#1766cf;font-size:10px;font-weight:850;white-space:nowrap}
.badge.calendar{background:var(--ag-purple-soft);color:#6543d9}
.badge.obligation{background:#f8eee4;color:#915115}
.badge.waiting{background:var(--ag-amber-soft);color:var(--ag-amber)}
.badge.service{background:#eaf3ff;color:#1766cf}
.badge.incident{background:#fdecec;color:#bd5353}
.chevron{color:#2f65b7;font-size:20px;font-weight:700;text-align:center}

.timeline{position:relative;display:grid;gap:8px}
.timeline:before{content:"";position:absolute;left:42px;top:8px;bottom:8px;width:1px;background:#d9e4ef}
.item{position:relative;display:grid;grid-template-columns:84px minmax(0,1fr) auto 18px;gap:14px;align-items:center;min-height:66px;padding:11px 12px;border:1px solid var(--ag-line);border-radius:11px;background:var(--ag-card);text-decoration:none;box-shadow:0 2px 8px rgba(28,57,96,.03)}
.item:hover{border-color:#b9cce7}
.item:before{content:"";position:absolute;left:38px;width:9px;height:9px;border-radius:999px;background:#4e8df7;box-shadow:0 0 0 4px #fff}
.time{padding-left:18px;color:#356aac;font-size:12px;font-weight:900}
.empty{padding:26px;border:1px dashed #cad7e6;border-radius:13px;background:rgba(255,255,255,.55);color:#7284a2;text-align:center}

@media(max-width:950px){
  .stats{grid-template-columns:repeat(3,minmax(0,1fr))}
  .overdue-card{grid-template-columns:110px minmax(0,1fr) auto 18px}
}
@media(max-width:720px){
  .shell{padding-left:14px;padding-right:14px}
  .topbar{align-items:flex-start}
  .hero-row{display:grid}
  .today-chip{justify-self:start}
  .scope{margin-left:0;width:100%}
  .scope:before{display:none}
  .scope select{min-width:0;flex:1}
  .stats{grid-template-columns:repeat(2,minmax(0,1fr))}
  .overdue-card,.item{grid-template-columns:82px minmax(0,1fr) 18px}
  .badge-group{grid-column:2;justify-content:flex-start}
  .overdue-card .chevron,.item .chevron{grid-column:3;grid-row:1 / span 2}
  .timeline:before{left:36px}
  .item:before{left:32px}
  .time{padding-left:14px}
  .hero-icon{width:48px;height:48px;border-radius:13px}
}
@media(prefers-color-scheme:dark){
  :root{
    --ag-bg:#0b1020;
    --ag-card:#11182b;
    --ag-text:#f8fafc;
    --ag-muted:#94a3b8;
    --ag-line:#24304b;
  }
  .brand,.title,.section-title{color:#f8fafc}
  .subtitle,.meta,.section-count{color:#94a3b8}
  .toolbar a,.scope select,.calendar-status,.stat,.item{background:#11182b;color:#e2e8f0}
  .overdue-card{background:#17151d;border-color:#5d3d48;border-left-color:#c97878}
  .empty{background:#0f172a;border-color:#334155}
  .item:before{box-shadow:0 0 0 4px #11182b}
}
</style>
</head>
<body>
@php
    $kindLabels = [
        'calendar' => 'Calendario',
        'task' => 'Tarea',
        'waiting' => 'En espera',
        'obligation' => 'Vencimiento',
        'service' => 'Servicio',
        'incident' => 'Incidente',
    ];

    $kindIcons = [
        'calendar' => '◫',
        'task' => '▣',
        'waiting' => '⌛',
        'obligation' => '◷',
        'service' => '⌕',
        'incident' => '△',
    ];
@endphp

<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="agenda" />
    </div>

    <section class="hero">
        <div class="hero-row">
            <div class="hero-title">
                <div class="hero-icon">▣</div>
                <div>
                    <h1>Agenda</h1>
                    <div class="subtitle">
                        {{ $date->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}
                        · Central + Google Calendar
                    </div>
                </div>
            </div>

            @if($isToday)
                <div class="today-chip">＋ Hoy operativo</div>
            @endif
        </div>

        <div class="toolbar">
            <div class="nav-date">
                <a href="{{ route('operational-agenda.show', array_filter(['date'=>$previousDate,'scope'=>$scope])) }}">← Anterior</a>
                <a href="{{ route('operational-agenda.show', array_filter(['date'=>$todayDate,'scope'=>$scope])) }}">Hoy</a>
                <a href="{{ route('operational-agenda.show', array_filter(['date'=>$nextDate,'scope'=>$scope])) }}">Siguiente →</a>
            </div>

            <form method="GET" action="{{ route('operational-agenda.show') }}">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <label class="scope">
                    Ámbito
                    <select name="scope" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach($organizations as $organization)
                            <option
                                value="{{ $organization->id }}"
                                @selected((string)$scope === (string)$organization->id)
                            >
                                {{ $organization->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </form>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="stat-icon">▣</div>
                <div><strong>{{ $counts['total'] }}</strong><span>Total visible</span></div>
            </div>

            <div class="stat programmed">
                <div class="stat-icon">✓</div>
                <div><strong>{{ $counts['scheduled'] }}</strong><span>Programados</span></div>
            </div>

            <div class="stat calendar">
                <div class="stat-icon">◫</div>
                <div><strong>{{ $counts['calendar'] }}</strong><span>Calendario</span></div>
            </div>

            <div class="stat">
                <div class="stat-icon">☷</div>
                <div><strong>{{ $counts['tasks'] }}</strong><span>Tareas</span></div>
            </div>

            <div class="stat">
                <div class="stat-icon">♙</div>
                <div><strong>{{ $counts['followups'] }}</strong><span>Seguimientos</span></div>
            </div>

            <div class="stat overdue">
                <div class="stat-icon">◷</div>
                <div><strong>{{ $counts['overdue'] }}</strong><span>Vencidos</span></div>
            </div>
        </div>

        @if(($calendar['status'] ?? null) === 'disconnected')
            <div class="calendar-status">
                Google Calendar no está conectado. La agenda sigue mostrando la información interna de Central.
            </div>
        @elseif(($calendar['status'] ?? null) === 'error')
            <div class="calendar-status error">
                {{ $calendar['error'] }} La información interna de Central sigue disponible.
            </div>
        @else
            <div class="calendar-status">
                Google Calendar conectado · {{ $counts['calendar'] }} evento(s) externo(s).
            </div>
        @endif
    </section>

    @if($isToday && $overdueItems->isNotEmpty())
        <section class="section">
            <div class="section-head">
                <div class="section-title">
                    <span class="dot">◷</span>
                    Pendientes vencidos
                </div>
                <div class="section-count">{{ $overdueItems->count() }} por atender</div>
            </div>

            <div class="overdue-board">
                @foreach($overdueItems as $item)
                    <a class="overdue-card" href="{{ $item['url'] ?: '#' }}">
                        <div class="overdue-date">
                            Vencido
                            <strong>
                                {{ $item['starts_at']->format('d/m') }}
                                @if(!$item['all_day'])
                                    · {{ $item['starts_at']->format('H:i') }}
                                @endif
                            </strong>
                        </div>

                        <div>
                            <div class="title">{{ $item['title'] }}</div>
                            <div class="meta">
                                @if($item['organization'])
                                    {{ $item['organization'] }} ·
                                @endif
                                {{ $item['subtitle'] ?: ($kindLabels[$item['kind']] ?? ucfirst($item['kind'])) }}
                            </div>
                        </div>

                        <div class="badge-group">
                            <span class="badge {{ $item['kind'] }}">
                                {{ $kindIcons[$item['kind']] ?? '•' }}
                                {{ $kindLabels[$item['kind']] ?? ucfirst($item['kind']) }}
                            </span>
                        </div>

                        <div class="chevron">›</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="section">
        <div class="section-head">
            <div class="section-title">
                <span class="dot" style="background:#eaf3ff;color:#1766cf">▣</span>
                Programado para el día
            </div>
            <div class="section-count">{{ $scheduledItems->count() }} elemento(s)</div>
        </div>

        @if($scheduledItems->isEmpty())
            <div class="empty">
                No hay elementos programados para este día.
                @if($isToday && $overdueItems->isNotEmpty())
                    Los pendientes vencidos se muestran arriba.
                @endif
            </div>
        @else
            <div class="timeline">
                @foreach($scheduledItems as $item)
                    <a
                        class="item"
                        href="{{ $item['url'] ?: '#' }}"
                        @if($item['external']) target="_blank" rel="noopener noreferrer" @endif
                    >
                        <div class="time">
                            {{ $item['all_day'] ? 'Todo el día' : $item['starts_at']->format('H:i') }}
                        </div>

                        <div>
                            <div class="title">{{ $item['title'] }}</div>
                            <div class="meta">
                                @if($item['organization'])
                                    {{ $item['organization'] }} ·
                                @endif
                                {{ $item['subtitle'] ?: ($kindLabels[$item['kind']] ?? ucfirst($item['kind'])) }}
                            </div>
                        </div>

                        <div class="badge-group">
                            <span class="badge {{ $item['kind'] }}">
                                {{ $kindIcons[$item['kind']] ?? '•' }}
                                {{ $kindLabels[$item['kind']] ?? ucfirst($item['kind']) }}
                            </span>
                        </div>

                        <div class="chevron">›</div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>

<x-operational-theme />
<x-operational-interactions />
</body>
</html>
