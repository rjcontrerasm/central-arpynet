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

        .shell {
            width: min(100%, 960px);
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

        .topbar { margin-bottom: 24px; }

        .brand {
            font-weight: 850;
            letter-spacing: -.03em;
        }

        .admin-link,
        .date,
        .meta,
        .stat-label,
        .empty {
            color: #94a3b8;
        }

        .admin-link,
        .section-link {
            font-size: 13px;
        }

        .hero {
            align-items: end;
            margin-bottom: 20px;
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
            color: white;
            font-weight: 850;
        }

        .stats {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 24px;
        }

        .stat,
        .item {
            background: #11182b;
            border: 1px solid #24304b;
        }

        .stat {
            padding: 14px;
            border-radius: 17px;
        }

        .stat-value {
            font-size: 30px;
            line-height: 1;
            font-weight: 850;
            letter-spacing: -.05em;
        }

        .stat-label {
            margin-top: 7px;
            font-size: 13px;
        }

        .danger-value { color: #fca5a5; }
        .today-value { color: #93c5fd; }

        .section { margin-top: 26px; }

        .section-head {
            align-items: baseline;
            margin-bottom: 11px;
        }

        h2 {
            margin: 0;
            font-size: 19px;
            letter-spacing: -.025em;
        }

        .section-link { color: #93c5fd; }

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

        .pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 2px;
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

        .pill.danger {
            background: #450a0a;
            color: #fecaca;
        }

        .pill.today {
            background: #172554;
            color: #bfdbfe;
        }

        .empty {
            padding: 18px;
            border: 1px dashed #334155;
            border-radius: 16px;
            text-align: center;
            font-size: 14px;
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
            color: white;
            font-weight: 850;
            box-shadow:
                0 16px 50px rgba(37, 99, 235, .38);
        }

        @media (min-width: 720px) {
            .shell { padding-top: 30px; }

            .stats {
                grid-template-columns:
                    repeat(4, minmax(0, 1fr));
            }

            .quick { display: inline-flex; }
            .fab { display: none; }

            .two-column {
                display: grid;
                grid-template-columns:
                    minmax(0, 1.3fr)
                    minmax(0, .7fr);
                gap: 24px;
                align-items: start;
            }
        }

        @media (prefers-color-scheme: light) {
            body {
                background: #f8fafc;
                color: #0f172a;
            }

            .admin-link,
            .date,
            .meta,
            .stat-label,
            .empty {
                color: #64748b;
            }

            .stat,
            .item {
                background: white;
                border-color: #e2e8f0;
            }

            .pill {
                background: #f1f5f9;
                color: #475569;
            }

            .pill.danger {
                background: #fef2f2;
                color: #b91c1c;
            }

            .pill.today {
                background: #eff6ff;
                color: #1d4ed8;
            }
        }
    </style>
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <a
            class="admin-link"
            href="{{ url('/admin') }}"
        >
            Panel completo →
        </a>
    </div>

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

    <section class="stats">
        <a class="stat" href="{{ url('/admin/tareas') }}">
            <div class="stat-value danger-value">
                {{ $overdueCount }}
            </div>
            <div class="stat-label">Vencidas</div>
        </a>

        <a class="stat" href="{{ url('/admin/tareas') }}">
            <div class="stat-value today-value">
                {{ $todayCount }}
            </div>
            <div class="stat-label">Para hoy</div>
        </a>

        <a class="stat" href="{{ url('/admin/tareas') }}">
            <div class="stat-value">
                {{ $weekCount }}
            </div>
            <div class="stat-label">Próximos 7 días</div>
        </a>

        <a class="stat" href="{{ url('/admin/tareas') }}">
            <div class="stat-value">
                {{ $noDateCount }}
            </div>
            <div class="stat-label">Sin fecha</div>
        </a>
    </section>

    <div class="two-column">
        <main>
            <section class="section">
                <div class="section-head">
                    <h2>Atender ahora</h2>
                    <a
                        class="section-link"
                        href="{{ url('/admin/tareas') }}"
                    >
                        Ver tareas
                    </a>
                </div>

                <div class="list">
                    @forelse ($attentionTasks as $task)
                        @php
                            $isOverdue = $task->due_at
                                && $task->due_at->isBefore(
                                    $now->startOfDay(),
                                );

                            $isToday = $task->due_at
                                && $task->due_at->isSameDay($now);
                        @endphp

                        <a
                            class="item"
                            href="{{ url('/admin/tareas') }}"
                        >
                            <div class="item-title">
                                {{ $task->title }}
                            </div>

                            <div class="meta">
                                {{ $task->organization?->name
                                    ?? 'Sin ámbito' }}

                                @if ($task->due_at)
                                    · {{ $task->due_at->format(
                                        'd/m/Y',
                                    ) }}
                                @else
                                    · sin fecha
                                @endif
                            </div>

                            <div class="pills">
                                @if ($isOverdue)
                                    <span class="pill danger">
                                        Vencida
                                    </span>
                                @elseif ($isToday)
                                    <span class="pill today">
                                        Hoy
                                    </span>
                                @endif

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
                        </a>
                    @empty
                        <div class="empty">
                            No tienes tareas abiertas.
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
                        href="{{ url('/admin/vencimientos') }}"
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
                                    class="pill
                                    {{ in_array(
                                        $incident->severity,
                                        ['critical', 'high'],
                                        true,
                                    ) ? 'danger' : '' }}"
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
