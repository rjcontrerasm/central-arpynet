<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="color-scheme" content="light dark">
    <title>Buscar · Central ARPYNET</title>

    <x-operational-theme />

    <style>
        * { box-sizing: border-box; }

        body { margin: 0; }

        .shell {
            width: min(100%, 1200px);
            margin: 0 auto;
            min-height: 100vh;
            padding: 24px 16px 80px;
        }

        .topbar,
        .hero {
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

        .hero {
            align-items: end;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0;
            font-size: clamp(31px, 7vw, 46px);
            line-height: 1;
            letter-spacing: -.05em;
        }

        h2 {
            margin: 0;
            font-size: 17px;
            letter-spacing: -.02em;
        }

        .subtitle {
            margin-top: 7px;
            font-size: 13px;
        }

        .search-panel {
            display: grid;
            gap: 12px;
            padding: 15px;
            border: 1px solid var(--central-border);
            border-radius: 18px;
            background: var(--central-surface);
            box-shadow: var(--central-shadow);
        }

        .search-form {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr) auto;
            gap: 8px;
        }

        .search-form input {
            min-width: 0;
            min-height: 46px;
            padding: 10px 13px;
            border: 1px solid var(--central-border-strong);
            border-radius: 12px;
            background: var(--central-surface);
            color: var(--central-text);
        }

        .search-form button {
            min-height: 46px;
            padding: 9px 15px;
            border: 0;
            border-radius: 12px;
            background: var(--central-primary);
            color: #fff;
            font-weight: 850;
            cursor: pointer;
        }

        .filters,
        .scope-list {
            display: flex;
            gap: 7px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .filters::-webkit-scrollbar,
        .scope-list::-webkit-scrollbar {
            display: none;
        }

        .chip {
            flex: 0 0 auto;
            min-height: 36px;
            padding: 8px 11px;
            border: 1px solid var(--central-border-strong);
            border-radius: 999px;
            background: var(--central-surface);
            color: var(--central-muted);
            font-size: 12px;
            font-weight: 760;
        }

        .chip.active {
            border-color: #7eb0ff;
            background: var(--central-primary-soft);
            color: var(--central-primary-text);
        }

        .summary {
            margin: 18px 0 4px;
            color: var(--central-muted);
            font-size: 12px;
        }

        .results {
            display: grid;
            gap: 18px;
            margin-top: 18px;
        }

        .result-section {
            display: grid;
            gap: 9px;
        }

        .section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
        }

        .count {
            color: var(--central-muted);
            font-size: 11px;
        }

        .result-list {
            display: grid;
            gap: 8px;
        }

        .result {
            display: grid;
            gap: 5px;
            padding: 13px 14px;
            border: 1px solid var(--central-border);
            border-radius: 15px;
            background: var(--central-surface);
            box-shadow: var(--central-shadow);
        }

        .result:hover {
            border-color: #9db6d4;
        }

        .result-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .result-title {
            font-weight: 820;
            line-height: 1.35;
        }

        .result-org,
        .result-meta {
            color: var(--central-muted);
            font-size: 11px;
            line-height: 1.45;
        }

        .go {
            flex: 0 0 auto;
            color: var(--central-primary-text);
            font-size: 12px;
            font-weight: 820;
        }

        .empty-state {
            margin-top: 18px;
            padding: 28px 18px;
            border: 1px dashed var(--central-border-strong);
            border-radius: 16px;
            background: var(--central-surface);
            color: var(--central-muted);
            text-align: center;
            font-size: 13px;
            line-height: 1.55;
        }

        @media (min-width: 760px) {
            .result-list {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .shell {
                padding: 16px 12px 72px;
            }

            .search-form {
                grid-template-columns: minmax(0, 1fr);
            }

            .search-form button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
@php
    $typeLabels = [
        'all' => 'Todo',
        'task' => 'Tareas',
        'project' => 'Proyectos',
        'client' => 'Clientes',
        'service' => 'Servicios',
        'incident' => 'Incidentes',
    ];

    $groupLabels = [
        'task' => 'Tareas',
        'project' => 'Proyectos',
        'client' => 'Clientes',
        'service' => 'Servicios',
        'incident' => 'Incidentes',
    ];

    $base = array_filter([
        'q' => $search !== '' ? $search : null,
        'scope' => $selectedScope,
    ]);
@endphp

<div class="shell">
    <x-operational-page-header
        active="search"
        title="Buscar"
        subtitle="Encuentra trabajo y contexto en todo Central sin cambiar de módulo."
    />

    <section class="search-panel">
        <form
            class="search-form"
            method="GET"
            action="{{ route('global-search.index') }}"
        >
            @if ($selectedScope)
                <input
                    type="hidden"
                    name="scope"
                    value="{{ $selectedScope }}"
                >
            @endif

            @if ($type !== 'all')
                <input
                    type="hidden"
                    name="type"
                    value="{{ $type }}"
                >
            @endif

            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar cliente, tarea, proyecto, servicio o incidente…"
                minlength="2"
                maxlength="120"
                autocomplete="off"
                autofocus
                aria-label="Buscar en Central"
            >

            <button type="submit">
                Buscar
            </button>
        </form>

        <nav
            class="filters"
            aria-label="Tipo de resultado"
        >
            @foreach ($typeLabels as $value => $label)
                <a
                    class="chip {{ $type === $value ? 'active' : '' }}"
                    href="{{ route(
                        'global-search.index',
                        array_merge(
                            $base,
                            ['type' => $value],
                        ),
                    ) }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <nav
            class="scope-list"
            aria-label="Ámbito de búsqueda"
        >
            <a
                class="chip {{ $selectedScope ? '' : 'active' }}"
                href="{{ route(
                    'global-search.index',
                    array_filter([
                        'q' => $search !== '' ? $search : null,
                        'type' => $type !== 'all'
                            ? $type
                            : null,
                    ]),
                ) }}"
            >
                Todos los ámbitos
            </a>

            @foreach ($organizations as $organization)
                <a
                    class="chip {{
                        $selectedScope === (int) $organization->id
                            ? 'active'
                            : ''
                    }}"
                    href="{{ route(
                        'global-search.index',
                        array_filter([
                            'q' => $search !== '' ? $search : null,
                            'type' => $type !== 'all'
                                ? $type
                                : null,
                            'scope' => $organization->id,
                        ]),
                    ) }}"
                >
                    {{ $organization->name }}
                </a>
            @endforeach
        </nav>
    </section>

    @if ($search !== '' && ! $searchReady)
        <div class="empty-state">
            Escribe al menos 2 caracteres para buscar.
        </div>
    @elseif ($searchReady && $total === 0)
        <div class="empty-state">
            No encontré resultados visibles para
            “{{ $search }}”.
        </div>
    @elseif ($searchReady)
        <div class="summary">
            {{ $total }}
            {{ $total === 1 ? 'resultado visible' : 'resultados visibles' }}
            para “{{ $search }}”.
        </div>

        <div class="results">
            @foreach ($groupLabels as $group => $label)
                @php
                    $items = $results->get($group);
                @endphp

                @if ($items->isNotEmpty())
                    <section class="result-section">
                        <div class="section-head">
                            <h2>{{ $label }}</h2>
                            <span class="count">
                                {{ $items->count() }}
                            </span>
                        </div>

                        <div class="result-list">
                            @foreach ($items as $item)
                                <a
                                    class="result"
                                    href="{{ $item['url'] }}"
                                >
                                    <div class="result-head">
                                        <div class="result-title">
                                            {{ $item['title'] }}
                                        </div>

                                        <span class="go">
                                            Abrir →
                                        </span>
                                    </div>

                                    @if ($item['organization'])
                                        <div class="result-org">
                                            {{ $item['organization'] }}
                                        </div>
                                    @endif

                                    @if ($item['meta'])
                                        <div class="result-meta">
                                            {{ $item['meta'] }}
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </div>
    @else
        <div class="empty-state">
            Busca por nombre, título, RUC, número de orden,
            siguiente acción o servicio afectado.
        </div>
    @endif
</div>
</body>
</html>
