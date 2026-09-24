<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark">
<title>Vencimientos · Central ARPYNET</title>
<link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/obligations-ops.css') }}?v=2.39.2"
    >
</head>
<body>
@php
$base=array_filter(['scope'=>$selectedScope,'focus'=>$focus,'q'=>$search!==''?$search:null]);
$focuses=['attention'=>'Requieren atención','overdue'=>'Vencidos','today'=>'Hoy','upcoming'=>'Próximos','pending'=>'Pendientes','paid'=>'Pagados','skipped'=>'Omitidos','all'=>'Todos'];
$createScope=$selectedScope && in_array($selectedScope,$writableOrganizationIds,true)?$selectedScope:($writableOrganizationIds[0]??null);
@endphp
<div class="shell">
<x-operational-page-header
    active="obligations"
    title="Vencimientos"
    subtitle="Obligaciones recurrentes, pagos y alertas"
>
    <x-slot:actions>
        <a
            class="secondary-link"
            href="{{ route('recurring-obligation-front.index', array_filter(['scope' => $selectedScope])) }}"
        >
            Administrar recurrentes
        </a>

        @if($createScope)
            <a
                class="primary-link"
                href="{{ route('recurring-obligation-front.create', ['scope' => $createScope]) }}"
            >
                + Nueva obligación
            </a>
        @endif
    </x-slot:actions>
</x-operational-page-header>

@if(session('obligation_success'))<div class="success">{{ session('obligation_success') }}</div>@endif

@php
$obligationFocusTitle = match (true) {
    $summary['overdue'] > 0 =>
        $summary['overdue'].' vencimientos atrasados requieren acción',
    $summary['today'] > 0 =>
        $summary['today'].' vencimientos deben resolverse hoy',
    default => 'Sin vencimientos urgentes en la vista actual',
};
$obligationFocusTone = $summary['overdue'] > 0
    ? 'danger'
    : ($summary['today'] > 0 ? 'warning' : 'success');
$obligationFocusMeta =
    $summary['pending'].' pendientes · '
    .$summary['upcoming'].' próximos';
@endphp

<x-operational-focus-banner
    :title="$obligationFocusTitle"
    :meta="$obligationFocusMeta"
    :tone="$obligationFocusTone"
>
    <x-slot:actions>
        <a
            class="primary-link"
            href="{{ route('obligation-ops.show', array_merge(
                $base,
                ['focus' => 'attention'],
            )) }}"
        >
            Ver atención
        </a>
    </x-slot:actions>
</x-operational-focus-banner>

