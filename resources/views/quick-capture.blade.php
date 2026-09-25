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
    <title>Captura rápida · Central ARPYNET</title>

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/quick-capture.css') }}?v=2.39.1"
    >
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <x-operational-nav active="capture" />
    </div>

    <section class="hero">
        <h1>Captura rápida</h1>
        <p>
            Escribe la tarea, elige cuándo y guarda.
            Lo demás puede esperar.
        </p>
    </section>

    @if (session('quick_capture_success'))
        <div class="success">
            {{ session('quick_capture_success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="errors">
            <strong>Revisa estos datos:</strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        class="card stack"
        method="POST"
        action="{{ route('quick-capture.store') }}"
        autocomplete="off"
    >
        @csrf

        <label>
            ¿Qué tienes que hacer?

            <input
                id="title"
                name="title"
                type="text"
                maxlength="255"
                placeholder="Ej. mañana enviar informe SUNARP"
                value="{{ old('title') }}"
                autofocus
                required
            >
        </label>

        <div class="smart-hint">
            <strong>Atajos:</strong>
            <code>viernes</code>,
            <code>en 3 días</code>,
            <code>15/09</code>,
            <code>urgente</code>,
            <code>@Personal</code>,
            <code>#Proyecto</code> y
            <code>-&gt; próxima acción</code>.
            Solo se interpretan formatos explícitos.
        </div>

        <label>
            Empresa / ámbito

            <select
                name="organization_id"
                required
            >
                @foreach ($organizations as $organization)
                    <option
                        value="{{ $organization->id }}"
                        @selected(
                            (string) old(
                                'organization_id',
                                $defaultOrganizationId,
                            )
                            === (string) $organization->id
                        )
                    >
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <div>
            <div class="due-label">
                ¿Cuándo?
            </div>

            <div class="chips">
                @foreach ([
                    'today' => 'Hoy',
                    'tomorrow' => 'Mañana',
                    'next_week' => '1 semana',
                    'none' => 'Sin fecha',
                    'custom' => 'Elegir',
                ] as $value => $label)
                    <label class="chip">
                        <input
                            type="radio"
                            name="due_mode"
                            value="{{ $value }}"
                            @checked(
                                old('due_mode', 'today')
                                === $value
                            )
                        >
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <label
            id="custom-date-wrapper"
            class="custom-date"
        >
            Fecha

            <input
                type="date"
                name="due_date"
                value="{{ old('due_date') }}"
            >
        </label>

        <details class="advanced">
            <summary>Opciones avanzadas</summary>

            <div class="advanced-hint">
                Normal funciona para la mayoría de tareas.
                Ajusta urgencia o impacto solo cuando realmente
                necesites alterar la prioridad.
            </div>

            <div class="grid2">
                <label class="full">
                    Proyecto

                    <select name="project_id">
                        <option value="">Sin proyecto</option>

                        @foreach ($projects as $project)
                            <option
                                value="{{ $project->id }}"
                                @selected(
                                    (string) old('project_id')
                                    === (string) $project->id
                                )
                            >
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Urgencia

                    <select name="urgency">
                        @foreach ([
                            'low' => 'Baja',
                            'normal' => 'Normal',
                            'high' => 'Alta',
                            'critical' => 'Crítica',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(
                                    old('urgency', 'normal')
                                    === $value
                                )
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Impacto

                    <select name="impact">
                        @foreach ([
                            'low' => 'Bajo',
                            'normal' => 'Normal',
                            'high' => 'Alto',
                            'critical' => 'Crítico',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(
                                    old('impact', 'normal')
                                    === $value
                                )
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </details>

        <button
            class="submit"
            type="submit"
            data-busy-label="Guardando tarea…"
        >
            Guardar tarea
        </button>
    </form>

    @if ($recentTasks->isNotEmpty())
        <section class="recent">
            <h2>Últimas capturas</h2>

            <div class="recent-list">
                @foreach ($recentTasks as $task)
                    <div class="recent-item" data-operational-card>
                        <div class="recent-title">
                            {{ $task->title }}
                        </div>

                        <div class="recent-meta">
                            {{ $task->organization?->name ?? 'Sin ámbito' }}

                            @if ($task->due_at)
                                · vence
                                {{ $task->due_at->format('d/m/Y') }}
                            @else
                                · sin fecha
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

<script src="{{ asset('central-assets/pages/quick-capture.js') }}?v=2.39.1"></script>
    <x-operational-theme />
    <x-operational-interactions />
</body>
</html>
