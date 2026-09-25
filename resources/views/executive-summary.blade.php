<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="color-scheme" content="light dark">
    <title>Resumen · Central ARPYNET</title>

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/executive-summary.css') }}?v=2.39.2"
    >
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <x-operational-nav active="summary" />
    </div>

    <section class="hero">
        <div>
            <h1>
                {{ $period === 'week'
                    ? 'Próximos 7 días'
                    : 'Resumen de hoy' }}
            </h1>

            <div class="subtitle">
                {{ $summary['start']->format('d/m/Y') }}
                @if ($period === 'week')
                    →
                    {{ $summary['end']->format('d/m/Y') }}
                @endif
            </div>
        </div>
    </section>

    <section class="filters">
        <div class="scroll">
            <a
                class="chip {{
                    $period === 'today'
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'executive-summary.show',
                    array_filter([
                        'scope' => $selectedScope,
                        'period' => 'today',
                    ]),
                ) }}"
            >
                Hoy
            </a>

            <a
                class="chip {{
                    $period === 'week'
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'executive-summary.show',
                    array_filter([
                        'scope' => $selectedScope,
                        'period' => 'week',
                    ]),
                ) }}"
            >
                Próximos 7 días
            </a>
        </div>

        <div class="scroll">
            <a
                class="chip {{
                    $selectedScope ? '' : 'active'
                }}"
                href="{{ route(
                    'executive-summary.show',
                    ['period' => $period],
                ) }}"
            >
                Todos los ámbitos
            </a>

            @foreach ($organizations as $organization)
                <a
                    class="chip {{
                        $selectedScope === $organization->id
                            ? 'active'
                            : ''
                    }}"
                    href="{{ route(
                        'executive-summary.show',
                        [
                            'scope' => $organization->id,
                            'period' => $period,
                        ],
                    ) }}"
                >
                    {{ $organization->name }}
                </a>
            @endforeach
        </div>
    </section>

    <section class="stats">
        @foreach ([
            'Críticos' => $summary['counts']['critical'],
            'A vigilar' => $summary['counts']['attention'],
            'Tareas' => $summary['counts']['tasks_due'],
            'Seguimientos' => $summary['counts']['waiting_followups'],
            'Acciones servicio' => $summary['counts']['service_actions'],
            'Vencimientos' => $summary['counts']['obligations'],
            'Proyectos' => $summary['counts']['projects'],
        ] as $label => $value)
            <div class="stat">
                <div class="stat-value">
                    {{ $value }}
                </div>

                <div class="stat-label">
                    {{ $label }}
                </div>
            </div>
        @endforeach
    </section>

    <section class="decision-section">
        <div class="section-head">
            <h2>Decidir ahora</h2>

            <div class="decision-head-actions">
                <span class="meta">
                    {{ $summary['counts']['decisions'] }}
                    decisiones
                </span>

                <a
                    class="section-link"
                    href="{{ route(
                        'decision-inbox.index',
                        array_filter([
                            'scope' => $selectedScope,
                        ]),
                    ) }}"
                >
                    Abrir bandeja →
                </a>
            </div>
        </div>

        <div class="decision-grid">
            @forelse (
                $summary['decisions']
                as $decision
            )
                <a
                    class="decision-card"
                    href="{{ $decision['url'] }}"
                    data-operational-card
                >
                    <div class="item-head">
                        <div>
                            <div class="item-title">
                                {{ $decision['title'] }}
                            </div>

                            <div class="meta">
                                {{ $decision['organization'] }}
                                ·
                                {{ $decision['type_label'] }}
                            </div>
                        </div>

                        <span
                            class="pill {{
                                $decision['level']
                            }}"
                        >
                            {{ $decision['level_label'] }}
                        </span>
                    </div>

                    <div class="decision-action">
                        {{
                            $decision[
                                'recommended_action'
                            ]
                        }}
                        →
                    </div>

                    <div class="decision-reason">
                        {{
                            $decision[
                                'decision_reason'
                            ]
                        }}
                    </div>
                </a>
            @empty
                <div class="empty">
                    No hay decisiones operativas urgentes.
                </div>
            @endforelse
        </div>
    </section>

    <div class="two-column">
        <main>
            <section class="section">
                <div class="section-head">
                    <h2>Otras alertas</h2>

                    <a
                        class="section-link"
                        href="{{ route('global-tracking.show') }}"
                    >
                        Ver seguimiento →
                    </a>
                </div>

                <div class="list">
                    @forelse (
                        $summary['attention']
                        as $item
                    )
                        <a
                            class="item"
                            href="{{ $item['url'] }}"
                        >
                            <div class="item-head">
                                <div>
                                    <div class="item-title">
                                        {{ $item['title'] }}
                                    </div>

                                    <div class="meta">
                                        {{ $item['organization'] }}
                                        ·
                                        {{ $item['type_label'] }}
                                    </div>
                                </div>

                                <span
                                    class="pill {{
                                        $item['level']
                                    }}"
                                >
                                    {{ $item['level_label'] }}
                                </span>
                            </div>

                            <div class="summary-detail">
                                <span>
                                    {{ $item['meta'] }}
                                </span>

                                @if ($item['date_label'])
                                    <span class="dot">·</span>

                                    <span>
                                        {{ $item['date_label'] }}
                                    </span>
                                @endif
                            </div>

                            @if (! empty($item['reasons']))
                                <div class="reason">
                                    {{ implode(
                                        ' · ',
                                        array_slice(
                                            $item['reasons'],
                                            0,
                                            2,
                                        ),
                                    ) }}
                                </div>
                            @endif
                        </a>
                    @empty
                        <div class="empty">
                            No hay alertas adicionales fuera de
                            “Decidir ahora”.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="section">
                <div class="section-head">
                    <h2>Tareas con fecha</h2>

                    <a
                        class="section-link"
                        href="{{ route('daily-ops.show') }}"
                    >
                        Mi día →
                    </a>
                </div>

                <div class="list">
                    @forelse (
                        $summary['due_tasks']
                        as $task
                    )
                        <a
                            class="item"
                            href="{{ route(
                                'daily-ops.show',
                                [
                                    'scope' =>
                                        $task->organization_id,
                                ],
                            ) }}"
                        >
                            <div class="item-title">
                                {{ $task->title }}
                            </div>

                            <div class="meta">
                                {{ $task->organization?->name }}
                                ·
                                {{ $task->due_at->format(
                                    'd/m/Y H:i',
                                ) }}
                            </div>
                        </a>
                    @empty
                        <div class="empty">
                            Sin tareas con vencimiento en el periodo.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="section">
                <div class="section-head">
                    <h2>Seguimientos en espera</h2>
                </div>

                <div class="list">
                    @forelse (
                        $summary['waiting_followups']
                        as $task
                    )
                        <a
                            class="item"
                            href="{{ route(
                                'daily-ops.show',
                                [
                                    'scope' =>
                                        $task->organization_id,
                                ],
                            ) }}"
                        >
                            <div class="item-title">
                                {{ $task->title }}
                            </div>

                            <div class="meta">
                                {{ $task->waiting_reason
                                    ?: 'En espera' }}
                                ·
                                {{ $task->waiting_until->format(
                                    'd/m/Y',
                                ) }}
                            </div>
                        </a>
                    @empty
                        <div class="empty">
                            Sin seguimientos pendientes en el periodo.
                        </div>
                    @endforelse
                </div>
            </section>
        </main>

        <aside>
            <section class="section">
                <div class="section-head">
                    <h2>Servicios</h2>

                    <a
                        class="section-link"
                        href="{{ route(
                            'service-orders-ops.show'
                        ) }}"
                    >
                        Ver servicios →
                    </a>
                </div>

                <div class="list">
                    @forelse (
                        $summary['service_actions']
                        as $order
                    )
                        <a
                            class="item"
                            href="{{ route(
                                'service-orders-ops.show',
                                [
                                    'scope' =>
                                        $order->organization_id,
                                ],
                            ) }}"
                        >
                            <div class="item-title">
                                {{ $order->title }}
                            </div>

                            <div class="meta">
                                {{ $order->next_action
                                    ?: 'Sin siguiente acción' }}
                                ·
                                {{ $order->next_action_at->format(
                                    'd/m/Y H:i',
                                ) }}
                            </div>
                        </a>
                    @empty
                        <div class="empty">
                            Sin acciones de servicio en el periodo.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="section">
                <div class="section-head">
                    <h2>Vencimientos</h2>

                    <a
                        class="section-link"
                        href="{{ route(
                            'obligation-ops.show'
                        ) }}"
                    >
                        Ver vencimientos →
                    </a>
                </div>

                <div class="list">
                    @forelse (
                        $summary['obligations']
                        as $occurrence
                    )
                        <a
                            class="item"
                            href="{{ route(
                                'obligation-ops.show',
                                [
                                    'scope' =>
                                        $occurrence->organization_id,
                                ],
                            ) }}"
                        >
                            <div class="item-title">
                                {{ $occurrence->obligation?->name }}
                            </div>

                            <div class="meta">
                                {{ $occurrence->due_date->format(
                                    'd/m/Y',
                                ) }}
                                @if (
                                    $occurrence->expected_amount
                                    !== null
                                )
                                    ·
                                    {{ $occurrence->currency }}
                                    {{ number_format(
                                        (float)
                                            $occurrence->expected_amount,
                                        2,
                                        '.',
                                        ',',
                                    ) }}
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="empty">
                            Sin vencimientos en el periodo.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="section">
                <div class="section-head">
                    <h2>Proyectos a revisar</h2>

                    <a
                        class="section-link"
                        href="{{ route('project-ops.show') }}"
                    >
                        Ver proyectos →
                    </a>
                </div>

                <div class="list">
                    @forelse (
                        $summary['projects']
                        as $project
                    )
                        <a
                            class="item"
                            href="{{ route(
                                'project-ops.show',
                                [
                                    'scope' =>
                                        $project->organization_id,
                                    'focus' => 'all',
                                ],
                            ) }}"
                        >
                            <div class="item-head">
                                <div>
                                    <div class="item-title">
                                        {{ $project->name }}
                                    </div>

                                    <div class="meta">
                                        {{ $project->organization?->name
                                            ?? 'Sin ámbito' }}
                                    </div>
                                </div>

                                <span class="pill">
                                    Avance
                                    {{ $project->progress_percent }}%
                                </span>
                            </div>

                            <div class="summary-detail">
                                <span>
                                    {{ $project->stagnation_label }}
                                </span>

                                @if ($project->target_date)
                                    <span class="dot">·</span>

                                    <span>
                                        Objetivo
                                        {{ $project->target_date->format(
                                            'd/m/Y',
                                        ) }}
                                    </span>
                                @endif
                            </div>

                            @if ($project->next_action)
                                <div class="decision-action">
                                    Siguiente:
                                    {{ $project->next_action }}
                                    →
                                </div>
                            @else
                                <div class="reason">
                                    Sin próxima acción definida.
                                </div>
                            @endif

                            @if ($project->blockers)
                                <div class="reason">
                                    Bloqueo:
                                    {{ $project->blockers }}
                                </div>
                            @endif
                        </a>
                    @empty
                        <div class="empty">
                            Sin proyectos que requieran revisión.
                        </div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>

    <x-client-health
        :clients="$summary['client_health']"
    />

    <x-executive-finance
        :finance="$summary['executive_finance']"
    />

    <section class="section">
        <div class="section-head">
            <h2>Resumen financiero</h2>
        </div>

        <div class="money-groups">
            @foreach (
                $summary['service_financial']
                as $currency => $money
            )
                <div class="money-group">
                    <div class="money-title">
                        Servicios · {{ $currency }}
                    </div>

                    <div class="money-grid">
                        @foreach ([
                            'Facturado' => $money['invoiced'],
                            'Por cobrar' => $money['receivable'],
                            'Vencido' => $money['overdue'],
                        ] as $label => $value)
                            <div class="money">
                                <div class="money-value">
                                    {{ $currency }}
                                    {{ number_format(
                                        $value,
                                        2,
                                        '.',
                                        ',',
                                    ) }}
                                </div>

                                <div class="money-label">
                                    {{ $label }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @foreach (
                $summary['obligation_financial']
                as $currency => $money
            )
                <div class="money-group">
                    <div class="money-title">
                        Obligaciones · {{ $currency }}
                    </div>

                    <div class="money-grid">
                        @foreach ([
                            'Pendiente' => $money['pending'],
                            'Vencido' => $money['overdue'],
                        ] as $label => $value)
                            <div class="money">
                                <div class="money-value">
                                    {{ $currency }}
                                    {{ number_format(
                                        $value,
                                        2,
                                        '.',
                                        ',',
                                    ) }}
                                </div>

                                <div class="money-label">
                                    {{ $label }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if (
                $summary['service_financial']->isEmpty()
                && $summary['obligation_financial']->isEmpty()
            )
                <div class="empty">
                    Aún no hay movimientos financieros registrados.
                </div>
            @endif
        </div>
    </section>
</div>
    <x-operational-theme />
    <x-operational-interactions />
</body>
</html>
