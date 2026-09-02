<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="color-scheme" content="light dark">
    <title>Seguimiento · Central ARPYNET</title>

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
        input {
            font: inherit;
        }

        .shell {
            width: min(100%, 1240px);
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .topbar,
        .hero,
        .card-head {
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
            gap: 8px;
            margin-bottom: 18px;
        }

        .filter-label {
            margin-top: 2px;
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

        .search {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr) auto;
            gap: 8px;
        }

        .search input {
            width: 100%;
            min-height: 40px;
            padding: 8px 10px;
            border: 1px solid #334155;
            border-radius: 10px;
            background: #11182b;
            color: #f8fafc;
        }

        .search button {
            padding: 8px 13px;
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        .stats {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-bottom: 22px;
        }

        .stat,
        .card {
            border: 1px solid #24304b;
            background: #11182b;
        }

        .stat {
            padding: 13px;
            border-radius: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 850;
            letter-spacing: -.05em;
            line-height: 1;
        }

        .stat-label {
            margin-top: 5px;
            font-size: 11px;
        }

        .list {
            display: grid;
            gap: 10px;
        }

        .card {
            display: block;
            padding: 14px;
            border-radius: 16px;
            transition:
                transform .12s ease,
                border-color .12s ease;
        }

        .card:hover {
            transform: translateY(-1px);
            border-color: #475569;
        }

        .card-head {
            align-items: start;
        }

        .title {
            font-weight: 800;
            line-height: 1.3;
        }

        .meta {
            margin-top: 4px;
            font-size: 12px;
            line-height: 1.45;
        }

        .pills,
        .reasons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
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
            color: #fbbf24;
            font-size: 11px;
            font-weight: 750;
        }

        .go {
            margin-top: 10px;
            color: #93c5fd;
            font-size: 11px;
            font-weight: 800;
        }

        .empty {
            padding: 24px 16px;
            border: 1px dashed #334155;
            border-radius: 16px;
            text-align: center;
            font-size: 13px;
        }

        @media (min-width: 760px) {
            .stats {
                grid-template-columns:
                    repeat(6, minmax(0, 1fr));
            }

            .list {
                grid-template-columns:
                    repeat(
                        auto-fit,
                        minmax(min(100%, 480px), 1fr)
                    );
            }

            .empty {
                grid-column: 1 / -1;
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
            .empty {
                color: #64748b;
            }

            .stat,
            .card {
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

            .pill {
                background: #f1f5f9;
                color: #475569;
            }

            .pill.critical {
                background: #fef2f2;
                color: #b91c1c;
            }

            .pill.attention {
                background: #fff7ed;
                color: #c2410c;
            }

            .pill.watch {
                background: #fffbeb;
                color: #a16207;
            }

            .search input {
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

        <x-operational-nav active="tracking" />
    </div>

    <section class="hero">
        <div>
            <h1>Seguimiento</h1>

            <div class="subtitle">
                Todo lo que requiere atención en un solo lugar
            </div>
        </div>
    </section>

    @php
        $base = array_filter([
            'scope' => $selectedScope,
            'type' => $type,
            'focus' => $focus,
            'q' => $search !== '' ? $search : null,
        ]);

        $types = [
            'all' => 'Todos los módulos',
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
                class="chip {{
                    $selectedScope ? '' : 'active'
                }}"
                href="{{ route(
                    'global-tracking.show',
                    array_filter([
                        'type' => $type,
                        'focus' => $focus,
                        'q' => $search !== ''
                            ? $search
                            : null,
                    ]),
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
                        'global-tracking.show',
                        array_filter([
                            'scope' => $organization->id,
                            'type' => $type,
                            'focus' => $focus,
                            'q' => $search !== ''
                                ? $search
                                : null,
                        ]),
                    ) }}"
                >
                    {{ $organization->name }}
                </a>
            @endforeach
        </div>

        <div class="filter-label">Estado</div>

        <div class="scroll">
            <a
                class="chip {{
                    $focus === 'attention'
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'global-tracking.show',
                    array_merge(
                        $base,
                        ['focus' => 'attention'],
                    ),
                ) }}"
            >
                Requieren atención
            </a>

            <a
                class="chip {{
                    $focus === 'all'
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'global-tracking.show',
                    array_merge(
                        $base,
                        ['focus' => 'all'],
                    ),
                ) }}"
            >
                Todos
            </a>

        </div>

        <div class="filter-label">Módulo</div>

        <div class="scroll">
            @foreach ($types as $value => $label)
                <a
                    class="chip {{
                        $type === $value
                            ? 'active'
                            : ''
                    }}"
                    href="{{ route(
                        'global-tracking.show',
                        array_merge(
                            $base,
                            ['type' => $value],
                        ),
                    ) }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form
            class="search"
            method="GET"
            action="{{ route('global-tracking.show') }}"
        >
            @if ($selectedScope)
                <input
                    type="hidden"
                    name="scope"
                    value="{{ $selectedScope }}"
                >
            @endif

            <input
                type="hidden"
                name="type"
                value="{{ $type }}"
            >

            <input
                type="hidden"
                name="focus"
                value="{{ $focus }}"
            >

            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar en seguimiento..."
            >

            <button type="submit">
                Buscar
            </button>
        </form>
    </section>

    <section class="stats">
        @foreach ([
            'Críticos' => $summary['critical'],
            'A vigilar' => $summary['attention'],
            'Tareas' => $summary['tasks'],
            'Proyectos' => $summary['projects'],
            'Servicios' => $summary['services'],
            'Vencimientos' => $summary['obligations'],
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

    <div class="list">
        @forelse ($items as $item)
            <a
                class="card"
                href="{{ $item['url'] }}"
            >
                <div class="card-head">
                    <div>
                        <div class="title">
                            {{ $item['title'] }}
                        </div>

                        <div class="meta">
                            {{ $item['organization'] }}
                            ·
                            {{ $item['type_label'] }}
                        </div>
                    </div>

                    <span
                        class="pill {{ $item['level'] }}"
                    >
                        {{ $item['level_label'] }}
                    </span>
                </div>

                <div class="pills">
                    <span class="pill">
                        {{ $item['meta'] }}
                    </span>

                    @if ($item['date_label'])
                        <span class="pill">
                            {{ $item['date_label'] }}
                        </span>
                    @endif
                </div>

                @if ($item['reasons'])
                    <div class="reasons">
                        @foreach (
                            $item['reasons']
                            as $reason
                        )
                            <span class="reason">
                                {{ $reason }}
                            </span>
                        @endforeach
                    </div>
                @endif

                <div class="go">
                    Abrir módulo →
                </div>
            </a>
        @empty
            <div class="empty">
                No hay elementos que coincidan con estos filtros.
            </div>
        @endforelse
    </div>
</div>
    <x-operational-interactions />
</body>
</html>
