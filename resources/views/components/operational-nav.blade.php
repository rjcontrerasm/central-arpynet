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

    $secondaryLabels = [
        'agenda' => 'Agenda',
        'tracking' => 'Seguimiento',
        'review' => 'Revisión',
        'weekly' => 'Revisión semanal',
        'decisions' => 'Decisiones',
        'summary' => 'Resumen',
        'notifications' => 'Notificaciones',
        'history' => 'Historial',
        'trash' => 'Papelera',
    ];

    $secondaryLabel = $secondaryLabels[$active] ?? null;
@endphp

<style>
    .op-nav {
        --op-nav-muted: #94a3b8;
        --op-nav-text: #cbd5e1;
        --op-nav-surface: #0f172a;
        --op-nav-surface-2: #172554;
        --op-nav-border: #334155;
        --op-nav-accent: #60a5fa;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 5px;
        min-width: 0;
    }

    .op-nav a {
        text-decoration: none;
    }

    .op-nav-link,
    .op-nav-more > summary {
        display: inline-flex;
        align-items: center;
        min-height: 36px;
        padding: 7px 9px;
        border: 1px solid transparent;
        border-radius: 10px;
        color: var(--op-nav-muted);
        font-size: 13px;
        font-weight: 760;
        line-height: 1;
        white-space: nowrap;
    }

    .op-nav-link:hover,
    .op-nav-more > summary:hover {
        color: var(--op-nav-text);
        background: rgba(30, 41, 59, .72);
    }

    .op-nav-link.is-active {
        border-color: #1d4ed8;
        background: var(--op-nav-surface-2);
        color: #dbeafe;
    }

    .op-nav-wide {
        display: none;
    }

    .op-nav-more {
        position: relative;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
    }

    .op-nav-more > summary {
        cursor: pointer;
        list-style: none;
        background: transparent;
    }

    .op-nav-more > summary::-webkit-details-marker {
        display: none;
    }

    .op-nav-more > summary::after {
        content: '⌄';
        margin-left: 5px;
        font-size: 11px;
        transition: transform 140ms ease;
    }

    .op-nav-more[open] > summary::after {
        transform: rotate(180deg);
    }

    .op-nav-more[open] > summary {
        border-color: var(--op-nav-border);
        background: var(--op-nav-surface);
        color: #f8fafc;
    }

    .op-nav-menu {
        position: absolute;
        z-index: 100;
        top: calc(100% + 7px);
        right: 0;
        display: grid;
        width: min(240px, calc(100vw - 32px));
        padding: 8px;
        border: 1px solid var(--op-nav-border);
        border-radius: 14px;
        background: var(--op-nav-surface);
        box-shadow: 0 18px 55px rgba(0, 0, 0, .32);
    }

    .op-nav-menu a {
        display: flex;
        align-items: center;
        min-height: 40px;
        padding: 9px 10px;
        border-radius: 9px;
        color: var(--op-nav-text);
        font-size: 13px;
        font-weight: 700;
    }

    .op-nav-menu a:hover {
        background: #1e293b;
    }

    .op-nav-menu a.is-active {
        background: var(--op-nav-surface-2);
        color: #dbeafe;
    }

    .op-nav-divider {
        height: 1px;
        margin: 6px 4px;
        background: var(--op-nav-border);
    }

    .global-undo-bar {
        position: fixed;
        z-index: 250;
        right: 18px;
        bottom: 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: min(520px, calc(100vw - 32px));
        padding: 12px 14px;
        border: 1px solid #334155;
        border-radius: 14px;
        background: #0f172a;
        color: #e2e8f0;
        box-shadow: 0 18px 55px rgba(0, 0, 0, .38);
        font-size: 12px;
        font-weight: 720;
    }

    .global-undo-bar form {
        margin: 0;
    }

    .global-undo-button {
        min-height: 34px;
        padding: 6px 10px;
        border: 1px solid #3b82f6;
        border-radius: 9px;
        background: #172554;
        color: #dbeafe;
        font: inherit;
        font-weight: 850;
        cursor: pointer;
        white-space: nowrap;
    }

    @media (max-width: 420px) {
        .op-nav-link,
        .op-nav-more > summary {
            min-height: 40px;
            padding-inline: 8px;
            font-size: 12px;
        }

        .op-nav-more > summary {
            max-width: 132px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }

    @media (min-width: 760px) {
        .op-nav {
            gap: 7px;
        }

        .op-nav-wide {
            display: inline-flex;
        }
    }

    @media (prefers-color-scheme: light) {
        .op-nav {
            --op-nav-muted: #64748b;
            --op-nav-text: #334155;
            --op-nav-surface: #ffffff;
            --op-nav-surface-2: #eff6ff;
            --op-nav-border: #cbd5e1;
        }

        .op-nav-link:hover,
        .op-nav-more > summary:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .op-nav-link.is-active,
        .op-nav-menu a.is-active {
            border-color: var(--op-nav-accent);
            color: #1d4ed8;
        }

        .op-nav-more[open] > summary {
            color: #0f172a;
        }

        .op-nav-menu {
            box-shadow: 0 18px 45px rgba(15, 23, 42, .14);
        }

        .op-nav-menu a:hover {
            background: #f1f5f9;
        }

        .global-undo-bar {
            border-color: #cbd5e1;
            background: #ffffff;
            color: #334155;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
        }

        .global-undo-button {
            background: #eff6ff;
            color: #1d4ed8;
        }
    }
</style>

<nav
    class="op-nav"
    data-operational-nav
    aria-label="Navegación principal de Central"
>
    <a
        class="op-nav-link {{ $active === 'daily' ? 'is-active' : '' }}"
        href="{{ route('daily-ops.show') }}"
        @if ($active === 'daily') aria-current="page" @endif
    >
        Mi día
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
                class="{{ $active === 'tracking' ? 'is-active' : '' }}"
                href="{{ route('global-tracking.show') }}"
            >
                Seguimiento
            </a>

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

            <div class="op-nav-divider"></div>

            <a href="{{ url('/admin') }}">
                Panel administrativo →
            </a>
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
