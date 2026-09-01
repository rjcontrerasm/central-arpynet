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
            width: min(100%, 1020px);
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
            gap: 14px;
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
            scrollbar-width: thin;
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
            margin-top: 9px;
            padding-top: 9px;
            border-top: 1px solid #24304b;
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

            .scope.active {
                background: #eff6ff;
                color: #1d4ed8;
                border-color: #60a5fa;
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

        <nav class="nav">
            <a href="{{ route('daily-ops.show') }}">
                Mi día
            </a>

            <a href="{{ route('quick-capture.show') }}">
                Captura
            </a>

            <a href="{{ route('service-orders-ops.show') }}">
                Servicios
            </a>

            <a href="{{ route('obligation-ops.show') }}">
                Vencimientos
            </a>

            <a href="{{ route('global-tracking.show') }}">
                Seguimiento
            </a>

            <a href="{{ route('executive-summary.show') }}">
                Resumen
            </a>

            <a href="{{ route('notification-center.index') }}">
                Notificaciones
            </a>

            <a href="{{ url('/admin') }}">
                Panel →
            </a>
        </nav>
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

    <section class="stats">
        <div class="stat">
            <div class="stat-value danger-value">
                {{ $overdueCount }}
            </div>
            <div class="stat-label">Vencidas</div>
        </div>

        <div class="stat">
            <div class="stat-value today-value">
                {{ $todayCount }}
            </div>
            <div class="stat-label">Para hoy</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $weekCount }}
            </div>
            <div class="stat-label">Próximos 7 días</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $noDateCount }}
            </div>
            <div class="stat-label">Sin fecha</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $waitingCount }}
            </div>
            <div class="stat-label">En espera</div>
        </div>
    </section>

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

                <div class="item">
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

    @php
        $taskSections = [
            [
                'title' => 'Ahora',
                'tasks' => $nowTasks,
                'empty' => 'Nada crítico o vencido.',
            ],
            [
                'title' => 'Hoy',
                'tasks' => $todayTasks,
                'empty' => 'No quedan tareas para hoy.',
            ],
            [
                'title' => 'Próximos',
                'tasks' => $upcomingTasks,
                'empty' => 'Sin tareas en los próximos 7 días.',
            ],
            [
                'title' => 'Sin fecha',
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

                            <div class="item">
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

                                    @if ($task->urgency === 'high')
                                        <span class="pill">
                                            Urgencia alta
                                        </span>
                                    @endif

                                    @if ($task->impact === 'high')
                                        <span class="pill">
                                            Impacto alto
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
                                                        'medium' => 'Media',
                                                        'high' => 'Alta',
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
                                                        'medium' => 'Medio',
                                                        'high' => 'Alto',
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
</body>
</html>
