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

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/daily-ops.css') }}?v=2.39.3"
    >
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <x-operational-nav active="daily" />
    </div>

    @if (session('daily_action_success'))
        @php
            $undoSnapshot = session(
                'daily_action_undo',
            );

            $canUndo =
                is_array($undoSnapshot)
                && (int) (
                    $undoSnapshot[
                        'expires_at'
                    ] ?? 0
                ) >= time();
        @endphp

        <div class="success">
            <div class="success-row">
                <span>
                    {{ session('daily_action_success') }}
                </span>

                @if ($canUndo)
                    <form
                        class="undo-form"
                        method="POST"
                        action="{{ route(
                            'daily-task-action.undo',
                        ) }}"
                    >
                        @csrf

                        <button
                            class="undo-button"
                            type="submit"
                            data-busy-label="Deshaciendo…"
                        >
                            Deshacer
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @php
        $currentUser = auth()->user();
        $canQuickCapture = $currentUser
            && (
                $selectedScope
                    ? $currentUser->canWriteToOrganization(
                        (int) $selectedScope,
                    )
                    : ! empty($currentUser->writableOrganizationIds())
            );

        $workViewLabels = [
            'mine' => 'Mi bandeja',
            'team' => 'Mi equipo',
            'unassigned' => 'Sin asignar',
        ];
    @endphp

    <section class="hero">
        <div>
            <h1>Mi día</h1>

            <div class="date">
                {{ $now->locale('es')->translatedFormat(
                    'l d \d\e F',
                ) }}
            </div>
        </div>

        @if ($canQuickCapture)
            <a
                class="quick"
                href="{{ route('quick-capture.show') }}"
            >
                + Captura rápida
            </a>
        @endif
    </section>

    <nav class="scopes" aria-label="Vista de trabajo">
        @foreach ($workViewLabels as $value => $label)
            <a
                class="scope {{
                    $selectedWorkView === $value
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'daily-ops.show',
                    array_filter([
                        'view' => $value,
                        'scope' => $selectedScope,
                        'q' => $search !== ''
                            ? $search
                            : null,
                        'priority' => $selectedPriority,
                        'recurring_rule' =>
                            $selectedRecurringRule,
                    ]),
                ) }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <nav class="scopes" aria-label="Filtrar por ámbito">
        <a
            class="scope {{ $selectedScope ? '' : 'active' }}"
            href="{{ route(
                'daily-ops.show',
                array_filter([
                    'view' => $selectedWorkView,
                    'q' => $search !== ''
                        ? $search
                        : null,
                    'priority' =>
                        $selectedPriority,
                    'recurring_rule' =>
                        $selectedRecurringRule,
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
                        'view' => $selectedWorkView,
                        'scope' => $organization->id,
                        'q' => $search !== ''
                            ? $search
                            : null,
                        'priority' =>
                            $selectedPriority,
                        'recurring_rule' =>
                            $selectedRecurringRule,
                    ]),
                ) }}"
            >
                {{ $organization->name }}
            </a>
        @endforeach
    </nav>

    @php
        $baseQuery = array_filter([
            'view' => $selectedWorkView,
            'scope' => $selectedScope,
            'q' => $search !== '' ? $search : null,
            'recurring_rule' =>
                $selectedRecurringRule,
        ]);

        $priorityLabels = [
            'overdue' => 'Vencidas',
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
            <input
                type="hidden"
                name="view"
                value="{{ $selectedWorkView }}"
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

            @if ($selectedRecurringRule)
                <input
                    type="hidden"
                    name="recurring_rule"
                    value="{{ $selectedRecurringRule }}"
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
            || $selectedRecurringRule
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

                @if ($selectedRecurringRule)
                    <span>
                        · Recurrencia seleccionada
                    </span>
                @endif

                <a
                    class="clear-filter"
                    href="{{ route(
                        'daily-ops.show',
                        ['view' => $selectedWorkView],
                    ) }}"
                >
                    Limpiar
                </a>
            </div>
        @endif
    </section>

    <section class="focus-panel" aria-label="Foco del día">
        <div>
            <div class="focus-eyebrow">Foco del día</div>

            @if ($overdueCount > 0)
                <h2 class="focus-title">
                    {{ $overdueCount }}
                    {{ $overdueCount === 1 ? 'tarea vencida requiere' : 'tareas vencidas requieren' }}
                    revisión
                </h2>
            @elseif ($criticalCount > 0)
                <h2 class="focus-title">
                    {{ $criticalCount }}
                    {{ $criticalCount === 1 ? 'tarea crítica requiere' : 'tareas críticas requieren' }}
                    atención inmediata
                </h2>
            @elseif ($priorityTodayCount > 0)
                <h2 class="focus-title">
                    {{ $priorityTodayCount }}
                    {{ $priorityTodayCount === 1 ? 'tarea para resolver' : 'tareas para resolver' }}
                    hoy
                </h2>
            @else
                <h2 class="focus-title">
                    Sin urgencias en tu bandeja
                </h2>
            @endif

            <div class="focus-meta">
                {{ $criticalCount }} críticas
                · {{ $waitingCount }} en espera
                · {{ $projectsAttentionCount }} proyectos a revisar
                · {{ $upcomingObligations->count() }} vencimientos próximos
            </div>
        </div>

        <div class="focus-actions">
            @if ($overdueCount > 0)
                <a
                    class="focus-action"
                    href="{{ route('daily-ops.show', array_filter([
                        'view' => $selectedWorkView,
                        'scope' => $selectedScope,
                        'q' => $search !== '' ? $search : null,
                        'priority' => 'overdue',
                        'recurring_rule' =>
                            $selectedRecurringRule,
                    ])) }}"
                >
                    Ver vencidas
                </a>
            @elseif ($criticalCount > 0)
                <a
                    class="focus-action"
                    href="{{ route('daily-ops.show', array_filter([
                        'view' => $selectedWorkView,
                        'scope' => $selectedScope,
                        'q' => $search !== '' ? $search : null,
                        'priority' => 'critical',
                        'recurring_rule' =>
                            $selectedRecurringRule,
                    ])) }}"
                >
                    Ver críticas
                </a>
            @elseif ($priorityTodayCount > 0)
                <a
                    class="focus-action"
                    href="{{ route('daily-ops.show', array_filter([
                        'view' => $selectedWorkView,
                        'scope' => $selectedScope,
                        'q' => $search !== '' ? $search : null,
                        'priority' => 'today',
                        'recurring_rule' =>
                            $selectedRecurringRule,
                    ])) }}"
                >
                    Ver tareas de hoy
                </a>
            @endif

            <a
                class="focus-action secondary"
                href="{{ route('operational-agenda.show') }}"
            >
                Abrir agenda
            </a>

            @if ($canQuickCapture)
                <a
                    class="focus-action secondary"
                    href="{{ route('quick-capture.show') }}"
                >
                    + Capturar
                </a>
            @endif
        </div>
    </section>

    <section class="stats" aria-label="Resumen de prioridades">
        <a
            class="stat"
            href="{{ route('daily-ops.show', array_filter([
                'view' => $selectedWorkView,
                'scope' => $selectedScope,
                'q' => $search !== '' ? $search : null,
                'priority' => 'overdue',
                'recurring_rule' =>
                    $selectedRecurringRule,
            ])) }}"
        >
            <div class="stat-value danger-value">
                {{ $overdueCount }}
            </div>
            <div class="stat-label">Vencidas</div>
        </a>

        <a
            class="stat"
            href="{{ route('daily-ops.show', array_filter([
                'view' => $selectedWorkView,
                'scope' => $selectedScope,
                'q' => $search !== '' ? $search : null,
                'priority' => 'critical',
                'recurring_rule' =>
                    $selectedRecurringRule,
            ])) }}"
        >
            <div class="stat-value critical-value">
                {{ $criticalCount }}
            </div>
            <div class="stat-label">Críticas</div>
        </a>

        <a
            class="stat"
            href="{{ route('daily-ops.show', array_filter([
                'view' => $selectedWorkView,
                'scope' => $selectedScope,
                'q' => $search !== '' ? $search : null,
                'priority' => 'today',
                'recurring_rule' =>
                    $selectedRecurringRule,
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
                'view' => $selectedWorkView,
                'scope' => $selectedScope,
                'q' => $search !== '' ? $search : null,
                'priority' => 'week',
                'recurring_rule' =>
                    $selectedRecurringRule,
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
                'view' => $selectedWorkView,
                'scope' => $selectedScope,
                'q' => $search !== '' ? $search : null,
                'priority' => 'planned',
                'recurring_rule' =>
                    $selectedRecurringRule,
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
                'id' => 'prioridad-critica',
                'title' => 'Prioridad crítica',
                'tasks' => $criticalNowTasks,
                'empty' => 'No hay tareas críticas adicionales.',
                'hide_when_empty' => true,
            ],
            [
                'id' => 'hoy',
                'title' => 'Hoy',
                'tasks' => $todayTasks,
                'empty' => 'No quedan tareas para hoy.',
            ],
            [
                'id' => 'esta-semana',
                'title' => 'Esta semana',
                'tasks' => $upcomingTasks,
                'empty' => 'Sin tareas en los próximos 7 días.',
            ],
            [
                'id' => 'planificados',
                'title' => 'Planificados',
                'tasks' => $noDateTasks,
                'empty' => 'No hay tareas pendientes sin fecha.',
            ],
        ];
    @endphp

    <div class="two-column">
        <main>
            <section
                class="section"
                id="vencidas"
            >
                <div class="section-head">
                    <h2>Vencidas</h2>

                    @if (
                        $overdueCount > 0
                        && ! $showAllOverdue
                    )
                        <a
                            class="section-link"
                            href="{{ route(
                                'daily-ops.show',
                                array_filter([
                                    'view' =>
                                        $selectedWorkView,
                                    'scope' =>
                                        $selectedScope,
                                    'q' =>
                                        $search !== ''
                                            ? $search
                                            : null,
                                    'priority' =>
                                        'overdue',
                                    'recurring_rule' =>
                                        $selectedRecurringRule,
                                ]),
                            ) }}"
                        >
                            Ver las {{ $overdueCount }}
                        </a>
                    @endif
                </div>

                <div class="list">
                    @forelse (
                        $visibleOverdueGroups
                        as $row
                    )
                        @if ($row['type'] === 'recurring')
                            @include(
                                'partials.daily-overdue-recurring-group',
                                ['row' => $row],
                            )
                        @else
                            @include(
                                'partials.daily-task-card',
                                ['task' => $row['task']],
                            )
                        @endif
                    @empty
                        <div class="empty">
                            No hay tareas vencidas pendientes.
                        </div>
                    @endforelse
                </div>

                @if (
                    $overdueCount > 0
                    && ! $showAllOverdue
                )
                    <div class="meta overdue-summary">
                        Mostrando {{
                            $visibleOverdueGroups
                                ->sum('count')
                        }}
                        de {{ $overdueCount }}
                        tarea(s) vencida(s), agrupando
                        recurrencias repetidas.
                    </div>
                @endif
            </section>

            @foreach ($taskSections as $section)
                @if (
                    ($section['hide_when_empty'] ?? false)
                    && $section['tasks']->isEmpty()
                )
                    @continue
                @endif

                @php
                    $sectionPriority = [
                        'prioridad-critica' =>
                            'critical',
                        'hoy' => 'today',
                        'esta-semana' => 'week',
                        'planificados' =>
                            'planned',
                    ][$section['id']] ?? null;
                @endphp

                <section
                    class="section"
                    id="{{ $section['id'] }}"
                >
                    <div class="section-head">
                        <h2>{{ $section['title'] }}</h2>

                        <a
                            class="section-link"
                            href="{{ route(
                                'daily-ops.show',
                                array_filter([
                                    'view' =>
                                        $selectedWorkView,
                                    'scope' =>
                                        $selectedScope,
                                    'q' =>
                                        $search !== ''
                                            ? $search
                                            : null,
                                    'priority' =>
                                        $sectionPriority,
                                    'recurring_rule' =>
                                        $selectedRecurringRule,
                                ]),
                            ) }}"
                        >
                            Ver tareas
                        </a>
                    </div>

                    <div class="list">
                        @forelse ($section['tasks'] as $task)
                            @include(
                                'partials.daily-task-card',
                                ['task' => $task],
                            )
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

                            $canWriteTask = $currentUser
                                ?->canWriteToOrganization(
                                    (int) $task->organization_id,
                                ) ?? false;
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

                                @if ($selectedWorkView === 'team')
                                    · Responsable:
                                    {{ $task->assignee?->name
                                        ?? 'Sin asignar' }}
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

                            @if ($canWriteTask)
                                <form
                                    method="POST"
                                    action="{{ route(
                                        'daily-task-waiting.resume',
                                        $task,
                                    ) }}"
                                >
                                    @csrf
                                    <input type="hidden" name="view" value="{{ $selectedWorkView }}">

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
                            @endif
                        </div>
                    @empty
                        <div class="empty">
                            No hay tareas en espera.
                        </div>
                    @endforelse
                </div>
            </section>
        </main>

        <aside aria-label="Contexto operativo">
            <section class="section">
                <div class="section-head">
                    <h2>Proyectos a revisar</h2>

                    <a
                        class="section-link"
                        href="{{ route('project-ops.show') }}"
                    >
                        Ver proyectos
                    </a>
                </div>

                <div class="list">
                    @forelse ($projectsAttention as $row)
                        @php
                            $project = $row['project'];
                            $signal = $row['signal'];
                        @endphp

                        <a
                            class="item"
                            href="{{ route(
                                'project-ops.show',
                                [
                                    'scope' => $project->organization_id,
                                    'focus' => 'all',
                                ],
                            ) }}"
                        >
                            <div class="item-title">
                                {{ $project->name }}
                            </div>

                            <div class="meta">
                                {{ $project->organization?->name
                                    ?? 'Sin ámbito' }}

                                @if ($project->target_date)
                                    · objetivo
                                    {{ $project->target_date->format(
                                        'd/m/Y',
                                    ) }}
                                @else
                                    · sin fecha objetivo
                                @endif
                            </div>

                            <div class="pills">
                                <span class="pill {{
                                    $signal['level'] === 'critical'
                                        ? 'critical'
                                        : 'week'
                                }}">
                                    {{ $signal['level_label'] }}
                                </span>

                                <span class="pill">
                                    Avance
                                    {{ $project->progress_percent }}%
                                </span>
                            </div>

                            @if ($project->next_action)
                                <div class="next-action-current">
                                    <strong>Siguiente:</strong>
                                    {{ $project->next_action }}
                                </div>
                            @endif

                            @if ($project->blockers)
                                <div class="meta waiting-due">
                                    Bloqueo:
                                    {{ $project->blockers }}
                                </div>
                            @endif
                        </a>
                    @empty
                        <div class="empty">
                            No hay proyectos que requieran revisión.
                        </div>
                    @endforelse
                </div>

                @if ($projectsAttentionCount > $projectsAttention->count())
                    <div class="meta" style="margin-top:8px">
                        Mostrando {{ $projectsAttention->count() }}
                        de {{ $projectsAttentionCount }} proyecto(s)
                        con señales operativas.
                    </div>
                @endif
            </section>

            <section class="section">
                <div class="section-head">
                    <h2>Órdenes y servicios</h2>

                    <a
                        class="section-link"
                        href="{{ route('service-orders-ops.show') }}"
                    >
                        Ver todos
                    </a>
                </div>

                <div class="list">
                    @forelse ($serviceOrders as $order)
                        <a
                            class="item"
                            href="{{ route('service-orders-ops.show') }}"
                        >
                            <div class="item-title">
                                {{ $order->title }}
                            </div>

                            <div class="meta">
                                {{ $order->organization?->name
                                    ?? 'Sin ámbito' }}
                                @if ($order->client?->name)
                                    · {{ $order->client->name }}
                                @endif
                                @if ($selectedWorkView === 'team')
                                    · Responsable:
                                    {{ $order->assignee?->name
                                        ?? 'Sin asignar' }}
                                @endif
                            </div>

                            <div class="pills">
                                <span class="pill">
                                    {{ \App\Models\ServiceOrder::stageOptions()[$order->stage]
                                        ?? ucfirst($order->stage) }}
                                </span>

                                @if ($order->next_action_at)
                                    <span class="pill {{
                                        $order->next_action_at->isPast()
                                            ? 'overdue'
                                            : 'today'
                                    }}">
                                        Seguimiento
                                        {{ $order->next_action_at->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>

                            @if ($order->next_action)
                                <div class="next-action-current">
                                    <strong>Siguiente:</strong>
                                    {{ $order->next_action }}
                                </div>
                            @endif
                        </a>
                    @empty
                        <div class="empty">
                            No hay órdenes o servicios pendientes.
                        </div>
                    @endforelse
                </div>
            </section>

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
                            href="{{ route('obligation-ops.show') }}"
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
                        href="{{ route('incident-360.index') }}"
                    >
                        Ver todos
                    </a>
                </div>

                <div class="list">
                    @forelse ($openIncidents as $incident)
                        <a
                            class="item"
                            href="{{ route('incident-360.index') }}"
                        >
                            <div class="item-title">
                                {{ $incident->title }}
                            </div>

                            <div class="meta">
                                {{ $incident->organization?->name
                                    ?? 'Sin ámbito' }}
                                @if ($selectedWorkView === 'team')
                                    · Responsable:
                                    {{ $incident->assignee?->name
                                        ?? 'Sin asignar' }}
                                @endif
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

@if ($canQuickCapture)
    <a
        class="fab"
        href="{{ route('quick-capture.show') }}"
    >
        + Captura rápida
    </a>
@endif
    <x-operational-theme />
    <x-operational-interactions />
</body>
</html>
