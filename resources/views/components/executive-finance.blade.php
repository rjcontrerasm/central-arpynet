@props(['finance'])

@if (($finance['currencies'] ?? collect())->isNotEmpty())
    <section class="section">
        <div class="section-head">
            <h2>Finanzas ejecutivas</h2>
            <span class="meta">
                Proyección operativa · sin conversión entre monedas
            </span>
        </div>

        <div class="money-groups">
            @foreach ($finance['currencies'] as $currency => $data)
                <div class="money-group">
                    <div class="money-title">
                        {{ $currency }} · posición operativa
                    </div>

                    <div class="money-grid">
                        @foreach ([
                            'Por facturar' => $data['service']['pending_invoice'],
                            'Facturado' => $data['service']['invoiced'],
                            'Cobrado' => $data['service']['collected'],
                            'Por cobrar' => $data['service']['receivable'],
                            'Cobranza vencida' => $data['service']['overdue'],
                        ] as $label => $value)
                            <div class="money">
                                <div class="money-value">
                                    {{ $currency }}
                                    {{ number_format($value, 2, '.', ',') }}
                                </div>
                                <div class="money-label">{{ $label }}</div>
                            </div>
                        @endforeach

                        <div class="money">
                            <div class="money-value">
                                {{ $data['service']['collection_rate'] === null
                                    ? '—'
                                    : number_format($data['service']['collection_rate'], 1).'%'
                                }}
                            </div>
                            <div class="money-label">Tasa de cobro sobre facturado</div>
                        </div>
                    </div>

                    <div class="money-title" style="margin-top:8px">
                        Aging de cartera
                    </div>

                    <div class="money-grid">
                        @foreach ([
                            'Vigente / no vencida' => $data['aging']['current'],
                            '1–30 días vencidos' => $data['aging']['overdue_1_30'],
                            '31–60 días vencidos' => $data['aging']['overdue_31_60'],
                            '61+ días vencidos' => $data['aging']['overdue_61_plus'],
                        ] as $label => $value)
                            <div class="money">
                                <div class="money-value">
                                    {{ $currency }}
                                    {{ number_format($value, 2, '.', ',') }}
                                </div>
                                <div class="money-label">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="money-title" style="margin-top:8px">
                        Próximos compromisos
                    </div>

                    <div class="money-grid">
                        @foreach ([
                            'Cobros programados · 7 días' => $data['schedule']['collections_7d'],
                            'Obligaciones programadas · 7 días' => $data['schedule']['obligations_7d'],
                            'Proyección operativa neta · 7 días' => $data['schedule']['net_7d'],
                            'Cobros programados · 30 días' => $data['schedule']['collections_30d'],
                            'Obligaciones programadas · 30 días' => $data['schedule']['obligations_30d'],
                            'Proyección operativa neta · 30 días' => $data['schedule']['net_30d'],
                            'Obligaciones ya vencidas' => $data['schedule']['obligations_overdue'],
                        ] as $label => $value)
                            <div class="money">
                                <div class="money-value">
                                    {{ $currency }}
                                    {{ number_format($value, 2, '.', ',') }}
                                </div>
                                <div class="money-label">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if ($data['top_receivable_clients']->isNotEmpty())
                        <div class="money-title" style="margin-top:8px">
                            Concentración de cuentas por cobrar
                        </div>

                        <div class="list">
                            @foreach ($data['top_receivable_clients'] as $client)
                                <div class="item">
                                    <div class="item-head">
                                        <div>
                                            <div class="item-title">
                                                {{ $client['name'] }}
                                            </div>
                                            <div class="meta">
                                                {{ $client['orders'] }}
                                                {{ $client['orders'] === 1 ? 'documento' : 'documentos' }}
                                                · {{ number_format($client['share'], 1) }}% de la cartera
                                            </div>
                                        </div>

                                        <span class="pill">
                                            {{ $currency }}
                                            {{ number_format($client['amount'], 2, '.', ',') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="meta" style="margin-top:10px">
            La proyección operativa compara únicamente cobros con fecha futura registrada
            contra obligaciones pendientes del mismo horizonte. No representa saldo bancario,
            flujo de caja contable ni rentabilidad.
        </div>
    </section>
@endif
