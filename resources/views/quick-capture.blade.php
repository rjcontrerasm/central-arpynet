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
        href="{{ asset('central-assets/pages/quick-capture.css') }}?v={{ filemtime(public_path('central-assets/pages/quick-capture.css')) }}"
    >
</head>

<body>
<div class="shell">
    <x-operational-page-header
        active="capture"
        title="Captura rápida"
        subtitle="Escribe la tarea, elige cuándo y guarda. Lo demás puede esperar."
    />

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
        data-context-work-team-id="{{ $contextWorkTeamId ?? '' }}"
        data-current-user-id="{{ auth()->id() }}"
    >
        @csrf

        @if ($contextWorkTeamId)
            <input
                type="hidden"
                name="capture_context_work_team_id"
                value="{{ $contextWorkTeamId }}"
            >
        @endif

        @if ($contextOrganizationId)
            <input
                type="hidden"
                name="capture_context_organization_id"
                value="{{ $contextOrganizationId }}"
            >
        @endif

        @if ($contextWorkTeam || $contextOrganizationId)
            @php
                $contextOrganization =
                    $contextOrganizationId
                        ? $organizations->firstWhere(
                            'id',
                            $contextOrganizationId,
                        )
                        : null;
            @endphp

            <div class="capture-context">
                <div class="capture-context-title">
                    Contexto de captura
                </div>

                <div class="capture-context-badges">
                    @if ($contextWorkTeam)
                        <span class="capture-context-badge">
                            Equipo: {{ $contextWorkTeam->name }}
                        </span>
                    @endif

                    @if ($contextOrganization)
                        <span class="capture-context-badge secondary">
                            Empresa: {{ $contextOrganization->name }}
                        </span>
                    @endif
                </div>

                <div class="capture-context-help">
                    La tarea conservará este contexto como sugerencia.
                    Puedes cambiar empresa, responsable o visibilidad
                    antes de guardarla.
                </div>
            </div>
        @endif

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
                id="organization-select"
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

        <div class="grid2">
            <label>
                Responsable

                <select
                    name="assigned_to"
                    required
                >
                    @foreach ($assignees as $assignee)
                        <option
                            value="{{ $assignee->id }}"
                            data-default-work-team-id="{{ $defaultTeamByAssignee[$assignee->id] ?? '' }}"
                            data-organization-ids="{{ implode(',', $assigneeOrganizationIds[$assignee->id] ?? []) }}"
                            data-work-team-ids="{{ implode(',', $assigneeWorkTeamIds[$assignee->id] ?? []) }}"
                            @selected(
                                (string) old(
                                    'assigned_to',
                                    auth()->id(),
                                )
                                === (string) $assignee->id
                            )
                        >
                            {{ $assignee->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Visibilidad

                <select
                    id="visibility-scope"
                    name="visibility_scope"
                    required
                >
                    <option
                        value="organization"
                        @selected(
                            old(
                                'visibility_scope',
                                $defaultVisibilityScope,
                            ) === 'organization'
                        )
                    >
                        Toda la empresa / ámbito
                    </option>

                    <option
                        value="teams"
                        @selected(
                            old(
                                'visibility_scope',
                                $defaultVisibilityScope,
                            ) === 'teams'
                        )
                    >
                        Solo equipo(s)
                    </option>
                </select>
            </label>
        </div>

        <label
            id="work-teams-wrapper"
            class="team-selector"
        >
            Equipos con acceso

            <select
                id="work-team-select"
                name="work_team_ids[]"
                multiple
                size="{{ min(max($workTeams->count(), 2), 5) }}"
            >
                @foreach ($workTeams as $team)
                    <option
                        value="{{ $team->id }}"
                        @selected(
                            in_array(
                                $team->id,
                                old(
                                    'work_team_ids',
                                    $defaultWorkTeamId
                                        ? [$defaultWorkTeamId]
                                        : [],
                                ),
                            )
                        )
                    >
                        {{ $team->name }}
                    </option>
                @endforeach
            </select>

            <span class="advanced-hint">
                Los miembros del equipo podrán verla aunque la tarea pertenezca
                a otra empresa. El responsable sigue siendo una sola persona.
            </span>
        </label>

        <div>
            <div class="when-label">
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

                    <select
                        id="project-select"
                        name="project_id"
                    >
                        <option value="">Sin proyecto</option>

                        @foreach ($projects as $project)
                            <option
                                value="{{ $project->id }}"
                                data-organization-id="{{ $project->organization_id }}"
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

<script
    src="{{ asset('central-assets/pages/quick-capture.js') }}?v={{ filemtime(public_path('central-assets/pages/quick-capture.js')) }}"
    defer
></script>
    <x-operational-theme />
    <x-operational-interactions />
</body>
</html>
