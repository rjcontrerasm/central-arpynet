<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Decisiones · Central ARPYNET</title>

    <style>
        :root {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color-scheme: light dark;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #0b1020;
            color: #f8fafc;
        }

        a { color: inherit; text-decoration: none; }
        button, input { font: inherit; }

        .shell {
            width: min(100%, 1200px);
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .topbar, .hero, .card-head, .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .topbar { margin-bottom: 24px; }
        .brand { font-weight: 850; letter-spacing: -.03em; }

        .hero {
            align-items: end;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0;
            font-size: clamp(31px, 7vw, 46px);
            line-height: .98;
            letter-spacing: -.05em;
        }

        h2 { margin: 0; font-size: 18px; }

        .subtitle, .meta, .stat-label, .empty { color: #94a3b8; }
        .subtitle { margin-top: 7px; font-size: 13px; }

        .filters { display: grid; gap: 9px; margin-bottom: 18px; }

        .filter-label {
            color: #64748b;
            font-size: 10px;
            font-weight: 850;
            letter-spacing: .06em;
            text-transform: uppercase;
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
            font-size: 12px;
            font-weight: 750;
        }

        .chip.active {
            border-color: #60a5fa;
            background: #172554;
            color: #dbeafe;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-bottom: 22px;
        }

        .stat, .decision-card {
            border: 1px solid #24304b;
            background: #11182b;
        }

        .stat { padding: 13px; border-radius: 15px; }
        .stat-value { font-size: 28px; font-weight: 850; line-height: 1; }
        .stat-label { margin-top: 5px; font-size: 11px; }

        .section-head { align-items: baseline; margin-bottom: 10px; }
        .decision-grid { display: grid; gap: 10px; }

        .decision-card {
            align-self: start;
            padding: 14px;
            border-radius: 16px;
        }

        .card-head { align-items: start; }
        .title { font-weight: 820; line-height: 1.3; }
        .meta { margin-top: 4px; font-size: 12px; line-height: 1.45; }

        .pill {
            padding: 4px 8px;
            border-radius: 999px;
            background: #1e293b;
            color: #cbd5e1;
            font-size: 10px;
            font-weight: 800;
        }

        .pill.critical { background: #450a0a; color: #fecaca; }
        .pill.attention { background: #431407; color: #fed7aa; }
        .pill.watch { background: #422006; color: #fde68a; }

        .recommendation {
            margin-top: 10px;
            color: #93c5fd;
            font-size: 13px;
            font-weight: 850;
        }

        .reason {
            margin-top: 5px;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.45;
        }

        .signals {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .signal {
            color: #b45309;
            font-size: 11px;
            font-weight: 700;
        }

        .current-next {
            margin-top: 10px;
            padding: 8px 10px;
            border-left: 3px solid #60a5fa;
            border-radius: 8px;
            background: rgba(37, 99, 235, .08);
            color: #cbd5e1;
            font-size: 11px;
        }

        .next-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 7px;
            margin-top: 10px;
        }

        .next-form input {
            min-width: 0;
            min-height: 39px;
            padding: 8px 10px;
            border: 1px solid #334155;
            border-radius: 9px;
            background: #0f172a;
            color: #f8fafc;
        }

        .next-form button, .decision-action {
            min-height: 39px;
            padding: 7px 11px;
            border: 0;
            border-radius: 9px;
            background: #2563eb;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .action-form { margin: 0; }

        .decision-action.secondary {
            border: 1px solid #334155;
            background: #0f172a;
            color: #cbd5e1;
        }

        .decision-action.done {
            border: 1px solid #166534;
            background: #052e16;
            color: #bbf7d0;
        }

        .open-module {
            display: inline-flex;
            margin-top: 11px;
            color: #93c5fd;
            font-size: 11px;
            font-weight: 800;
        }

        .success {
            margin-bottom: 14px;
            padding: 11px 13px;
            border: 1px solid #166534;
            border-radius: 12px;
            background: #052e16;
            color: #bbf7d0;
            font-size: 12px;
            font-weight: 750;
        }

        .empty {
            padding: 24px 16px;
            border: 1px dashed #334155;
            border-radius: 16px;
            text-align: center;
            font-size: 13px;
        }

        @media (min-width: 760px) {
            .stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .decision-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (prefers-color-scheme: light) {
            body { background: #f8fafc; color: #0f172a; }

            .stat, .decision-card {
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

            .current-next { background: #eff6ff; color: #334155; }

            .next-form input {
                background: #fff;
                color: #0f172a;
                border-color: #cbd5e1;
            }

            .decision-action.secondary {
                background: #fff;
                color: #475569;
                border-color: #cbd5e1;
            }
        }
    </style>
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="decisions" />
    </div>

    @if (session('decision_success'))
        <div class="success">{{ session('decision_success') }}</div>
    @endif

    @if (session('daily_action_success'))
        <div class="success">{{ session('daily_action_success') }}</div>
    @endif

    <section class="hero">
        <div>
            <h1>Decisiones</h1>
            <div class="subtitle">Resuelve lo que requiere una decisión concreta.</div>
        </div>
    </section>

    @php
        $types = [
            'all' => 'Todos',
            'task' => 'Tareas',
            'project' => 'Proyectos',
            'service' => 'Servicios',
            'obligation' => 'Vencimientos',
        ];
    @endphp

    <section class="filters">
        <div class="filter-label">Ámbito</div>
        <div class="scroll">
            <a
                class="chip {{ $selectedScope ? '' : 'active' }}"
                href="{{ route('decision-inbox.index', ['type' => $type]) }}"
            >Todos los ámbitos</a>

            @foreach ($organizations as $organization)
                <a
                    class="chip {{ $selectedScope === $organization->id ? 'active' : '' }}"
                    href="{{ route('decision-inbox.index', [
                        'scope' => $organization->id,
                        'type' => $type,
                    ]) }}"
                >{{ $organization->name }}</a>
            @endforeach
        </div>

        <div class="filter-label">Módulo</div>
        <div class="scroll">
            @foreach ($types as $value => $label)
                <a
                    class="chip {{ $type === $value ? 'active' : '' }}"
                    href="{{ route('decision-inbox.index', array_filter([
                        'scope' => $selectedScope,
                        'type' => $value,
                    ])) }}"
                >{{ $label }}</a>
            @endforeach
        </div>
    </section>

    <section class="stats">
        @foreach ([
            'Decisiones' => $counts['total'],
            'Críticas' => $counts['critical'],
            'Sin próxima acción' => $counts['no_next_action'],
            'Estancadas' => $counts['stagnant'],
        ] as $label => $value)
            <div class="stat">
                <div class="stat-value">{{ $value }}</div>
                <div class="stat-label">{{ $label }}</div>
            </div>
        @endforeach
    </section>

    <section>
        <div class="section-head">
            <h2>Resolver ahora</h2>
            <span class="meta">{{ $decisions->count() }} pendientes</span>
        </div>

        <div class="decision-grid">
            @forelse ($decisions as $decision)
                <article class="decision-card" data-operational-card>
                    <div class="card-head">
                        <div>
                            <div class="title">{{ $decision['title'] }}</div>
                            <div class="meta">
                                {{ $decision['organization'] }} · {{ $decision['type_label'] }}
                            </div>
                        </div>

                        <span class="pill {{ $decision['level'] }}">
                            {{ $decision['level_label'] }}
                        </span>
                    </div>

                    <div class="recommendation">{{ $decision['recommended_action'] }}</div>
                    <div class="reason">{{ $decision['decision_reason'] }}</div>

                    @if ($decision['reasons'])
                        <div class="signals">
                            @foreach ($decision['reasons'] as $signal)
                                <span class="signal">{{ $signal }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($decision['next_action'])
                        <div class="current-next">
                            <strong>Siguiente:</strong> {{ $decision['next_action'] }}
                        </div>
                    @endif

                    @if ($decision['type'] === 'task')
                        <form
                            class="next-form"
                            method="POST"
                            action="{{ route('task-next-action.update', $decision['id']) }}"
                        >
                            @csrf
                            <input type="hidden" name="return_to" value="decisions">

                            @if ($selectedScope)
                                <input type="hidden" name="scope" value="{{ $selectedScope }}">
                            @endif

                            <input type="hidden" name="type" value="{{ $type }}">

                            <input
                                type="text"
                                name="next_action"
                                value="{{ $decision['next_action'] }}"
                                placeholder="Definir próximo paso"
                                maxlength="255"
                            >

                            <button type="submit" data-busy-label="Guardando…">Guardar</button>
                        </form>

                        @php
                            $taskActions = [
                                'complete' => '✓ Hecho',
                                'start' => 'En curso',
                                'today' => 'Hoy',
                                'tomorrow' => 'Mañana',
                                'next_week' => '+1 semana',
                            ];
                        @endphp

                        <div class="actions">
                            @foreach ($taskActions as $action => $label)
                                <form
                                    class="action-form"
                                    method="POST"
                                    action="{{ route(
                                        'decision-task-action.update',
                                        $decision['id'],
                                    ) }}"
                                >
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $action }}">

                                    @if ($selectedScope)
                                        <input type="hidden" name="scope" value="{{ $selectedScope }}">
                                    @endif

                                    <input type="hidden" name="type" value="{{ $type }}">

                                    <button
                                        class="decision-action {{
                                            $action === 'complete' ? 'done' : 'secondary'
                                        }}"
                                        type="submit"
                                        data-busy-label="Aplicando…"
                                    >{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    <a class="open-module" href="{{ $decision['url'] }}">
                        Abrir módulo →
                    </a>
                </article>
            @empty
                <div class="empty">No hay decisiones pendientes con estos filtros.</div>
            @endforelse
        </div>
    </section>
</div>

<x-operational-theme />
<x-operational-interactions />
</body>
</html>
