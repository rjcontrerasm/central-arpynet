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

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/global-search.css') }}?v=2.39.1"
    >
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
