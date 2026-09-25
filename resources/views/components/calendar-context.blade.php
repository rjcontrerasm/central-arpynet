@if(($context['status'] ?? null) === 'ok')
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