<section class="filters">
<div class="filter-label">Ámbito</div><div class="scroll"><a class="chip {{ $selectedScope?'':'active' }}" href="{{ route('obligation-ops.show',array_filter(['focus'=>$focus,'q'=>$search!==''?$search:null])) }}">Todos los ámbitos</a>@foreach($organizations as $organization)<a class="chip {{ $selectedScope===(int)$organization->id?'active':'' }}" href="{{ route('obligation-ops.show',array_filter(['scope'=>$organization->id,'focus'=>$focus,'q'=>$search!==''?$search:null])) }}">{{ $organization->name }}</a>@endforeach</div>
<div class="filter-label">Estado</div><div class="scroll">@foreach($focuses as $value=>$label)<a class="chip {{ $focus===$value?'active':'' }}" href="{{ route('obligation-ops.show',array_merge($base,['focus'=>$value])) }}">{{ $label }}</a>@endforeach</div>
<form class="search" method="get" action="{{ route('obligation-ops.show') }}">@if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif<input type="hidden" name="focus" value="{{ $focus }}"><input type="search" name="q" value="{{ $search }}" placeholder="Buscar obligación, proveedor o referencia..."><button type="submit">Buscar</button></form>
</section>
<section class="stats">
@foreach(['Vencidos'=>$summary['overdue'],'Hoy'=>$summary['today'],'Próximos'=>$summary['upcoming'],'Pendientes'=>$summary['pending'],'Mostrados'=>$summary['total']] as $label=>$value)<div class="stat"><div class="stat-value">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>@endforeach
</section>
<div class="list">
@forelse($occurrences as $occurrence)
@php $canWrite=in_array((int)$occurrence->organization_id,$writableOrganizationIds,true); @endphp
<article class="card">
<div class="card-head"><div><div class="title">{{ $occurrence->obligation?->name ?? 'Obligación' }}</div><div class="meta">{{ $occurrence->organization?->name ?? 'Sin ámbito' }}@if($occurrence->obligation?->provider) · {{ $occurrence->obligation->provider }}@endif</div></div><span class="pill {{ $occurrence->ops_level }}">{{ $occurrence->ops_label }}</span></div>
<div class="pills"><span class="pill">Vence {{ $occurrence->due_date->format('d/m/Y') }}</span>@if($occurrence->obligation?->is_critical)<span class="pill critical">Crítica</span>@endif @if($occurrence->obligation?->category)<span class="pill">{{ $occurrence->obligation->category }}</span>@endif</div>
@if($occurrence->expected_amount!==null || $occurrence->actual_amount!==null || $occurrence->paid_date || $occurrence->payment_reference)<div class="amount">@if($occurrence->expected_amount!==null)<div>Esperado: {{ $occurrence->currency }} {{ number_format((float)$occurrence->expected_amount,2,'.',',') }}</div>@endif @if($occurrence->actual_amount!==null)<div class="meta">Real: {{ $occurrence->currency }} {{ number_format((float)$occurrence->actual_amount,2,'.',',') }}</div>@endif @if($occurrence->paid_date)<div class="meta">Pagado: {{ $occurrence->paid_date->format('d/m/Y') }}</div>@endif @if($occurrence->payment_reference)<div class="meta">Ref.: {{ $occurrence->payment_reference }}</div>@endif</div>@endif
@if($canWrite)
@if($occurrence->status==='pending')
<details><summary>Registrar pago</summary><form class="payment-form" method="post" action="{{ route('obligation-ops.update',$occurrence) }}">@csrf<input type="hidden" name="action" value="paid">@if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif<input type="hidden" name="focus" value="{{ $focus }}">@if($search!=='')<input type="hidden" name="q" value="{{ $search }}">@endif<label class="field">Monto pagado<input type="number" step="0.01" min="0" name="actual_amount" value="{{ $occurrence->expected_amount }}"></label><label class="field">Fecha de pago<input type="date" name="paid_date" value="{{ $now->format('Y-m-d') }}"></label><label class="field">Referencia<input type="text" name="payment_reference" maxlength="255" placeholder="Operación, factura, recibo..."></label><label class="field">URL del comprobante<input type="url" name="receipt_url" placeholder="https://..."></label><button class="pay-button" type="submit">Registrar pago</button></form></details>
<div class="actions"><form method="post" action="{{ route('obligation-ops.update',$occurrence) }}">@csrf<input type="hidden" name="action" value="skipped">@if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif<input type="hidden" name="focus" value="{{ $focus }}">@if($search!=='')<input type="hidden" name="q" value="{{ $search }}">@endif<button class="small-action skip" type="submit">Omitir</button></form></div>
@else
<div class="actions"><form method="post" action="{{ route('obligation-ops.update',$occurrence) }}">@csrf<input type="hidden" name="action" value="pending">@if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif<input type="hidden" name="focus" value="{{ $focus }}">@if($search!=='')<input type="hidden" name="q" value="{{ $search }}">@endif<button class="small-action reopen" type="submit">Reabrir</button></form></div>
@endif
@endif
<div class="card-foot"><span class="muted">{{ $canWrite?'Edición disponible':'Solo lectura' }}</span>@if($occurrence->obligation)<a class="card-link" href="{{ route('recurring-obligation-front.edit',$occurrence->obligation) }}">{{ $canWrite?'Editar recurrente':'Ver recurrente' }} →</a>@endif</div>
</article>
@empty<div class="empty">No hay vencimientos que coincidan con estos filtros.</div>@endforelse
</div>

<div class="operational-context-heading">Contexto financiero</div>
@foreach($moneySummary as $currency=>$money)<div class="money-currency">Resumen {{ $currency }}</div><section class="money-grid">@foreach(['Esperado'=>$money['expected'],'Pendiente'=>$money['pending'],'Vencido'=>$money['overdue'],'Pagado'=>$money['paid']] as $label=>$value)<div class="money"><div class="money-value">{{ $currency }} {{ number_format($value,2,'.',',') }}</div><div class="money-label">{{ $label }}</div></div>@endforeach</section>@endforeach
</div>
<x-operational-theme /><x-operational-interactions />
</body></html>
