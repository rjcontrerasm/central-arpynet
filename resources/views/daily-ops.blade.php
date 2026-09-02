<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="color-scheme" content="light dark">
    <title>Mi día · Central ARPYNET</title>

    <style>
        :root {
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            color-scheme: light dark;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #0b1020;
            color: #f8fafc;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .shell {
            width: min(100%, 1200px);
            margin: 0 auto;
            min-height: 100vh;
            padding: 18px 16px 96px;
        }

        .topbar,
        .hero,
        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .topbar { margin-bottom: 22px; }

        .brand {
            font-weight: 850;
            letter-spacing: -.03em;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .nav > a {
            padding: 7px 9px;
            border-radius: 9px;
        }

        .nav > a[aria-current="page"] {
            background: #172554;
            color: #dbeafe;
        }

        .more-menu {
            position: relative;
        }

        .more-menu summary {
            list-style: none;
            cursor: pointer;
            padding: 7px 9px;
            border-radius: 9px;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 700;
        }

        .more-menu summary::-webkit-details-marker {
            display: none;
        }

        .more-menu[open] summary {
            background: #11182b;
            color: #f8fafc;
        }

        .more-menu-panel {
            position: absolute;
            z-index: 40;
            top: calc(100% + 8px);
            right: 0;
            width: 210px;
            display: grid;
            padding: 8px;
            border: 1px solid #334155;
            border-radius: 14px;
            background: #0f172a;
            box-shadow: 0 18px 50px rgba(0, 0, 0, .28);
        }

        .more-menu-panel a {
            padding: 9px 10px;
            border-radius: 9px;
            color: #cbd5e1;
            font-size: 13px;
        }

        .more-menu-panel a:hover {
            background: #1e293b;
        }

        .nav a,
        .date,
        .meta,
        .stat-label,
        .empty {
            color: #94a3b8;
        }

        .nav a,
        .section-link {
            font-size: 13px;
        }

        .hero {
            align-items: end;
            margin-bottom: 16px;
        }

        h1 {
            margin: 0;
            font-size: clamp(31px, 8vw, 48px);
            line-height: .95;
            letter-spacing: -.055em;
        }

        .date {
            margin-top: 8px;
            font-size: 14px;
        }

        .quick {
            display: none;
            padding: 12px 16px;
            border-radius: 14px;
            background: #2563eb;
            color: #fff;
            font-weight: 850;
        }

        .success {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #166534;
            background: #052e16;
            color: #bbf7d0;
            font-size: 14px;
            font-weight: 700;
        }

        .scopes {
            display: flex;
            gap: 7px;
            overflow-x: auto;
            padding: 2px 0 14px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .scopes::-webkit-scrollbar,
        .priority-filters::-webkit-scrollbar {
            display: none;
        }

        .scope {
            flex: 0 0 auto;
            padding: 8px 11px;
            border: 1px solid #334155;
            border-radius: 999px;
            background: #0f172a;
            color: #cbd5e1;
            font-size: 12px;
            font-weight: 750;
        }

        .scope.active {
            border-color: #60a5fa;
            background: #172554;
            color: #dbeafe;
        }

        .filters {
            display: grid;
            gap: 9px;
            margin-bottom: 14px;
        }

        .search-form {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr) auto;
            gap: 8px;
        }

        .search-input {
            width: 100%;
            min-height: 42px;
            padding: 9px 12px;
            border: 1px solid #334155;
            border-radius: 12px;
            background: #0f172a;
            color: #f8fafc;
        }

        .search-button {
            min-height: 42px;
            padding: 8px 13px;
            border: 0;
            border-radius: 12px;
            background: #2563eb;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        .priority-filters {
            display: flex;
            gap: 7px;
            overflow-x: auto;
            padding-bottom: 2px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .priority-filter {
            flex: 0 0 auto;
            padding: 7px 10px;
            border: 1px solid #334155;
            border-radius: 999px;
            background: #0f172a;
            color: #cbd5e1;
            font-size: 11px;
            font-weight: 750;
        }

        .priority-filter.active {
            border-color: #60a5fa;
            background: #172554;
            color: #dbeafe;
        }

        .filter-summary {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 7px;
            color: #94a3b8;
            font-size: 12px;
        }

        .clear-filter {
            color: #93c5fd;
            font-weight: 750;
        }

        .stats {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-bottom: 22px;
        }

        .stat,
        .item {
            background: #11182b;
            border: 1px solid #24304b;
        }

        .stat {
            padding: 13px;
            border-radius: 16px;
            transition:
                transform 140ms ease,
                border-color 140ms ease;
        }

        a.stat:hover {
            border-color: #60a5fa;
        }

        .stat-value {
            font-size: 29px;
            line-height: 1;
            font-weight: 850;
            letter-spacing: -.05em;
        }

        .stat-label {
            margin-top: 6px;
            font-size: 12px;
        }

        .danger-value { color: #fca5a5; }
        .today-value { color: #93c5fd; }

        .section { margin-top: 24px; }

        .two-column main > .section:first-child {
            margin-top: 0;
        }

        .two-column main > .section:first-child .section-head h2 {
            color: #fca5a5;
        }

        .two-column main > .section:first-child .item:first-child {
            border-color: #7f1d1d;
            box-shadow: 0 10px 34px rgba(127, 29, 29, .12);
        }

        .section-head {
            align-items: baseline;
            margin-bottom: 10px;
        }

        h2 {
            margin: 0;
            font-size: 19px;
            letter-spacing: -.025em;
        }

        .section-link {
            color: #93c5fd;
        }

        .list {
            display: grid;
            gap: 9px;
        }

        .item {
            display: grid;
            gap: 5px;
            padding: 14px;
            border-radius: 16px;
        }

        .item-title {
            font-weight: 760;
            line-height: 1.35;
        }

        .meta {
            font-size: 12px;
            line-height: 1.45;
        }

        .pills,
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .pills { margin-top: 2px; }
        .actions { margin-top: 9px; }

        .actions button,
        .actions summary,
        .resume-button,
        .wait-button {
            min-height: 40px;
        }

        .pill {
            display: inline-flex;
            padding: 4px 8px;
            border-radius: 999px;
            background: #1e293b;
            color: #cbd5e1;
            font-size: 11px;
            font-weight: 750;
        }

        .pill.critical,
        .pill.overdue {
            background: #450a0a;
            color: #fecaca;
        }

        .pill.today {
            background: #172554;
            color: #bfdbfe;
        }

        .pill.week {
            background: #422006;
            color: #fde68a;
        }

        .action-form { margin: 0; }

        .action,
        .task-edit summary {
            min-height: 34px;
            padding: 6px 10px;
            border: 1px solid #334155;
            border-radius: 10px;
            background: #0f172a;
            color: #cbd5e1;
            font-size: 12px;
            font-weight: 750;
            cursor: pointer;
        }

        .action.done {
            border-color: #166534;
            background: #052e16;
            color: #bbf7d0;
        }

        .waiting-form {
            display: grid;
            gap: 8px;
            margin-top: 9px;
            padding: 10px;
            border: 1px dashed #475569;
            border-radius: 12px;
            background: rgba(15, 23, 42, .42);
        }

        .waiting-form input {
            width: 100%;
            min-height: 38px;
            padding: 8px 10px;
            border: 1px solid #334155;
            border-radius: 9px;
            background: #11182b;
            color: #f8fafc;
        }

        .wait-button,
        .resume-button {
            min-height: 36px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .wait-button {
            border: 1px solid #92400e;
            background: #451a03;
            color: #fde68a;
        }

        .resume-button {
            border: 1px solid #166534;
            background: #052e16;
            color: #bbf7d0;
        }

        .waiting-due {
            color: #fbbf24;
            font-weight: 800;
        }

        .task-edit {
            margin-top: 2px;
            padding-top: 0;
            border-top: 0;
        }

        .task-edit summary {
            display: inline-flex;
            align-items: center;
            list-style: none;
        }

        .task-edit summary::-webkit-details-marker {
            display: none;
        }

        .edit-form {
            display: grid;
            gap: 10px;
            margin-top: 10px;
            padding: 12px;
            border-radius: 13px;
            background: #0f172a;
        }

        .edit-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 9px;
        }

        .edit-field {
            display: grid;
            gap: 5px;
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
        }

        .edit-field.full {
            grid-column: 1 / -1;
        }

        .edit-field input,
        .edit-field select {
            width: 100%;
            min-height: 40px;
            padding: 8px 10px;
            border: 1px solid #334155;
            border-radius: 9px;
            background: #11182b;
            color: #f8fafc;
        }

        .save-edit {
            min-height: 40px;
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .empty {
            padding: 17px;
            border: 1px dashed #334155;
            border-radius: 16px;
            text-align: center;
            font-size: 13px;
        }

        .fab {
            position: fixed;
            left: 50%;
            bottom: max(16px, env(safe-area-inset-bottom));
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            width: min(calc(100% - 32px), 420px);
            min-height: 56px;
            border-radius: 18px;
            background: #2563eb;
            color: #fff;
            font-weight: 850;
            box-shadow:
                0 16px 50px rgba(37, 99, 235, .38);
        }

        @media (max-width: 719px) {
            .shell {
                padding-top: 14px;
                padding-bottom:
                    calc(
                        92px
                        + env(safe-area-inset-bottom)
                    );
            }

            .topbar {
                align-items: flex-start;
            }

            .hero {
                margin-top: 8px;
            }

            .stats {
                grid-template-columns:
                    repeat(3, minmax(0, 1fr));
                overflow: visible;
                gap: 8px;
                padding-bottom: 0;
                scroll-snap-type: none;
            }

            .stat {
                min-width: 0;
                padding: 12px 10px;
                scroll-snap-align: none;
            }

            .stat-value {
                font-size: 25px;
            }

            .stat-label {
                font-size: 11px;
            }

            .item {
                padding: 15px;
            }

            .fab {
                left: auto;
                right: 16px;
                bottom:
                    max(
                        14px,
                        env(safe-area-inset-bottom)
                    );
                transform: none;
                width: auto;
                min-width: 164px;
                min-height: 50px;
                padding: 0 18px;
                border-radius: 999px;
                box-shadow:
                    0 12px 32px rgba(37, 99, 235, .32);
            }
        }

        @media (min-width: 720px) {
            .shell { padding-top: 30px; }

            .stats {
                grid-template-columns:
                    repeat(5, minmax(0, 1fr));
            }

            .quick { display: inline-flex; }
            .fab { display: none; }

            .two-column {
                display: grid;
                grid-template-columns:
                    minmax(0, 1.35fr)
                    minmax(0, .65fr);
                gap: 24px;
                align-items: start;
            }
        }

        @media (prefers-color-scheme: light) {
            body {
                background: #f8fafc;
                color: #0f172a;
            }

            .nav a,
            .date,
            .meta,
            .stat-label,
            .empty,
            .edit-field {
                color: #64748b;
            }

            .stat,
            .item {
                background: #fff;
                border-color: #e2e8f0;
            }

            .scope,
            .action,
            .task-edit summary,
            .priority-filter {
                background: #fff;
                color: #475569;
                border-color: #cbd5e1;
            }

            .scope.active,
            .nav > a[aria-current="page"] {
                background: #eff6ff;
                color: #1d4ed8;
                border-color: #60a5fa;
            }

            .more-menu[open] summary,
            .more-menu-panel {
                background: #fff;
                color: #0f172a;
                border-color: #cbd5e1;
            }

            .more-menu-panel a {
                color: #475569;
            }

            .more-menu-panel a:hover {
                background: #f1f5f9;
            }

            .two-column main > .section:first-child .section-head h2 {
                color: #b91c1c;
            }

            .two-column main > .section:first-child .item:first-child {
                border-color: #fecaca;
                box-shadow:
                    0 8px 24px rgba(185, 28, 28, .07);
            }

            a.stat:hover {
                border-color: #93c5fd;
            }

            .pill {
                background: #f1f5f9;
                color: #475569;
            }

            .pill.critical,
            .pill.overdue {
                background: #fef2f2;
                color: #b91c1c;
            }

            .pill.today {
                background: #eff6ff;
                color: #1d4ed8;
            }

            .pill.week {
                background: #fffbeb;
                color: #a16207;
            }

            .action.done,
            .success {
                background: #f0fdf4;
                color: #166534;
            }

            .edit-form {
                background: #f8fafc;
            }

            .search-input {
                background: #fff;
                color: #0f172a;
                border-color: #cbd5e1;
            }

            .waiting-form input,
            .edit-field input,
            .edit-field select {
                background: #fff;
                color: #0f172a;
                border-color: #cbd5e1;
            }
        }
    </style>
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <x-operational-nav active="daily" />
    </div>

    @if (session('daily_action_success'))
        <div class="success">
            {{ session('daily_action_success') }}
        </div>
    @endif

    <section class="hero">
        <div>
            <h1>Mi día</h1>

            <div class="date">
                {{ $now->locale('es')->translatedFormat(
                    'l d \d\e F',
                ) }}
            </div>
        </div>

        <a
            class="quick"
            href="{{ route('quick-capture.show') }}"
        >
            + Captura rápida
        </a>
    </section>

    <nav class="scopes" aria-label="Filtrar por ámbito">
        <a
            class="scope {{ $selectedScope ? '' : 'active' }}"
            href="{{ route(
                'daily-ops.show',
                array_filter([
                    'q' => $search !== ''
                        ? $search
                        : null,
                    'priority' =>
                        $selectedPriority,
                ]),
            ) }}"
        >
            Todos
        </a>

        @foreach ($organizations as $organization)
            <a
                class="scope {{
                    $selectedScope === $organization->id
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'daily-ops.show',
                    array_filter([
                        'scope' => $organization->id,
                        'q' => $search !== ''
                            ? $search
                            : null,
                        'priority' =>
                            $selectedPriority,
                    ]),
                ) }}"
            >
                {{ $organization->name }}
            </a>
        @endforeach
    </nav>

    @php
        $baseQuery = array_filter([
            'scope' => $selectedScope,
            'q' => $search !== '' ? $search : null,
        ]);

        $priorityLabels = [
            'critical' => 'Críticas',
            'today' => 'Hoy',
            'week' => 'Semana',
            'planned' => 'Planificadas',
        ];
    @endphp

    <section class="filters">
        <form
            class="search-form"
            method="GET"
            action="{{ route('daily-ops.show') }}"
        >
            @if ($selectedScope)
                <input
                    type="hidden"
                    name="scope"
                    value="{{ $selectedScope }}"
                >
            @endif

            @if ($selectedPriority)
                <input
                    type="hidden"
                    name="priority"
                    value="{{ $selectedPriority }}"
                >
            @endif

            <input
                class="search-input"
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar tarea..."
                autocomplete="off"
            >

            <button
                class="search-button"
                type="submit"
            >
                Buscar
            </button>
        </form>

        <nav
            class="priority-filters"
            aria-label="Filtrar por prioridad"
        >
            <a
                class="priority-filter {{
                    $selectedPriority
                        ? ''
                        : 'active'
                }}"
                href="{{ route(
                    'daily-ops.show',
                    $baseQuery,
                ) }}"
            >
                Todas
            </a>

            @foreach (
                $priorityLabels
                as $value => $label
            )
                <a
                    class="priority-filter {{
                        $selectedPriority === $value
                            ? 'active'
                            : ''
                    }}"
                    href="{{ route(
                        'daily-ops.show',
                        array_merge(
                            $baseQuery,
                            ['priority' => $value],
                        ),
                    ) }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if (
            $search !== ''
            || $selectedPriority
            || $selectedScope
        )
            <div class="filter-summary">
                <span>Filtros activos</span>

                @if ($search !== '')
                    <span>
                        · “{{ $search }}”
                    </span>
                @endif

                @if ($selectedPriority)
                    <span>
                        · {{
                            $priorityLabels[
                                $selectedPriority
                            ]
                        }}
                    </span>
                @endif

                <a
                    class="clear-filter"
                    href="{{ route('daily-ops.show') }}"
                >
                    Limpiar
                </a>
            </div>
        @endif
    </section>

    <section class="stats" aria-label="Resumen de prioridades">
        <a
            class="stat"
            href="{{ route('daily-ops.show', array_filter([
                'scope' => $selectedScope,
                'q' => $search !== '' ? $search : null,
                'priority' => 'critical',
            ])) }}"
        >
            <div class="stat-value danger-value">
                {{ $criticalCount }}
            </div>
            <div class="stat-label">Críticos</div>
        </a>

        <a
            class="stat"
            href="{{ route('daily-ops.show', array_filter([
                'scope' => $selectedScope,
                'q' => $search !== '' ? $search : null,
                'priority' => 'today',
            ])) }}"
        >
            <div class="stat-value today-value">
                {{ $priorityTodayCount }}
            </div>
            <div class="stat-label">Hoy</div>
        </a>

        <a
            class="stat"
            href="{{ route('daily-ops.show', array_filter([
                'scope' => $selectedScope,
                'q' => $search !== '' ? $search : null,
                'priority' => 'week',
            ])) }}"
        >
            <div class="stat-value">
                {{ $priorityWeekCount }}
            </div>
            <div class="stat-label">Semana</div>
        </a>

        <a
            class="stat"
            href="{{ route('daily-ops.show', array_filter([
                'scope' => $selectedScope,
                'q' => $search !== '' ? $search : null,
                'priority' => 'planned',
            ])) }}"
        >
            <div class="stat-value">
                {{ $plannedCount }}
            </div>
            <div class="stat-label">Planificados</div>
        </a>

        <div class="stat">
            <div class="stat-value">
                {{ $waitingCount }}
            </div>
            <div class="stat-label">En espera</div>
        </div>
    </section>

    @php
        $taskSections = [
            [
                'title' => 'Prioridad ahora',
                'tasks' => $nowTasks,
                'empty' => 'Nada requiere atención inmediata.',
            ],
            [
                'title' => 'Hoy',
                'tasks' => $todayTasks,
                'empty' => 'No quedan tareas para hoy.',
            ],
            [
                'title' => 'Esta semana',
                'tasks' => $upcomingTasks,
                'empty' => 'Sin tareas en los próximos 7 días.',
            ],
            [
                'title' => 'Planificados',
                'tasks' => $noDateTasks,
                'empty' => 'No hay tareas pendientes sin fecha.',
            ],
        ];
    @endphp

    <div class="two-column">
        <main>
            @foreach ($taskSections as $section)
                <section class="section">
                    <div class="section-head">
                        <h2>{{ $section['title'] }}</h2>

                        <a
                            class="section-link"
                            href="{{ url('/admin/tareas') }}"
                        >
                            Ver tareas
                        </a>
                    </div>

                    <div class="list">
                        @forelse ($section['tasks'] as $task)
                            @php
                                $isOverdue = $task->due_at
                                    && $task->due_at->isBefore(
                                        $now->startOfDay(),
                                    );

                                $band = $task
                                    ->display_priority_band;
                            @endphp

                            <div class="item" data-operational-card>
                                <div class="item-title">
                                    {{ $task->title }}
                                </div>

                                <div class="meta">
                                    {{ $task->organization?->name
                                        ?? 'Sin ámbito' }}

                                    @if ($task->due_at)
                                        ·
                                        {{ $task->due_at->format(
                                            'd/m/Y',
                                        ) }}
                                    @else
                                        · sin fecha
                                    @endif
                                </div>

                                <div class="pills">
                                    @if ($isOverdue)
                                        <span class="pill overdue">
                                            Vencida
                                        </span>
                                    @endif

                                    <span
                                        class="pill {{ $band }}"
                                    >
                                        {{
                                            $task
                                                ->display_priority_label
                                        }}
                                        ·
                                        {{
                                            $task
                                                ->display_priority_score
                                        }}
                                    </span>

                                    @if (in_array($task->urgency, ['high', 'critical'], true))
                                        <span class="pill">
                                            {{ $task->urgency === 'critical' ? 'Urgencia crítica' : 'Urgencia alta' }}
                                        </span>
                                    @endif

                                    @if (in_array($task->impact, ['high', 'critical'], true))
                                        <span class="pill">
                                            {{ $task->impact === 'critical' ? 'Impacto crítico' : 'Impacto alto' }}
                                        </span>
                                    @endif
                                </div>

                                <div class="actions">
                                    @foreach ([
                                        'complete' => '✓ Hecho',
                                        'tomorrow' => 'Mañana',
                                        'next_week' => '+1 semana',
                                    ] as $action => $label)
                                        <form
                                            class="action-form"
                                            method="POST"
                                            action="{{ route(
                                                'daily-task-action.update',
                                                $task,
                                            ) }}"
                                        >
                                            @csrf

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="{{ $action }}"
                                            >

                                            @if ($selectedScope)
                                                <input
                                                    type="hidden"
                                                    name="scope"
                                                    value="{{ $selectedScope }}"
                                                >
                                            @endif

                                            <button
                                                class="action {{
                                                    $action === 'complete'
                                                        ? 'done'
                                                        : ''
                                                }}"
                                                type="submit"
                                            >
                                                {{ $label }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>

                                <details class="task-edit">
                                    <summary>En espera</summary>

                                    <form
                                        class="waiting-form"
                                        method="POST"
                                        action="{{ route(
                                            'daily-task-waiting.wait',
                                            $task,
                                        ) }}"
                                    >
                                        @csrf

                                        @if ($selectedScope)
                                            <input
                                                type="hidden"
                                                name="scope"
                                                value="{{ $selectedScope }}"
                                            >
                                        @endif

                                        @if ($search !== '')
                                            <input
                                                type="hidden"
                                                name="q"
                                                value="{{ $search }}"
                                            >
                                        @endif

                                        @if ($selectedPriority)
                                            <input
                                                type="hidden"
                                                name="priority"
                                                value="{{ $selectedPriority }}"
                                            >
                                        @endif

                                        <input
                                            type="date"
                                            name="waiting_until"
                                            value="{{ $now->addDay()->format('Y-m-d') }}"
                                            required
                                        >

                                        <input
                                            type="text"
                                            name="waiting_reason"
                                            placeholder="Esperando respuesta, aprobación..."
                                            maxlength="255"
                                            required
                                        >

                                        <button
                                            class="wait-button"
                                            type="submit"
                                        >
                                            Poner en espera
                                        </button>
                                    </form>
                                </details>

                                <details class="task-edit">
                                    <summary>Editar</summary>

                                    <form
                                        class="edit-form"
                                        method="POST"
                                        action="{{ route(
                                            'daily-task-edit.update',
                                            $task,
                                        ) }}"
                                    >
                                        @csrf

                                        @if ($selectedScope)
                                            <input
                                                type="hidden"
                                                name="scope"
                                                value="{{ $selectedScope }}"
                                            >
                                        @endif

                                        <div class="edit-grid">
                                            <label class="edit-field full">
                                                Empresa / ámbito

                                                <select
                                                    name="organization_id"
                                                    required
                                                >
                                                    @foreach (
                                                        $organizations
                                                        as $organization
                                                    )
                                                        <option
                                                            value="{{ $organization->id }}"
                                                            @selected(
                                                                (string) $task->organization_id
                                                                === (string) $organization->id
                                                            )
                                                        >
                                                            {{ $organization->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>

                                            <label class="edit-field full">
                                                Fecha

                                                <input
                                                    type="date"
                                                    name="due_date"
                                                    value="{{ $task->due_at?->format('Y-m-d') }}"
                                                >
                                            </label>

                                            <label class="edit-field">
                                                Urgencia

                                                <select name="urgency">
                                                    @foreach ([
                                                        'low' => 'Baja',
                                                        'normal' => 'Normal',
                                                        'high' => 'Alta',
                                                        'critical' => 'Crítica',
                                                    ] as $value => $label)
                                                        <option
                                                            value="{{ $value }}"
                                                            @selected(
                                                                $task->urgency
                                                                === $value
                                                            )
                                                        >
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>

                                            <label class="edit-field">
                                                Impacto

                                                <select name="impact">
                                                    @foreach ([
                                                        'low' => 'Bajo',
                                                        'normal' => 'Normal',
                                                        'high' => 'Alto',
                                                        'critical' => 'Crítico',
                                                    ] as $value => $label)
                                                        <option
                                                            value="{{ $value }}"
                                                            @selected(
                                                                $task->impact
                                                                === $value
                                                            )
                                                        >
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </div>

                                        <button
                                            class="save-edit"
                                            type="submit"
                                        >
                                            Guardar cambios
                                        </button>
                                    </form>
                                </details>
                            </div>
                        @empty
                            <div class="empty">
                                {{ $section['empty'] }}
                            </div>
                        @endforelse
                    </div>
                </section>
            @endforeach

            <section class="section">
                <div class="section-head">
                    <h2>En espera</h2>

                    <span class="meta">
                        {{ $waitingCount }} pendientes
                    </span>
                </div>

                <div class="list">
                    @forelse ($waitingTasks as $task)
                        @php
                            $followUpDue = $task->waiting_until
                                && $task->waiting_until->lte(
                                    $now->toDateString(),
                                );
                        @endphp

                        <div class="item" data-operational-card>
                            <div class="item-title">
                                {{ $task->title }}
                            </div>

                            <div class="meta">
                                {{ $task->organization?->name
                                    ?? 'Sin ámbito' }}

                                @if ($task->waiting_reason)
                                    · {{ $task->waiting_reason }}
                                @endif
                            </div>

                            @if ($task->waiting_until)
                                <div class="meta {{
                                    $followUpDue
                                        ? 'waiting-due'
                                        : ''
                                }}">
                                    Seguimiento:
                                    {{ $task->waiting_until->format(
                                        'd/m/Y',
                                    ) }}
                                </div>
                            @endif

                            <form
                                method="POST"
                                action="{{ route(
                                    'daily-task-waiting.resume',
                                    $task,
                                ) }}"
                            >
                                @csrf

                                @if ($selectedScope)
                                    <input
                                        type="hidden"
                                        name="scope"
                                        value="{{ $selectedScope }}"
                                    >
                                @endif

                                @if ($search !== '')
                                    <input
                                        type="hidden"
                                        name="q"
                                        value="{{ $search }}"
                                    >
                                @endif

                                @if ($selectedPriority)
                                    <input
                                        type="hidden"
                                        name="priority"
                                        value="{{ $selectedPriority }}"
                                    >
                                @endif

                                <button
                                    class="resume-button"
                                    type="submit"
                                >
                                    Reactivar
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="empty">
                            No hay tareas en espera.
                        </div>
                    @endforelse
                </div>
            </section>
        </main>

        <aside>
            <section class="section">
                <div class="section-head">
                    <h2>Vencimientos</h2>

                    <a
                        class="section-link"
                        href="{{ route('obligation-ops.show') }}"
                    >
                        Ver todos
                    </a>
                </div>

                <div class="list">
                    @forelse (
                        $upcomingObligations
                        as $occurrence
                    )
                        <a
                            class="item"
                            href="{{ url('/admin/vencimientos') }}"
                        >
                            <div class="item-title">
                                {{ $occurrence->obligation?->name
                                    ?? 'Obligación' }}
                            </div>

                            <div class="meta">
                                {{ $occurrence->organization?->name
                                    ?? 'Sin ámbito' }}
                                ·
                                {{ $occurrence->due_date->format(
                                    'd/m/Y',
                                ) }}
                            </div>
                        </a>
                    @empty
                        <div class="empty">
                            Sin vencimientos próximos.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="section">
                <div class="section-head">
                    <h2>Incidentes abiertos</h2>

                    <a
                        class="section-link"
                        href="{{ url('/admin/incidentes') }}"
                    >
                        Ver todos
                    </a>
                </div>

                <div class="list">
                    @forelse ($openIncidents as $incident)
                        <a
                            class="item"
                            href="{{ url('/admin/incidentes') }}"
                        >
                            <div class="item-title">
                                {{ $incident->title }}
                            </div>

                            <div class="meta">
                                {{ $incident->organization?->name
                                    ?? 'Sin ámbito' }}
                            </div>

                            <div class="pills">
                                <span
                                    class="pill {{
                                        in_array(
                                            $incident->severity,
                                            ['critical', 'high'],
                                            true,
                                        )
                                            ? 'critical'
                                            : ''
                                    }}"
                                >
                                    {{ ucfirst(
                                        $incident->severity,
                                    ) }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <div class="empty">
                            No hay incidentes abiertos.
                        </div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>

<a
    class="fab"
    href="{{ route('quick-capture.show') }}"
>
    + Captura rápida
</a>
    <x-operational-theme />
    <x-operational-interactions />
</body>
</html>
