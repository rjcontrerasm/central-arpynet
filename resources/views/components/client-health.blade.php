@props(['clients'])

@if (($clients ?? collect())->isNotEmpty())
    <section class="section">
        <div class="section-head">
            <h2>Health Score de clientes</h2>
            <span class="meta">
                Servicios activos · peor salud primero
            </span>
        </div>

        <div class="list">
            @foreach ($clients->take(8) as $client)
                <a
                    class="item"
                    href="{{ route(
                        'service-orders-ops.show',
                        [
                            'scope' => $client['organization_id'],
                            'focus' => 'all',
                        ],
                    ) }}"
                >
                    <div class="item-head">
                        <div>
                            <div class="item-title">
                                {{ $client['client_name'] }}
                            </div>

                            <div class="meta">
                                {{ $client['organization_name'] }}
                                · {{ $client['services_count'] }}
                                {{ $client['services_count'] === 1
                                    ? 'servicio activo'
                                    : 'servicios activos' }}
                            </div>
                        </div>

                        <span class="pill {{ $client['css'] }}">
                            {{ $client['score'] }}/100
                            · {{ $client['label'] }}
                        </span>
                    </div>

                    <div class="summary-detail">
                        <span>
                            Peor servicio:
                            {{ $client['worst_service']['title'] }}
                            ({{ $client['worst_service']['score'] }}/100)
                        </span>

                        @if ($client['risk_count'] > 0)
                            <span class="dot">·</span>
                            <span>
                                {{ $client['risk_count'] }} en riesgo
                            </span>
                        @endif

                        @if ($client['overdue_count'] > 0)
                            <span class="dot">·</span>
                            <span>
                                {{ $client['overdue_count'] }} con cobro vencido
                            </span>
                        @endif
                    </div>

                    @if ($client['reasons']->isNotEmpty())
                        <div class="reason">
                            {{ $client['reasons']->implode(' · ') }}
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif
