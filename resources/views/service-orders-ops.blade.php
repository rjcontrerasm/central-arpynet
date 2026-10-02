<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Servicios · Central ARPYNET</title>
    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/service-orders-ops.css') }}?v={{ filemtime(public_path('central-assets/pages/service-orders-ops.css')) }}"
    >
    <script
        src="{{ asset('central-assets/pages/service-orders-ops.js') }}?v={{ filemtime(public_path('central-assets/pages/service-orders-ops.js')) }}"
        defer
    ></script>
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
            @php
                $canWriteOrder = in_array((int)$order->organization_id, $writableOrganizationIds, true);
                $orderMilestoneCount = $order->milestones->count();
                $orderMilestoneCompleted = $order->milestones
                    ->filter(
                        fn ($milestone) =>
                            $milestone->conformity_date
                            || $milestone->task?->status === 'completed',
                    )
                    ->count();
                $orderMilestoneOverdue = $order->milestones
                    ->filter(
                        fn ($milestone) => $milestone->is_overdue,
                    )
                    ->count();
                $orderMilestoneProgress = $orderMilestoneCount > 0
                    ? (int) round(
                        ($orderMilestoneCompleted / $orderMilestoneCount) * 100,
                    )
                    : 0;
            @endphp
            <article class="card service-card">
                <div class="service-card-head">
                    <div class="service-card-identity">
                        <a class="title title-link" href="{{ route('service-order-front.show',$order) }}">
                            {{ $order->title }}
                        </a>
                        <div class="service-card-meta">
                            {{ $order->organization?->name ?? 'Sin ámbito' }}
                            <span aria-hidden="true">·</span>
                            {{ $order->client?->name ?? 'Sin cliente' }}
                            @if($order->order_number)
                                <span aria-hidden="true">·</span>
                                OS {{ $order->order_number }}
                            @endif
                        </div>
                    </div>
                    <div class="service-card-status">
                        <span class="pill {{ $order->ops_level }}">{{ $order->ops_label }}</span>
                        <span class="pill {{ $order->fin_status }}">{{ $order->fin_label }}</span>
                    </div>
                </div>

                <div class="service-card-context">
                    <span>{{ $stageOptions[$order->stage] ?? $order->stage }}</span>
                    <span>{{ $order->ops_days_in_stage }} días en etapa</span>
                    @if($order->workTeam)<span>{{ $order->workTeam->name }}</span>@endif
                    @if($order->health_score !== null)
                        <span class="health-inline {{ $order->health_css }}">Health {{ $order->health_score }}/100 · {{ $order->health_label }}</span>
                    @endif
                </div>

                <div class="service-card-metrics">
                    <div>
                        <span>Monto</span>
                        <strong>{{ $order->currency }} {{ number_format($order->fin_service_amount,2,'.',',') }}</strong>
                    </div>
                    <div class="milestone-metric">
                        <span>Hitos</span>
                        <strong>
                            {{ $orderMilestoneCompleted }}/{{ $orderMilestoneCount }}
                            @if($orderMilestoneOverdue > 0)
                                <small class="metric-overdue">· {{ $orderMilestoneOverdue }} vencido{{ $orderMilestoneOverdue === 1 ? '' : 's' }}</small>
                            @endif
                        </strong>
                        @if($orderMilestoneCount > 0)
                            <span
                                class="milestone-progress"
                                role="progressbar"
                                aria-label="Avance de hitos"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="{{ $orderMilestoneProgress }}"
                            >
                                <span style="width:{{ $orderMilestoneProgress }}%"></span>
                            </span>
                        @endif
                    </div>
                    <div>
                        <span>Fin contractual</span>
                        <strong>{{ $order->end_date?->format('d/m/Y') ?: '—' }}</strong>
                    </div>
                </div>

                <div class="service-next-action {{ $order->next_action ? '' : 'is-empty' }}">
                    <div class="service-next-label">Siguiente acción</div>
                    <div class="service-next-main">
                        <div>
                            <strong>{{ $order->next_action ?: 'No definida' }}</strong>
                            @if($order->next_action_at)
                                <span>{{ $order->next_action_at->format('d/m/Y H:i') }}</span>
                            @elseif(! $order->next_action)
                                <span>Aún no hay seguimiento programado.</span>
                            @endif
                        </div>
                        @if(! $order->next_action && $canWriteOrder)
                            <button
                                class="define-action-button"
                                type="button"
                                data-open-service-panel="follow-up-{{ $order->id }}"
                            >
                                Definir ahora
                            </button>
                        @endif
                    </div>
                    @foreach($order->ops_reasons as $reason)
                        <div class="reason">{{ $reason }}</div>
                    @endforeach
                </div>

                @if($orderMilestoneOverdue > 0 || $order->fin_is_invoiced || $order->paid_date)
                    <div class="service-card-alerts">
                        @if($orderMilestoneOverdue > 0)
                            <span class="alert-chip danger">{{ $orderMilestoneOverdue }} hito{{ $orderMilestoneOverdue === 1 ? '' : 's' }} vencido{{ $orderMilestoneOverdue === 1 ? '' : 's' }}</span>
                        @endif
                        @if($order->fin_is_invoiced)
                            <span class="alert-chip">Factura {{ $order->invoice_number ?: 'sin número' }} · {{ $order->currency }} {{ number_format($order->fin_invoice_amount,2,'.',',') }}</span>
                        @endif
                        @if($order->paid_date)
                            <span class="alert-chip success">Pagado {{ $order->paid_date->format('d/m/Y') }}</span>
                        @endif
                    </div>
                @endif

                @if($canWriteOrder)
                    <div class="service-quick-actions" data-service-panels>
                        <details
                            class="service-action-panel"
                            id="follow-up-{{ $order->id }}"
                            data-service-panel
                        >
                            <summary>Actualizar seguimiento</summary>
                            <form class="editor compact-editor" method="POST" action="{{ route('service-orders-ops.update',$order) }}">
                                @csrf
                                @if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif
                                @if($selectedStage)<input type="hidden" name="filter_stage" value="{{ $selectedStage }}">@endif
                                <input type="hidden" name="focus" value="{{ $focus }}">@if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                                <div class="follow-up-grid">
                                    <label class="field">Etapa<select name="stage" required>@foreach($stageOptions as $value=>$label)<option value="{{ $value }}" @selected($order->stage === $value)>{{ $label }}</option>@endforeach</select></label>
                                    <label class="field span-wide">Siguiente acción<input type="text" name="next_action" value="{{ $order->next_action }}" maxlength="255" placeholder="Ej. enviar informe"></label>
                                    <label class="field">Fecha y hora<input type="datetime-local" name="next_action_at" value="{{ $order->next_action_at?->format('Y-m-d\TH:i') }}"></label>
                                </div>
                                <div class="editor-actions">
                                    <button class="save compact-save" type="submit">Guardar seguimiento</button>
                                </div>
                            </form>
                        </details>

                        <details
                            class="service-action-panel"
                            id="finance-{{ $order->id }}"
                            data-service-panel
                        >
                            <summary>Actualizar finanzas</summary>
                            <form class="editor compact-editor" method="POST" action="{{ route('service-orders-finance.update',$order) }}">
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
                                    <label class="tax-switch-field full">
                                        <span>Incluye impuestos</span>
                                        <input type="hidden" name="includes_tax" value="0">
                                        <span class="tax-switch">
                                            <input type="checkbox" name="includes_tax" value="1" @checked($order->includes_tax)>
                                            <span aria-hidden="true"></span>
                                        </span>
                                    </label>
                                </div>
                                <div class="editor-actions">
                                    <button class="save compact-save" type="submit">Guardar finanzas</button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endif

                <div class="service-card-actions">
                    <a class="service-primary-action" href="{{ route('service-order-front.show',$order) }}">
                        <span class="view-icon" aria-hidden="true"></span>
                        Ver ficha
                    </a>
                    @if($canWriteOrder)
                        <a class="service-secondary-action" href="{{ route('service-order-front.edit',$order) }}">Editar</a>
                    @endif
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
