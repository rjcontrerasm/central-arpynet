<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Servicios · Central ARPYNET</title>
    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/service-orders-ops.css') }}?v=2.39.1"
    >
</head>
<body>
@php
    $base = array_filter([
        'scope' => $selectedScope,
        'stage' => $selectedStage,
        'focus' => $focus,
        'finance' => $finance,
        'q' => $search !== '' ? $search : null,
    ]);
    $createScope = $selectedScope && in_array($selectedScope, $writableOrganizationIds, true)
        ? $selectedScope
        : ($writableOrganizationIds[0] ?? null);
@endphp
<div class="shell">
    <x-operational-page-header
        active="services"
        title="Servicios"
        subtitle="Órdenes, siguiente acción y estancamiento"
    >
        <x-slot:actions>
            @if($createScope)
                <a
                    class="primary-link"
                    href="{{ route('service-order-front.create', ['scope' => $createScope]) }}"
                >
                    + Nuevo servicio
                </a>
            @endif
        </x-slot:actions>
    </x-operational-page-header>

    @if(session('ops_success'))<div class="success">{{ session('ops_success') }}</div>@endif

    @php
        $serviceFocusTitle = match (true) {
            $summary['critical'] > 0 =>
                $summary['critical'].' servicios requieren atención crítica',
            $summary['attention'] > 0 =>
                $summary['attention'].' servicios requieren seguimiento',
            default => 'Servicios sin alertas operativas inmediatas',
        };
        $serviceFocusTone = $summary['critical'] > 0
            ? 'danger'
            : ($summary['attention'] > 0 ? 'warning' : 'success');
        $serviceFocusMeta =
            $summary['execution'].' en ejecución · '
            .$summary['invoice'].' facturados · '
            .$summary['total'].' mostrados';
    @endphp

    <x-operational-focus-banner
        :title="$serviceFocusTitle"
        :meta="$serviceFocusMeta"
        :tone="$serviceFocusTone"
    >
        <x-slot:actions>
            <a
                class="primary-link"
                href="{{ route('service-orders-ops.show', array_merge(
                    $base,
                    ['focus' => 'attention'],
                )) }}"
            >
                Ver atención
            </a>
        </x-slot:actions>
    </x-operational-focus-banner>

    <section class="filters">
        <div class="filter-label">Ámbito</div>
        <div class="scroll">
            <a class="chip {{ $selectedScope ? '' : 'active' }}" href="{{ route('service-orders-ops.show', array_filter(['stage'=>$selectedStage,'focus'=>$focus,'finance'=>$finance,'q'=>$search !== '' ? $search : null])) }}">Todos</a>
            @foreach($organizations as $organization)
                <a class="chip {{ $selectedScope === $organization->id ? 'active' : '' }}" href="{{ route('service-orders-ops.show', array_filter(['scope'=>$organization->id,'stage'=>$selectedStage,'focus'=>$focus,'finance'=>$finance,'q'=>$search !== '' ? $search : null])) }}">{{ $organization->name }}</a>
            @endforeach
        </div>

        <div class="filter-label">Estado y etapa</div>
        <div class="scroll">
            <a class="chip {{ $focus === 'attention' ? 'active' : '' }}" href="{{ route('service-orders-ops.show', array_merge($base,['focus'=>'attention'])) }}">Requieren atención</a>
            <a class="chip {{ $focus === 'all' ? 'active' : '' }}" href="{{ route('service-orders-ops.show', array_merge($base,['focus'=>'all'])) }}">Todas</a>
            @foreach($stageOptions as $value=>$label)
                <a class="chip {{ $selectedStage === $value ? 'active' : '' }}" href="{{ route('service-orders-ops.show', array_merge($base,['stage'=>$value])) }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="filter-label">Finanzas</div>
        <div class="scroll">
            @foreach(['all'=>'Todas','pending_invoice'=>'Por facturar','receivable'=>'Por cobrar','overdue'=>'Cobro vencido','paid'=>'Pagado'] as $value=>$label)
                <a class="chip {{ $finance === $value ? 'active' : '' }}" href="{{ route('service-orders-ops.show', array_merge($base,['finance'=>$value])) }}">{{ $label }}</a>
            @endforeach
        </div>

        <form class="search" method="GET" action="{{ route('service-orders-ops.show') }}">
            @if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif
            @if($selectedStage)<input type="hidden" name="stage" value="{{ $selectedStage }}">@endif
            <input type="hidden" name="focus" value="{{ $focus }}">
            <input type="hidden" name="finance" value="{{ $finance }}">
            <input type="search" name="q" value="{{ $search }}" placeholder="Buscar título, orden o cotización...">
            <button type="submit">Buscar</button>
        </form>
    </section>

    <section class="stats">
        @foreach(['Críticas'=>$summary['critical'],'A vigilar'=>$summary['attention'],'En ejecución'=>$summary['execution'],'Facturadas'=>$summary['invoice'],'Mostradas'=>$summary['total']] as $label=>$value)
            <div class="stat"><div class="stat-value">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>
        @endforeach
    </section>

    <div class="list">
        @forelse($orders as $order)
            @php $canWriteOrder = in_array((int)$order->organization_id, $writableOrganizationIds, true); @endphp
            <article class="card">
                <div class="card-head">
                    <div>
                        <div class="title">{{ $order->title }}</div>
                        <div class="meta">{{ $order->organization?->name ?? 'Sin ámbito' }} · {{ $order->client?->name ?? 'Sin cliente' }}</div>
                    </div>
                    <span class="pill {{ $order->ops_level }}">{{ $order->ops_label }}</span>
                </div>

                <div class="pills">
                    <span class="pill {{ $order->fin_status }}">{{ $order->fin_label }}</span>
                    @if($order->health_score !== null)<span class="pill {{ $order->health_css }}">Health {{ $order->health_score }}/100 · {{ $order->health_label }}</span>@endif
                    <span class="pill">{{ $stageOptions[$order->stage] ?? $order->stage }}</span>
                    <span class="pill">{{ $order->ops_days_in_stage }} días en etapa</span>
                    @if($order->order_number)<span class="pill">OS {{ $order->order_number }}</span>@endif
                </div>

                <div class="next">
                    <strong>Siguiente acción:</strong> {{ $order->next_action ?: 'Sin definir' }}
                    @if($order->next_action_at)<div class="meta">{{ $order->next_action_at->format('d/m/Y H:i') }}</div>@endif
                    @if($order->end_date)<div class="meta">Fin contractual: {{ $order->end_date->format('d/m/Y') }}</div>@endif
                    @foreach($order->ops_reasons as $reason)<div class="reason">{{ $reason }}</div>@endforeach
                </div>

                <div class="next">
                    <strong>Finanzas:</strong>
                    @if($order->fin_service_amount > 0)<div class="meta">Servicio: {{ $order->currency }} {{ number_format($order->fin_service_amount,2,'.',',') }}</div>@endif
                    @if($order->fin_is_invoiced)<div class="meta">Factura: {{ $order->invoice_number ?: 'sin número' }} · {{ $order->currency }} {{ number_format($order->fin_invoice_amount,2,'.',',') }}</div>@endif
                    @if($order->invoice_due_date)<div class="meta">Vence: {{ $order->invoice_due_date->format('d/m/Y') }}</div>@endif
                    @if($order->paid_date)<div class="meta">Pagado: {{ $order->paid_date->format('d/m/Y') }}</div>@endif
                </div>

                @if($canWriteOrder)
                    <details>
                        <summary>Actualizar finanzas</summary>
                        <form class="editor" method="POST" action="{{ route('service-orders-finance.update',$order) }}">
                            @csrf
                            @if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif
                            @if($selectedStage)<input type="hidden" name="filter_stage" value="{{ $selectedStage }}">@endif
                            <input type="hidden" name="focus" value="{{ $focus }}"><input type="hidden" name="finance" value="{{ $finance }}">
                            @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                            <div class="finance-grid">
                                <label class="field">Moneda<select name="currency">@foreach(['PEN'=>'PEN','USD'=>'USD','EUR'=>'EUR'] as $value=>$label)<option value="{{ $value }}" @selected($order->currency === $value)>{{ $label }}</option>@endforeach</select></label>
                                <label class="field">Monto servicio<input type="number" step="0.01" min="0" name="amount" value="{{ $order->amount }}"></label>
                                <label class="field full">N.° factura<input type="text" name="invoice_number" value="{{ $order->invoice_number }}" maxlength="100"></label>
                                <label class="field">Fecha factura<input type="date" name="invoice_date" value="{{ $order->invoice_date?->format('Y-m-d') }}"></label>
                                <label class="field">Monto factura<input type="number" step="0.01" min="0" name="invoice_amount" value="{{ $order->invoice_amount }}"></label>
                                <label class="field">Vence factura<input type="date" name="invoice_due_date" value="{{ $order->invoice_due_date?->format('Y-m-d') }}"></label>
                                <label class="field">Fecha pago<input type="date" name="paid_date" value="{{ $order->paid_date?->format('Y-m-d') }}"></label>
                                <label class="field full"><span>Incluye impuestos</span><input type="hidden" name="includes_tax" value="0"><input type="checkbox" name="includes_tax" value="1" @checked($order->includes_tax)></label>
                            </div>
                            <button class="save" type="submit">Guardar finanzas</button>
                        </form>
                    </details>

                    <details>
                        <summary>Actualizar seguimiento</summary>
                        <form class="editor" method="POST" action="{{ route('service-orders-ops.update',$order) }}">
                            @csrf
                            @if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif
                            @if($selectedStage)<input type="hidden" name="filter_stage" value="{{ $selectedStage }}">@endif
                            <input type="hidden" name="focus" value="{{ $focus }}">@if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                            <label class="field">Etapa<select name="stage" required>@foreach($stageOptions as $value=>$label)<option value="{{ $value }}" @selected($order->stage === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="field">Siguiente acción<input type="text" name="next_action" value="{{ $order->next_action }}" maxlength="255" placeholder="Ej. enviar informe"></label>
                            <label class="field">Fecha y hora<input type="datetime-local" name="next_action_at" value="{{ $order->next_action_at?->format('Y-m-d\TH:i') }}"></label>
                            <button class="save" type="submit">Guardar seguimiento</button>
                        </form>
                    </details>
                @endif

                <div class="card-foot">
                    <span class="muted">{{ $canWriteOrder ? 'Edición FRONT disponible' : 'Solo lectura' }}</span>
                    <a class="card-link" href="{{ route('service-order-front.edit',$order) }}">{{ $canWriteOrder ? 'Editar servicio' : 'Ver servicio' }} →</a>
                </div>
            </article>
        @empty
            <div class="empty">No hay órdenes que coincidan con estos filtros. @if($createScope) Usa “Nuevo servicio” para registrar la primera. @endif</div>
        @endforelse
    </div>

    <div class="operational-context-heading">
        Contexto financiero de la vista actual
    </div>

    <section class="money-grid">
        @foreach(['Monto servicios'=>$financialSummary['service_amount'],'Facturado'=>$financialSummary['invoiced'],'Por cobrar'=>$financialSummary['outstanding'],'Vencido'=>$financialSummary['overdue'],'Pagado'=>$financialSummary['paid']] as $label=>$value)
            <div class="money"><div class="money-value">S/ {{ number_format($value,2,'.',',') }}</div><div class="money-label">{{ $label }}</div></div>
        @endforeach
    </section>

 </div>
<x-operational-theme />
<x-operational-interactions />
</body>
</html>
