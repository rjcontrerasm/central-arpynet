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

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/weekly-review.css') }}?v=2.39.2"
    >
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
                    class="progress-fill progress-{{ $reviewedCount * 20 }}"
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
