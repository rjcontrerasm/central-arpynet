<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Agenda · Central ARPYNET</title>
<link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/operational-agenda.css') }}?v=2.39.1"
    >
</head>
<body>
@php
    $kindLabels = [
        'calendar' => 'Calendario',
        'task' => 'Tarea',
        'project' => 'Proyecto',
        'waiting' => 'En espera',
        'obligation' => 'Vencimiento',
        'service' => 'Servicio',
        'incident' => 'Incidente',
    ];

    $kindIcons = [
        'calendar' => '◫',
        'task' => '▣',
        'project' => '◆',
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


            <div class="stat">
                <div class="stat-icon">◆</div>
                <div><strong>{{ $counts['projects'] }}</strong><span>Proyectos</span></div>
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

        <x-calendar-context :context="$calendarContext" />
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
                <span class="dot dot-scheduled">▣</span>
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
