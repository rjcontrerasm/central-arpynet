@if(($context['status'] ?? null) === 'ok')
<style>
.calendar-context-panel{margin-top:12px;padding:14px;border:1px solid var(--ag-line);border-radius:13px;background:var(--ag-card);box-shadow:var(--ag-shadow)}
.calendar-context-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
.calendar-context-title{font-size:13px;font-weight:900;color:#19345f}
.calendar-context-note{margin-top:3px;color:var(--ag-muted);font-size:10px}
.calendar-context-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:11px}
.calendar-context-metric{padding:10px;border:1px solid var(--ag-line);border-radius:10px;background:rgba(245,248,252,.55)}
.calendar-context-metric strong{display:block;font-size:18px;color:#17376b}
.calendar-context-metric span{display:block;margin-top:3px;color:var(--ag-muted);font-size:10px}
.calendar-context-events{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:9px}
.calendar-context-event{padding:10px;border:1px solid var(--ag-line);border-radius:10px;background:rgba(245,248,252,.42)}
.calendar-context-event small{display:block;color:var(--ag-muted);font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.04em}
.calendar-context-event strong{display:block;margin-top:4px;color:#17376b;font-size:12px}
.calendar-context-event span{display:block;margin-top:3px;color:var(--ag-muted);font-size:10px}
.calendar-context-warning{margin-top:9px;padding:8px 10px;border-radius:9px;background:var(--ag-amber-soft);color:var(--ag-amber);font-size:10px;font-weight:750}
@media(max-width:720px){.calendar-context-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.calendar-context-events{grid-template-columns:1fr}}
@media(prefers-color-scheme:dark){.calendar-context-title,.calendar-context-metric strong,.calendar-context-event strong{color:#f8fafc}.calendar-context-metric,.calendar-context-event{background:#0f172a}}
</style>

<div class="calendar-context-panel">
    <div class="calendar-context-head">
        <div>
            <div class="calendar-context-title">Contexto externo · Google Calendar</div>
            <div class="calendar-context-note">Solo hechos observados del calendario. CENTRAL no infiere disponibilidad ni crea tareas desde estos eventos.</div>
        </div>
    </div>

    <div class="calendar-context-grid">
        <div class="calendar-context-metric">
            <strong>{{ $context['counts']['external'] ?? 0 }}</strong>
            <span>Eventos externos</span>
        </div>
        <div class="calendar-context-metric">
            <strong>{{ $context['busy_minutes'] ?? 0 }} min</strong>
            <span>Tiempo ocupado con hora</span>
        </div>
        <div class="calendar-context-metric">
            <strong>{{ $context['counts']['all_day'] ?? 0 }}</strong>
            <span>Todo el día</span>
        </div>
        <div class="calendar-context-metric">
            <strong>{{ $context['overlap_count'] ?? 0 }}</strong>
            <span>Solapamientos reales</span>
        </div>
    </div>

    @if(!empty($context['current_event']) || !empty($context['next_event']))
        <div class="calendar-context-events">
            @if(!empty($context['current_event']))
                <div class="calendar-context-event">
                    <small>En curso</small>
                    <strong>{{ $context['current_event']['title'] }}</strong>
                    <span>
                        {{ $context['current_event']['starts_at']?->format('H:i') }}
                        @if($context['current_event']['ends_at'])
                            – {{ $context['current_event']['ends_at']->format('H:i') }}
                        @endif
                    </span>
                </div>
            @endif

            @if(!empty($context['next_event']))
                <div class="calendar-context-event">
                    <small>Próximo evento externo</small>
                    <strong>{{ $context['next_event']['title'] }}</strong>
                    <span>
                        {{ $context['next_event']['starts_at']?->format('H:i') }}
                        @if($context['next_event']['ends_at'])
                            – {{ $context['next_event']['ends_at']->format('H:i') }}
                        @endif
                    </span>
                </div>
            @endif
        </div>
    @endif

    @if(($context['overlap_count'] ?? 0) > 0)
        <div class="calendar-context-warning">
            Hay {{ $context['overlap_count'] }} solapamiento(s) entre eventos externos con inicio y fin conocidos. Se muestran como señal de contexto; CENTRAL no reprograma nada automáticamente.
        </div>
    @endif
</div>
@endif
