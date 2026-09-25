<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark">
<title>Obligaciones recurrentes · Central ARPYNET</title>
<link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/recurring-obligations-front.css') }}?v={{ filemtime(public_path('central-assets/pages/recurring-obligations-front.css')) }}"
    >
</head>
<body>
@php
$createScope=$selectedScope && in_array($selectedScope,$writableOrganizationIds,true)?$selectedScope:($writableOrganizationIds[0]??null);
@endphp
<div class="shell">
<header class="topbar"><a class="brand" href="{{ route('daily-ops.show') }}">Central ARPYNET</a><x-operational-nav active="obligations" /></header>
<section class="hero"><div><h1>Obligaciones recurrentes</h1><div class="subtitle">Definiciones que generan automáticamente los vencimientos operativos.</div></div>@if($createScope)<a class="primary" href="{{ route('recurring-obligation-front.create',['scope'=>$createScope]) }}">Nueva obligación</a>@endif</section>
<form class="filters" method="get" action="{{ route('recurring-obligation-front.index') }}">
<select name="scope"><option value="">Todos los ámbitos</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected($selectedScope===(int)$organization->id)>{{ $organization->name }}</option>@endforeach</select>
<input name="q" maxlength="120" value="{{ $search }}" placeholder="Buscar obligación, proveedor o referencia"><button type="submit">Buscar</button>
</form>
<div class="back-row"><a class="secondary" href="{{ route('obligation-ops.show',array_filter(['scope'=>$selectedScope])) }}">← Volver a vencimientos</a></div>
<section class="list">
@forelse($obligations as $obligation)
@php $canWrite=in_array((int)$obligation->organization_id,$writableOrganizationIds,true); @endphp
<article class="card">
<div class="card-head"><div><div class="title">{{ $obligation->name }}</div><div class="meta">{{ $obligation->organization?->name ?? 'Sin ámbito' }}@if($obligation->provider) · {{ $obligation->provider }}@endif</div></div><span class="pill {{ $obligation->is_active?'active':'inactive' }}">{{ $obligation->is_active?'Activa':'Inactiva' }}</span></div>
<div class="pills"><span class="pill">{{ \App\Models\RecurringObligation::categoryOptions()[$obligation->category] ?? $obligation->category }}</span><span class="pill">{{ \App\Models\RecurringObligation::frequencyOptions()[$obligation->frequency] ?? $obligation->frequency }}</span>@if($obligation->is_critical)<span class="pill critical">Crítica</span>@endif</div>
<div class="meta">Ancla: {{ $obligation->anchor_date?->format('d/m/Y') }}@if($obligation->end_date) · Hasta {{ $obligation->end_date->format('d/m/Y') }}@endif</div>
@if($obligation->expected_amount!==null)<div class="meta">Esperado: {{ $obligation->currency }} {{ number_format((float)$obligation->expected_amount,2,'.',',') }}</div>@endif
@if($obligation->reference)<div class="meta">Ref.: {{ $obligation->reference }}</div>@endif
<div class="card-foot"><span class="meta">Aviso {{ $obligation->reminder_days_before }} días antes</span><a class="link" href="{{ route('recurring-obligation-front.edit',$obligation) }}">{{ $canWrite?'Editar':'Ver' }} →</a></div>
</article>
@empty<div class="empty">No hay obligaciones recurrentes para los filtros seleccionados.</div>@endforelse
</section>
</div>
<x-operational-theme /><x-operational-interactions />
</body></html>
