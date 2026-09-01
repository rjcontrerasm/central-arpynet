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

        .shell {
            width: min(100%, 1120px);
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .topbar,
        .hero,
        .section-head,
        .item-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .topbar {
            margin-bottom: 24px;
        }

        .brand {
            font-weight: 850;
            letter-spacing: -.03em;
        }

        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
            color: #94a3b8;
            font-size: 13px;
        }

        .hero {
            align-items: end;
            margin-bottom: 16px;
        }

        h1 {
            margin: 0;
            font-size: clamp(30px, 7vw, 46px);
            line-height: .98;
            letter-spacing: -.05em;
        }

        h2 {
            margin: 0;
            font-size: 18px;
            letter-spacing: -.02em;
        }

        .subtitle,
        .meta,
        .stat-label,
        .empty {
            color: #94a3b8;
        }

        .subtitle {
            margin-top: 7px;
            font-size: 13px;
        }

        .filters {
            display: grid;
            gap: 9px;
            margin-bottom: 20px;
        }

        .scroll {
            display: flex;
            gap: 7px;
            overflow-x: auto;
            padding-bottom: 2px;
        }

        .chip {
            flex: 0 0 auto;
            padding: 7px 10px;
            border: 1px solid #334155;
            border-radius: 999px;
            background: #0f172a;
            color: #cbd5e1;
            font-size: 11px;
            font-weight: 750;
        }

        .chip.active {
            border-color: #60a5fa;
            background: #172554;
            color: #dbeafe;
        }

        .stats {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-bottom: 22px;
        }

        .stat,
        .item,
        .money {
            border: 1px solid #24304b;
            background: #11182b;
        }

        .stat {
            padding: 13px;
            border-radius: 15px;
        }

        .stat-value {
            font-size: 27px;
            line-height: 1;
            font-weight: 850;
            letter-spacing: -.05em;
        }

        .stat-label {
            margin-top: 5px;
            font-size: 11px;
        }

        .section {
            margin-top: 24px;
        }

        .section-head {
            align-items: baseline;
            margin-bottom: 10px;
        }

        .section-link {
            color: #93c5fd;
            font-size: 12px;
            font-weight: 750;
        }

        .list {
            display: grid;
            gap: 9px;
        }

        .item {
            display: block;
            padding: 13px;
            border-radius: 15px;
        }

        .item-title {
            font-weight: 800;
            line-height: 1.3;
        }

        .meta {
            margin-top: 4px;
            font-size: 12px;
            line-height: 1.45;
        }

        .summary-detail {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px;
            margin-top: 7px;
            color: #64748b;
            font-size: 11px;
            font-weight: 650;
        }

        .summary-detail .dot {
            color: #94a3b8;
        }

        .pill {
            padding: 4px 8px;
            border-radius: 999px;
            background: #1e293b;
            color: #cbd5e1;
            font-size: 10px;
            font-weight: 800;
        }

        .pill.critical {
            background: #450a0a;
            color: #fecaca;
        }

        .pill.attention {
            background: #431407;
            color: #fed7aa;
        }

        .pill.watch {
            background: #422006;
            color: #fde68a;
        }

        .reason {
            margin-top: 5px;
            color: #b45309;
            font-size: 11px;
            font-weight: 650;
            line-height: 1.35;
        }

        .money-groups {
            display: grid;
            gap: 12px;
        }

        .money-group {
            display: grid;
            gap: 8px;
        }

        .money-title {
            color: #93c5fd;
            font-size: 11px;
            font-weight: 850;
        }

        .money-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .money {
            padding: 12px;
            border-radius: 14px;
        }

        .money-value {
            font-size: 18px;
            font-weight: 850;
        }

        .money-label {
            margin-top: 4px;
            color: #94a3b8;
            font-size: 10px;
        }

        .empty {
            padding: 18px;
            border: 1px dashed #334155;
            border-radius: 15px;
            text-align: center;
            font-size: 12px;
        }

        @media (min-width: 760px) {
            .stats {
                grid-template-columns:
                    repeat(7, minmax(0, 1fr));
            }

            .two-column {
                display: grid;
                grid-template-columns:
                    minmax(0, 1.35fr)
                    minmax(0, .65fr);
                gap: 22px;
                align-items: start;
            }

            .money-grid {
                grid-template-columns:
                    repeat(3, minmax(0, 1fr));
            }
        }

        @media (prefers-color-scheme: light) {
            body {
                background: #f8fafc;
                color: #0f172a;
            }

            .nav,
            .subtitle,
            .meta,
            .stat-label,
            .empty,
            .money-label,
            .summary-detail {
                color: #64748b;
            }

            .stat,
            .item,
            .money {
                background: #fff;
                border-color: #e2e8f0;
            }

            .chip {
                background: #fff;
                color: #475569;
                border-color: #cbd5e1;
            }

            .chip.active {
                background: #eff6ff;
                color: #1d4ed8;
                border-color: #60a5fa;
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

            <a href="{{ route('executive-summary.show') }}">
                Resumen
            </a>

            <a href="{{ route('global-tracking.show') }}">
                Seguimiento
            </a>

            <a href="{{ route('service-orders-ops.show') }}">
                Servicios
            </a>

            <a href="{{ route('obligation-ops.show') }}">
                Vencimientos
            </a>

            <a href="{{ route('quick-capture.show') }}">
                Captura
            </a>

            <a href="{{ url('/admin') }}">
                Panel →
            </a>
        </nav>
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

    <div class="two-column">
        <main>
            <section class="section">
                <div class="section-head">
                    <h2>Atender primero</h2>

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
                            Nada requiere atención especial.
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
                        href="{{ url('/admin/proyectos') }}"
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
                            href="{{ url('/admin/proyectos') }}"
                        >
                            <div class="item-title">
                                {{ $project->name }}
                            </div>

                            <div class="meta">
                                Avance
                                {{ $project->progress_percent }}%
                                ·
                                {{ $project->stagnation_label }}
                            </div>
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
</body>
</html>
