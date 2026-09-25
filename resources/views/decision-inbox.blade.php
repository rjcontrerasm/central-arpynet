<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Decisiones · Central ARPYNET</title>

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/decision-inbox.css') }}?v=2.39.2"
    >
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
            <div class="subtitle">Decision Engine · priorización explicable y determinística.</div>
        </div>
    </section>

    <div class="engine-summary">
        <strong>{{ $decisionEngineSummary }}</strong>
        · Score v{{ $decisionScoreVersion }}
        · Recomendaciones de solo lectura; las acciones manuales siguen usando la capa operativa segura.
    </div>

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
            'Decidir ahora' => $counts['immediate'],
            'Decidir hoy' => $counts['today'],
            'Evidencia alta' => $counts['high_evidence'],
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

                    <div class="decision-engine-row">
                        <span class="decision-score">Score {{ $decision['decision_score'] }}/100</span>
                        <span class="decision-band">{{ $decision['decision_band_label'] }}</span>
                        <span class="evidence">{{ $decision['evidence_quality_label'] }}</span>
                    </div>

                    <div class="recommendation">{{ $decision['recommended_action'] }}</div>
                    <div class="reason">{{ $decision['decision_reason'] }}</div>
                    <div class="why-now"><strong>Por qué ahora:</strong> {{ $decision['why_now'] }}</div>
                    <div class="score-breakdown">
                        Prioridad {{ $decision['score_breakdown']['operational_priority'] }}
                        · Riesgo {{ $decision['score_breakdown']['explicit_risk'] }}
                        · Brecha {{ $decision['score_breakdown']['decision_gap'] }}
                    </div>

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

                    @php
                        $delegation = $decision['delegation'];
                    @endphp

                    <div class="delegation-box {{ $delegation['can_delegate'] ? '' : 'manual' }}">
                        <div class="delegation-title">
                            {{ $delegation['status_label'] }} · Política v{{ $delegation['policy_version'] }}
                        </div>
                        <div class="delegation-copy">
                            {{ $delegation['reason'] }}
                            Requiere revisión humana y una segunda confirmación antes de cualquier ejecución.
                        </div>

                        @if ($delegation['can_delegate'] && $decision['type'] === 'task')
                            <div class="delegation-actions">
                                @foreach ($delegation['actions'] as $delegationAction)
                                    <form
                                        method="POST"
                                        action="{{ route('decision-delegation.store') }}"
                                    >
                                        @csrf
                                        <input type="hidden" name="subject_type" value="task">
                                        <input type="hidden" name="subject_id" value="{{ $decision['id'] }}">
                                        <input type="hidden" name="action" value="{{ $delegationAction['key'] }}">
                                        <button
                                            class="delegation-action"
                                            type="submit"
                                            data-busy-label="Preparando…"
                                        >{{ $delegationAction['label'] }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @elseif ($delegation['can_delegate'] && in_array($decision['type'], ['project', 'service'], true))
                            @foreach ($delegation['actions'] as $delegationAction)
                                <form
                                    class="delegation-form"
                                    method="POST"
                                    action="{{ route('decision-delegation.store') }}"
                                >
                                    @csrf
                                    <input type="hidden" name="subject_type" value="{{ $decision['type'] }}">
                                    <input type="hidden" name="subject_id" value="{{ $decision['id'] }}">
                                    <input type="hidden" name="action" value="{{ $delegationAction['key'] }}">

                                    <div class="delegation-fields">
                                        <input
                                            class="delegation-input"
                                            type="text"
                                            name="next_action"
                                            maxlength="255"
                                            required
                                            placeholder="Siguiente acción concreta"
                                        >

                                        @if ($decision['type'] === 'service')
                                            <input
                                                class="delegation-input"
                                                type="datetime-local"
                                                name="next_action_at"
                                            >
                                        @endif
                                    </div>

                                    <button
                                        class="delegation-action"
                                        type="submit"
                                        data-busy-label="Preparando…"
                                    >{{ $delegationAction['label'] }}</button>
                                </form>
                            @endforeach
                        @endif
                    </div>

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
