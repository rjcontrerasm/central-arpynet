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

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/global-tracking.css') }}?v=2.39.2"
    >
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
                    $focus === 'stagnant'
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'global-tracking.show',
                    array_merge(
                        $base,
                        ['focus' => 'stagnant'],
                    ),
                ) }}"
            >
                Estancados
            </a>

            <a
                class="chip {{
                    $focus === 'no_next_action'
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'global-tracking.show',
                    array_merge(
                        $base,
                        [
                            'focus' =>
                                'no_next_action',
                        ],
                    ),
                ) }}"
            >
                Sin próxima acción
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
            <article class="card" data-operational-card>
                <a
                    class="card-main"
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

                @if ($item['type'] === 'task')
                    <div class="tracking-next">
                        @if ($item['next_action'])
                            <div class="tracking-next-current">
                                <strong>Siguiente:</strong>
                                {{ $item['next_action'] }}
                            </div>
                        @endif

                        <details>
                            <summary>
                                {{ $item['next_action']
                                    ? 'Cambiar próxima acción'
                                    : 'Definir próxima acción' }}
                            </summary>

                            <form
                                class="tracking-next-form"
                                method="POST"
                                action="{{ route(
                                    'task-next-action.update',
                                    $item['id'],
                                ) }}"
                            >
                                @csrf
                                <input type="hidden" name="return_to" value="tracking">

                                @if ($selectedScope)
                                    <input type="hidden" name="scope" value="{{ $selectedScope }}">
                                @endif

                                <input type="hidden" name="type" value="{{ $type }}">
                                <input type="hidden" name="focus" value="{{ $focus }}">

                                @if ($search !== '')
                                    <input type="hidden" name="q" value="{{ $search }}">
                                @endif

                                <input
                                    type="text"
                                    name="next_action"
                                    value="{{ $item['next_action'] }}"
                                    placeholder="Próximo paso concreto"
                                    maxlength="255"
                                >

                                <button
                                    type="submit"
                                    data-busy-label="Guardando…"
                                >
                                    Guardar
                                </button>
                            </form>
                        </details>
                    </div>
                @endif
            </article>
        @empty
            <div class="empty">
                No hay elementos que coincidan con estos filtros.
            </div>
        @endforelse
    </div>
</div>
    <x-operational-theme />
    <x-operational-interactions />
</body>
</html>
