@props([
    'active' => null,
])

@php
    $globalUndo = auth()->check()
        ? app(
            \App\Support\GlobalUndoService::class,
        )->current(auth()->user())
        : null;

    $globalUndoFlash = session(
        'global_undo_success',
    );

    $dailyWorkView = request()->routeIs('daily-ops.show')
        ? request()->query('view', 'mine')
        : null;

    $active = match (true) {
        request()->routeIs('recurring-task-front.*') => 'recurring-tasks',
        request()->routeIs('recurring-obligation-front.*') => 'recurring-obligations',
        default => $active,
    };

    $secondaryLabels = [
        'agenda' => 'Agenda',
        'search' => 'Buscar',
        'clients' => 'Clientes',
        'projects' => 'Proyectos',
        'overview360' => 'Vista 360',
        'incidents' => 'Incidentes 360',
        'tracking' => 'Seguimiento',
        'review' => 'Revisión',
        'weekly' => 'Revisión semanal',
        'decisions' => 'Decisiones',
        'summary' => 'Resumen',
        'notifications' => 'Notificaciones',
        'collaboration' => 'Colaboración',
        'copilot' => 'Copilot',
        'agent' => 'Jarvis',
        'automations' => 'Automatizaciones',
        'recurring-tasks' => 'Tareas recurrentes',
        'recurring-obligations' => 'Vencimientos recurrentes',
        'safety' => 'Estado y recuperación',
        'history' => 'Historial',
        'trash' => 'Papelera',
    ];

    $secondaryLabel = $secondaryLabels[$active] ?? null;
@endphp

<x-operational-assets />

<nav
    class="op-nav"
    data-operational-nav
    aria-label="Navegación principal de Central"
