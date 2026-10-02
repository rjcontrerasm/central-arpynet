<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Detalle del servicio · Central ARPYNET</title>
    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/service-order-front-show.css') }}?v={{ filemtime(public_path('central-assets/pages/service-order-front-show.css')) }}"
    >
</head>
<body>
<div class="shell">
    <x-operational-page-header
        active="services"
        title="Detalle del servicio"
        subtitle="Resumen contractual, operativo y financiero."
    >
        <x-slot:actions>
            @if($canEdit)
                <a class="header-action primary-action" href="{{ route('service-order-front.edit',$serviceOrder) }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    Editar servicio
                </a>
            @endif
            <a class="header-action" href="{{ route('service-orders-ops.show') }}">Volver a servicios</a>
        </x-slot:actions>
    </x-operational-page-header>

    @php
        $stageLabel = AppModelsServiceOrder::stageOptions()[$serviceOrder->stage] ?? $serviceOrder->stage;
        $contractType = [
            'none' => 'Sin contrato',
            'contract' => 'Contrato',
            'direct_order' => 'Orden directa',
            'other' => 'Otro',
        ][$serviceOrder->contract_document_type ?? 'none'] ?? 'Sin definir';
        $contractAmount = (float)($serviceOrder->contract_amount ?? $serviceOrder->amount ?? 0);
        $issuedAmount = $serviceOrder->execution_issued_amount;
        $invoicedAmount = $serviceOrder->invoiced_total;
        $paidAmount = $serviceOrder->paid_total;
    @endphp

    <section class="service-hero-card">
        <div class="service-hero-main">
            <div class="eyebrow">{{ $serviceOrder->organization?->name ?? 'Sin ámbito' }} · {{ $serviceOrder->client?->name ?? 'Sin cliente' }}</div>
            <h2>{{ $serviceOrder->title }}</h2>
            @if($serviceOrder->description)<p>{{ $serviceOrder->description }}</p>@endif
            <div class="service-pills">
                <span class="pill">{{ $stageLabel }}</span>
                @if($serviceOrder->contract_number)<span class="pill">Contrato {{ $serviceOrder->contract_number }}</span>@endif
                @if($serviceOrder->workTeam)<span class="pill">Equipo {{ $serviceOrder->workTeam->name }}</span>@endif
                @if($serviceOrder->assignee)<span class="pill">Responsable {{ $serviceOrder->assignee->name }}</span>@endif
            </div>
        </div>
        <div class="hero-metric">
            <span>Monto contractual</span>
            <strong>{{ $serviceOrder->currency }} {{ number_format($contractAmount,2,'.',',') }}</strong>
            <small>{{ $serviceOrder->includes_tax ? 'Incluye impuestos' : 'No incluye impuestos' }}</small>
        </div>
    </section>

    <section class="summary-grid">
        <article class="summary-card">
            <span>Emitido</span>
            <strong>{{ $serviceOrder->currency }} {{ number_format($issuedAmount,2,'.',',') }}</strong>
        </article>
        <article class="summary-card">
            <span>Facturado</span>
            <strong>{{ $serviceOrder->currency }} {{ number_format($invoicedAmount,2,'.',',') }}</strong>
        </article>
        <article class="summary-card">
            <span>Cobrado</span>
            <strong>{{ $serviceOrder->currency }} {{ number_format($paidAmount,2,'.',',') }}</strong>
        </article>
        <article class="summary-card">
            <span>Hitos</span>
            <strong>{{ $serviceOrder->milestones->whereNotNull('conformity_date')->count() }}/{{ $serviceOrder->milestones->count() }}</strong>
        </article>
    </section>

    <div class="detail-grid">
        <section class="detail-card">
            <div class="section-head">
                <div>
                    <div class="section-kicker">Contrato</div>
                    <h3>Compromiso contractual</h3>
                </div>
                @if($serviceOrder->contract_url)
                    <a class="document-button" href="{{ $serviceOrder->contract_url }}" target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                        Abrir contrato
                    </a>
                @endif
            </div>
            <dl class="data-list">
                <div><dt>Tipo</dt><dd>{{ $contractType }}</dd></div>
                <div><dt>N.º contrato / referencia</dt><dd>{{ $serviceOrder->contract_number ?: '—' }}</dd></div>
                <div><dt>Fecha</dt><dd>{{ $serviceOrder->contract_date?->format('d/m/Y') ?: '—' }}</dd></div>
                <div><dt>Vigencia</dt><dd>{{ $serviceOrder->start_date?->format('d/m/Y') ?: '—' }} → {{ $serviceOrder->end_date?->format('d/m/Y') ?: '—' }}</dd></div>
                <div><dt>Cotización</dt><dd>{{ $serviceOrder->quotation_number ?: '—' }} @if($serviceOrder->quotation_date)· {{ $serviceOrder->quotation_date->format('d/m/Y') }}@endif</dd></div>
                <div><dt>Monto</dt><dd>{{ $serviceOrder->currency }} {{ number_format($contractAmount,2,'.',',') }}</dd></div>
            </dl>
        </section>

        <section class="detail-card">
            <div class="section-head">
                <div>
                    <div class="section-kicker">Seguimiento</div>
                    <h3>Estado operativo</h3>
                </div>
            </div>
            <dl class="data-list">
                <div><dt>Etapa</dt><dd>{{ $stageLabel }}</dd></div>
                <div><dt>Responsable</dt><dd>{{ $serviceOrder->assignee?->name ?? '—' }}</dd></div>
                <div><dt>Equipo</dt><dd>{{ $serviceOrder->workTeam?->name ?? '—' }}</dd></div>
                <div><dt>Próxima acción</dt><dd>{{ $serviceOrder->next_action ?: 'Sin definir' }}</dd></div>
                <div><dt>Seguimiento</dt><dd>{{ $serviceOrder->next_action_at?->format('d/m/Y H:i') ?: '—' }}</dd></div>
                <div><dt>Conformidad general</dt><dd>{{ $serviceOrder->conformity_date?->format('d/m/Y') ?: '—' }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="detail-card full-card">
        <div class="section-head">
            <div>
                <div class="section-kicker">Ejecución presupuestal</div>
                <h3>Órdenes vinculadas</h3>
            </div>
        </div>
        <div class="record-list">
            @forelse($serviceOrder->executionOrders as $order)
                <article class="record-row">
                    <div class="record-copy">
                        <strong>{{ AppModelsServiceOrderExecutionOrder::documentTypeOptions()[$order->document_type] ?? 'Documento' }} {{ $order->document_number ?: 'pendiente' }}</strong>
                        <span>Ejercicio {{ $order->fiscal_year ?: '—' }} · {{ $serviceOrder->currency }} {{ number_format((float)($order->amount ?? 0),2,'.',',') }} · {{ AppModelsServiceOrderExecutionOrder::statusOptions()[$order->status] ?? $order->status }}</span>
                    </div>
                    @if($order->document_url)
                        <a class="document-button" href="{{ $order->document_url }}" target="_blank" rel="noopener noreferrer">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                            Abrir orden
                        </a>
                    @endif
                </article>
            @empty
                <div class="empty-state">No hay órdenes de ejecución registradas.</div>
            @endforelse
        </div>
    </section>

    <section class="detail-card full-card">
        <div class="section-head">
            <div>
                <div class="section-kicker">Finanzas</div>
                <h3>Facturación y cobranza</h3>
            </div>
        </div>
        <div class="record-list">
            @forelse($serviceOrder->invoices as $invoice)
                <article class="record-row">
                    <div class="record-copy">
                        <strong>Factura {{ $invoice->number ?: 'pendiente' }}</strong>
                        <span>{{ $serviceOrder->currency }} {{ number_format((float)($invoice->amount ?? 0),2,'.',',') }} · {{ $invoice->display_status }} @if($invoice->due_date)· vence {{ $invoice->due_date->format('d/m/Y') }}@endif</span>
                    </div>
                    @if($invoice->document_url)
                        <a class="document-button" href="{{ $invoice->document_url }}" target="_blank" rel="noopener noreferrer">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                            Abrir factura
                        </a>
                    @endif
                </article>
            @empty
                <div class="empty-state">No hay facturas registradas.</div>
            @endforelse
        </div>
    </section>

    <section class="detail-card full-card">
        <div class="section-head">
            <div>
                <div class="section-kicker">Cronograma</div>
                <h3>Hitos / entregables</h3>
            </div>
        </div>
        <div class="record-list">
            @forelse($serviceOrder->milestones as $milestone)
                @php
                    $taskDone = $milestone->task?->status === 'completed' || $milestone->delivered_date || $milestone->conformity_date;
                @endphp
                <article class="milestone-row">
                    <div class="milestone-index">{{ $milestone->sequence }}</div>
                    <div class="record-copy">
                        <strong>{{ $milestone->title }}</strong>
                        <span>{{ $milestone->contractual_due_date ? 'Vence '.$milestone->contractual_due_date->format('d/m/Y') : 'Sin fecha contractual' }} @if($milestone->amount)· {{ $serviceOrder->currency }} {{ number_format((float)$milestone->amount,2,'.',',') }}@endif</span>
                        <div class="mini-sequence">
                            <span class="{{ $taskDone ? 'done' : 'current' }}">1 Trabajo</span>
                            <span class="{{ $milestone->delivered_date ? 'done' : ($taskDone ? 'current' : '') }}">2 Entregado</span>
                            <span class="{{ $milestone->conformity_date ? 'done' : ($milestone->delivered_date ? 'current' : '') }}">3 Conforme</span>
                        </div>
                    </div>
                    <span class="status-badge">{{ $milestone->contractual_status_label }}</span>
                </article>
            @empty
                <div class="empty-state">No hay hitos registrados.</div>
            @endforelse
        </div>
    </section>

    @if($serviceOrder->drive_url || $serviceOrder->notes)
        <section class="detail-card full-card">
            <div class="section-head">
                <div>
                    <div class="section-kicker">Documentación</div>
                    <h3>Archivos y notas</h3>
                </div>
                @if($serviceOrder->drive_url)
                    <a class="document-button" href="{{ $serviceOrder->drive_url }}" target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                        Abrir carpeta
                    </a>
                @endif
            </div>
            @if($serviceOrder->notes)<p class="notes">{{ $serviceOrder->notes }}</p>@endif
        </section>
    @endif
</div>
<x-operational-theme />
<x-operational-interactions />
</body>
</html>
