<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="color-scheme"
        content="light dark"
    >
    <title>Revisión semanal · Central ARPYNET</title>

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

        a { color: inherit; }

        .shell {
            width: min(100%, 1200px);
            margin: 0 auto;
            padding: 24px 16px 70px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .brand {
            font-weight: 850;
            letter-spacing: -.03em;
        }

        .hero {
            margin-top: 28px;
        }

        h1 {
            margin: 0;
            font-size: clamp(32px, 5vw, 48px);
            letter-spacing: -.055em;
        }

        .subtitle {
            margin-top: 7px;
            color: #94a3b8;
            font-size: 13px;
        }

        .progress {
            margin-top: 18px;
            padding: 14px;
            border: 1px solid #24304b;
            border-radius: 14px;
            background: #11182b;
        }

        .progress-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
            font-weight: 820;
        }

        .progress-track {
            height: 8px;
            margin-top: 10px;
            overflow: hidden;
            border-radius: 999px;
            background: #1e293b;
        }

        .progress-fill {
            height: 100%;
            border-radius: inherit;
            background: #3b82f6;
        }

        .success {
            margin-top: 14px;
            padding: 11px 13px;
            border: 1px solid #166534;
            border-radius: 12px;
            background: #052e16;
            color: #bbf7d0;
            font-size: 12px;
            font-weight: 760;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 18px;
        }

        .card {
            align-self: start;
            padding: 16px;
            border: 1px solid #24304b;
            border-radius: 16px;
            background: #11182b;
        }

        .card.reviewed {
            border-color: #166534;
        }

        .card-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }

        .card h2 {
            margin: 0;
            font-size: 18px;
            letter-spacing: -.025em;
        }

        .count {
            min-width: 34px;
            padding: 5px 8px;
            border-radius: 999px;
            background: #172554;
            color: #bfdbfe;
            text-align: center;
            font-size: 11px;
            font-weight: 850;
        }

        .description {
            margin-top: 6px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.45;
        }

        .mini-list {
            display: grid;
            gap: 7px;
            margin-top: 12px;
        }

        .mini {
            padding: 9px 10px;
            border: 1px solid #26334f;
            border-radius: 10px;
            background: #0f172a;
            font-size: 11px;
        }

        .mini strong {
            display: block;
            color: #e2e8f0;
        }

        .mini span {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
        }

        .metrics {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 12px;
        }

        .metric {
            padding: 7px 9px;
            border-radius: 9px;
            background: #0f172a;
            color: #cbd5e1;
            font-size: 11px;
        }

        .money {
            margin-top: 10px;
            color: #cbd5e1;
            font-size: 11px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .actions a,
        .actions button {
            min-height: 37px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 10px;
            border-radius: 9px;
            font: inherit;
            font-size: 11px;
            font-weight: 820;
            text-decoration: none;
            cursor: pointer;
        }

        .open-link {
            border: 1px solid #334155;
            background: #0f172a;
            color: #cbd5e1;
        }

        .review-button {
            border: 0;
            background: #2563eb;
            color: #fff;
        }

        .reviewed-label {
            padding: 8px 10px;
            border-radius: 9px;
            background: #052e16;
            color: #bbf7d0;
            font-size: 11px;
            font-weight: 820;
        }

        .note {
            margin-top: 18px;
            color: #64748b;
            font-size: 11px;
        }

        @media (max-width: 760px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-color-scheme: light) {
            body {
                background: #f8fafc;
                color: #0f172a;
            }

            .progress,
            .card,
            .mini,
            .metric,
            .open-link {
                background: #fff;
                border-color: #e2e8f0;
            }

            .mini strong {
                color: #0f172a;
            }
        }
    </style>
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">
            Central ARPYNET
        </div>

        <x-operational-nav active="weekly" />
    </div>

    <section class="hero">
        <h1>Revisión semanal</h1>

        <div class="subtitle">
            Semana del
            {{ $week_start->format('d/m') }}
            al
            {{ $week_end->format('d/m/Y') }}.
            Revisar no significa resolver: significa confirmar que cada frente fue evaluado.
        </div>

        <div class="progress">
            <div class="progress-top">
                <span>Progreso semanal</span>
                <span>
                    {{ $reviewedCount }}/5
                </span>
            </div>

            <div class="progress-track">
                <div
                    class="progress-fill"
                    style="width: {{ $reviewedCount * 20 }}%"
                ></div>
            </div>
        </div>

        @if (session('weekly_review_success'))
            <div class="success">
                {{ session('weekly_review_success') }}
            </div>
        @endif
    </section>

    <section class="grid">
        @foreach ($steps as $key => $step)
            <article
                class="card {{ $step['reviewed'] ? 'reviewed' : '' }}"
            >
                <div class="card-head">
                    <div>
                        <h2>{{ $step['title'] }}</h2>

                        <div class="description">
                            {{ $step['description'] }}
                        </div>
                    </div>

                    <div class="count">
                        {{ $step['count'] }}
                    </div>
                </div>

                @if ($key === 'carryover')
                    <div class="mini-list">
                        @foreach ($carryover_tasks->take(4) as $task)
                            <div class="mini">
                                <strong>{{ $task->title }}</strong>
                                <span>
                                    {{ $task->organization?->name }}
                                    · venció
                                    {{ $task->due_at?->format('d/m H:i') }}
                                </span>
                            </div>
                        @endforeach

                        @foreach ($waiting_overdue->take(3) as $task)
                            <div class="mini">
                                <strong>{{ $task->title }}</strong>
                                <span>
                                    En espera · seguimiento vencido
                                    {{ $task->waiting_until?->format('d/m H:i') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @elseif ($key === 'stagnation')
                    <div class="metrics">
                        <div class="metric">
                            Tareas: {{ $task_signals->count() }}
                        </div>

                        <div class="metric">
                            Proyectos: {{ $stagnant_projects->count() }}
                        </div>
                    </div>

                    <div class="mini-list">
                        @foreach ($task_signals->take(3) as $signal)
                            <div class="mini">
                                <strong>
                                    {{ $signal['task']->title }}
                                </strong>
                                <span>
                                    {{ implode(
                                        ' · ',
                                        $signal['reasons'],
                                    ) }}
                                </span>
                            </div>
                        @endforeach

                        @foreach ($stagnant_projects->take(3) as $project)
                            <div class="mini">
                                <strong>{{ $project->name }}</strong>
                                <span>
                                    Proyecto ·
                                    {{ $project->stagnation_days }}
                                    días sin movimiento
                                </span>
                            </div>
                        @endforeach
                    </div>
                @elseif ($key === 'finance')
                    <div class="metrics">
                        <div class="metric">
                            Por cobrar: {{ $receivables->count() }}
                        </div>

                        <div class="metric">
                            Vencidas: {{ $overdue_receivables->count() }}
                        </div>

                        <div class="metric">
                            Por facturar: {{ $ready_to_invoice->count() }}
                        </div>
                    </div>

                    @foreach ($receivable_totals as $currency => $amount)
                        <div class="money">
                            {{ $currency }} por cobrar:
                            <strong>
                                {{ number_format($amount, 2) }}
                            </strong>

                            @if (($overdue_totals[$currency] ?? 0) > 0)
                                · vencido
                                <strong>
                                    {{ number_format(
                                        $overdue_totals[$currency],
                                        2,
                                    ) }}
                                </strong>
                            @endif
                        </div>
                    @endforeach
                @elseif ($key === 'obligations')
                    <div class="metrics">
                        <div class="metric">
                            Vencidas: {{ $overdue_obligations->count() }}
                        </div>

                        <div class="metric">
                            Próximos 30 días: {{ $next30_obligations->count() }}
                        </div>
                    </div>

                    <div class="mini-list">
                        @foreach ($overdue_obligations->take(3) as $item)
                            <div class="mini">
                                <strong>
                                    {{ $item->obligation?->name ?? 'Vencimiento' }}
                                </strong>
                                <span>
                                    {{ $item->organization?->name }}
                                    · venció
                                    {{ $item->due_date->format('d/m/Y') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @elseif ($key === 'horizon')
                    <div class="metrics">
                        <div class="metric">
                            7 días: {{ $counts['horizon_7'] }}
                        </div>

                        <div class="metric">
                            30 días: {{ $counts['horizon_30'] }}
                        </div>

                        <div class="metric">
                            Tareas 30d: {{ $horizon['tasks_30'] }}
                        </div>

                        <div class="metric">
                            Servicios 30d: {{ $horizon['services_30'] }}
                        </div>

                        <div class="metric">
                            Vencimientos 30d: {{ $horizon['obligations_30'] }}
                        </div>
                    </div>

                    <div class="mini-list">
                        @foreach ($next_items->take(6) as $item)
                            <div class="mini">
                                <strong>{{ $item['title'] }}</strong>
                                <span>
                                    {{ $item['type'] }}
                                    @if ($item['organization'])
                                        · {{ $item['organization'] }}
                                    @endif
                                    · {{ $item['date']->format('d/m/Y') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="actions">
                    @foreach ($step['links'] as $link)
                        <a
                            class="open-link"
                            href="{{ $link['url'] }}"
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach

                    @if ($step['reviewed'])
                        <span class="reviewed-label">
                            Revisado ✓
                        </span>
                    @else
                        <form
                            method="POST"
                            action="{{ route('weekly-review.mark') }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="step"
                                value="{{ $key }}"
                            >

                            <button
                                class="review-button"
                                type="submit"
                                data-busy-label="Guardando…"
                            >
                                Marcar revisado
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </section>

    <div class="note">
        La revisión semanal registra que el frente fue evaluado; no modifica tareas, servicios ni vencimientos.
    </div>
</div>

<x-operational-theme />
<x-operational-interactions />
</body>
</html>