>
    <a
        class="op-nav-link {{ $active === 'daily' && $dailyWorkView === 'mine' ? 'is-active' : '' }}"
        href="{{ route('daily-ops.show') }}"
        @if ($active === 'daily' && $dailyWorkView === 'mine') aria-current="page" @endif
    >
        Mi día
    </a>

    <a
        class="op-nav-link op-nav-wide {{ $active === 'daily' && $dailyWorkView === 'team' ? 'is-active' : '' }}"
        href="{{ route('daily-ops.show', ['view' => 'team']) }}"
        @if ($active === 'daily' && $dailyWorkView === 'team') aria-current="page" @endif
    >
        Mi equipo
    </a>

    <a
        class="op-nav-link {{ $active === 'agenda' ? 'is-active' : '' }}"
        href="{{ route('operational-agenda.show') }}"
        @if ($active === 'agenda') aria-current="page" @endif
    >
        Agenda
    </a>

    <a
        class="op-nav-link {{ $active === 'capture' ? 'is-active' : '' }}"
        href="{{ route('quick-capture.show') }}"
        @if ($active === 'capture') aria-current="page" @endif
    >
        Captura
    </a>

    <a
        class="op-nav-link {{ $active === 'search' ? 'is-active' : '' }}"
        href="{{ route('global-search.index') }}"
        @if ($active === 'search') aria-current="page" @endif
    >
        Buscar
    </a>

    <a
        class="op-nav-link op-nav-wide {{ $active === 'services' ? 'is-active' : '' }}"
        href="{{ route('service-orders-ops.show') }}"
        @if ($active === 'services') aria-current="page" @endif
    >
        Servicios
    </a>

    <a
        class="op-nav-link op-nav-wide {{ $active === 'obligations' ? 'is-active' : '' }}"
        href="{{ route('obligation-ops.show') }}"
        @if ($active === 'obligations') aria-current="page" @endif
    >
        Vencimientos
    </a>

    <details class="op-nav-more">
        <summary>
            {{ $secondaryLabel ? 'Más · '.$secondaryLabel : 'Más' }}
        </summary>

        <div class="op-nav-menu">
            <div class="op-nav-group-title">Hoy</div>

            <a
                class="{{ $active === 'daily' && $dailyWorkView === 'team' ? 'is-active' : '' }}"
                href="{{ route('daily-ops.show', ['view' => 'team']) }}"
            >
                Mi equipo
            </a>

            <a
                class="{{ $active === 'daily' && $dailyWorkView === 'unassigned' ? 'is-active' : '' }}"
                href="{{ route('daily-ops.show', ['view' => 'unassigned']) }}"
            >
                Sin asignar
            </a>

            <div class="op-nav-divider"></div>
            <div class="op-nav-group-title">Trabajo</div>

            <a
                class="{{ $active === 'services' ? 'is-active' : '' }}"
                href="{{ route('service-orders-ops.show') }}"
            >
                Servicios
            </a>

            <a
                class="{{ $active === 'obligations' ? 'is-active' : '' }}"
                href="{{ route('obligation-ops.show') }}"
            >
                Vencimientos
            </a>

            <a
                class="{{ $active === 'recurring-tasks' ? 'is-active' : '' }}"
                href="{{ route('recurring-task-front.index') }}"
            >
                Tareas recurrentes
            </a>

            <a
                class="{{ $active === 'recurring-obligations' ? 'is-active' : '' }}"
                href="{{ route('recurring-obligation-front.index') }}"
            >
                Vencimientos recurrentes
            </a>

            <a
                class="{{ $active === 'clients' ? 'is-active' : '' }}"
                href="{{ route('client-ops.index') }}"
            >
                Clientes
            </a>

            <a
                class="{{ $active === 'projects' ? 'is-active' : '' }}"
                href="{{ route('project-ops.show') }}"
            >
                Proyectos
            </a>

            <a
                class="{{ $active === 'overview360' ? 'is-active' : '' }}"
                href="{{ route('operational-360.show') }}"
            >
                Vista 360
            </a>
            <a
                class="{{ $active === 'incidents' ? 'is-active' : '' }}"
                href="{{ route('incident-360.index') }}"
            >
                Incidentes 360
            </a>
            <a
                class="{{ $active === 'tracking' ? 'is-active' : '' }}"
                href="{{ route('global-tracking.show') }}"
            >
                Seguimiento
            </a>

            <div class="op-nav-divider"></div>
            <div class="op-nav-group-title">Control</div>

            <a
                class="{{ $active === 'review' ? 'is-active' : '' }}"
                href="{{ route('daily-review.show') }}"
            >
                Revisión diaria
            </a>

            <a
                class="{{ $active === 'weekly' ? 'is-active' : '' }}"
                href="{{ route('weekly-review.show') }}"
            >
                Revisión semanal
            </a>

            <div class="op-nav-divider"></div>
            <div class="op-nav-group-title">Inteligencia</div>

            <a
                class="{{ $active === 'decisions' ? 'is-active' : '' }}"
                href="{{ route('decision-inbox.index') }}"
            >
                Decisiones
            </a>

            <a
                class="{{ $active === 'summary' ? 'is-active' : '' }}"
                href="{{ route('executive-summary.show') }}"
            >
                Resumen
            </a>

            <a
                class="{{ $active === 'notifications' ? 'is-active' : '' }}"
                href="{{ route('notification-center.index') }}"
            >
                Notificaciones
            </a>

            <a
                class="{{ $active === 'collaboration' ? 'is-active' : '' }}"
                href="{{ route('collaboration.index') }}"
            >
                Colaboración
            </a>

            <a
                class="{{ $active === 'copilot' ? 'is-active' : '' }}"
                href="{{ route('central-copilot.index') }}"
            >
                Copilot
            </a>

            <a
                class="{{ $active === 'agent' ? 'is-active' : '' }}"
                href="{{ route('agent-proposals.index') }}"
            >
                Jarvis
            </a>
            <a
                class="{{ $active === 'automations' ? 'is-active' : '' }}"
                href="{{ route('automation-center.index') }}"
            >
                Automatizaciones
            </a>

            <div class="op-nav-divider"></div>
            <div class="op-nav-group-title">Sistema</div>

            <a
                class="{{ $active === 'safety' ? 'is-active' : '' }}"
                href="{{ route('safety-recovery.index') }}"
            >
                Estado y recuperación
            </a>

            <a
                class="{{ $active === 'history' ? 'is-active' : '' }}"
                href="{{ route('audit-history.index') }}"
            >
                Historial
            </a>

            <a
                class="{{ $active === 'trash' ? 'is-active' : '' }}"
                href="{{ route('task-lifecycle.trash') }}"
            >
                Papelera
            </a>

            @if (auth()->user()?->canManageTeam())
                <div class="op-nav-divider"></div>

                <a href="{{ url('/admin') }}">
                    Administración avanzada →
                </a>
            @endif
        </div>
    </details>
</nav>

@if ($globalUndo)
    <div
        class="global-undo-bar"
        role="status"
        aria-live="polite"
    >
        <span>
            {{ $globalUndo->label }}.
        </span>

        <form
            method="POST"
            action="{{ route('global-undo.restore') }}"
        >
            @csrf

            <input
                type="hidden"
                name="undo_id"
                value="{{ $globalUndo->id }}"
            >

            <button
                class="global-undo-button"
                type="submit"
                data-busy-label="Deshaciendo…"
            >
                Deshacer
            </button>
        </form>
    </div>
@elseif ($globalUndoFlash)
    <div
        class="global-undo-bar"
        role="status"
        aria-live="polite"
    >
        <span>
            {{ $globalUndoFlash }}
        </span>
    </div>
@endif
