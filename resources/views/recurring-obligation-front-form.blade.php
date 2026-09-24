<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark">
<title>{{ $obligation?'Editar obligación':'Nueva obligación' }} · Central ARPYNET</title>
<link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/recurring-obligation-front-form.css') }}?v=2.39.1"
    >
</head>
<body>
<div class="shell">
<header class="topbar"><a class="brand" href="{{ route('daily-ops.show') }}">Central ARPYNET</a><x-operational-nav active="obligations" /></header>
@if(session('recurring_obligation_success'))<div class="success">{{ session('recurring_obligation_success') }}</div>@endif
@if($errors->any())<div class="errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="hero"><h1>{{ $obligation?'Editar obligación':'Nueva obligación' }}</h1><div class="subtitle">Configura la regla que genera los vencimientos futuros.</div></section>
@if(!$canWrite)<div class="readonly">Tienes acceso de solo lectura a esta obligación recurrente.</div>@endif
@php $organizationId=(int)old('organization_id',$obligation?->organization_id ?? $defaultOrganizationId); @endphp
<section class="panel">
<form method="post" action="{{ $obligation?route('recurring-obligation-front.update',$obligation):route('recurring-obligation-front.store') }}">@csrf
<div class="grid">
<div class="field"><label for="organization_id">Empresa / ámbito</label><select id="organization_id" name="organization_id" {{ $obligation||!$canWrite?'disabled':'' }} required>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected($organizationId===(int)$organization->id)>{{ $organization->name }}</option>@endforeach</select>@if($obligation)<input type="hidden" name="organization_id" value="{{ $obligation->organization_id }}"><div class="help">El ámbito de una obligación existente no se cambia desde esta ficha.</div>@endif</div>
<div class="field"><label for="name">Nombre</label><input id="name" name="name" maxlength="255" required value="{{ old('name',$obligation?->name) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="category">Categoría</label><select id="category" name="category" {{ !$canWrite?'disabled':'' }} required>@foreach(\App\Models\RecurringObligation::categoryOptions() as $value=>$label)<option value="{{ $value }}" @selected(old('category',$obligation?->category ?? 'service')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="frequency">Frecuencia</label><select id="frequency" name="frequency" {{ !$canWrite?'disabled':'' }} required>@foreach(\App\Models\RecurringObligation::frequencyOptions() as $value=>$label)<option value="{{ $value }}" @selected(old('frequency',$obligation?->frequency ?? 'monthly')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="anchor_date">Fecha base</label><input id="anchor_date" type="date" name="anchor_date" required value="{{ old('anchor_date',$obligation?->anchor_date?->format('Y-m-d') ?? now()->toDateString()) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="end_date">Fecha fin opcional</label><input id="end_date" type="date" name="end_date" value="{{ old('end_date',$obligation?->end_date?->format('Y-m-d')) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="expected_amount">Monto esperado</label><input id="expected_amount" type="number" min="0" step="0.01" name="expected_amount" value="{{ old('expected_amount',$obligation?->expected_amount) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="currency">Moneda</label><select id="currency" name="currency" {{ !$canWrite?'disabled':'' }} required>@foreach(['PEN'=>'Soles (PEN)','USD'=>'Dólares (USD)','EUR'=>'Euros (EUR)'] as $value=>$label)<option value="{{ $value }}" @selected(old('currency',$obligation?->currency ?? 'PEN')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="reminder_days_before">Avisar días antes</label><input id="reminder_days_before" type="number" min="0" max="365" name="reminder_days_before" required value="{{ old('reminder_days_before',$obligation?->reminder_days_before ?? 7) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="provider">Proveedor / contraparte</label><input id="provider" name="provider" maxlength="255" value="{{ old('provider',$obligation?->provider) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="reference">Referencia</label><input id="reference" name="reference" maxlength="255" value="{{ old('reference',$obligation?->reference) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field"><label for="drive_url">Carpeta / documento</label><input id="drive_url" type="url" name="drive_url" maxlength="255" value="{{ old('drive_url',$obligation?->drive_url) }}" {{ !$canWrite?'disabled':'' }}></div>
<div class="field span-2"><label for="description">Descripción</label><textarea id="description" name="description" {{ !$canWrite?'disabled':'' }}>{{ old('description',$obligation?->description) }}</textarea></div>
<div class="field span-2"><label for="notes">Notas</label><textarea id="notes" name="notes" {{ !$canWrite?'disabled':'' }}>{{ old('notes',$obligation?->notes) }}</textarea></div>
<div><label class="check"><input type="checkbox" name="is_critical" value="1" @checked(old('is_critical',$obligation?->is_critical ?? false)) {{ !$canWrite?'disabled':'' }}> Obligación crítica</label></div>
<div><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$obligation?->is_active ?? true)) {{ !$canWrite?'disabled':'' }}> Regla activa</label></div>
</div>
<div class="actions">@if($canWrite)<button class="primary" type="submit" data-busy-label="Guardando…">{{ $obligation?'Guardar cambios':'Crear obligación' }}</button>@endif<a class="secondary" href="{{ route('recurring-obligation-front.index') }}">Volver a obligaciones</a><a class="secondary" href="{{ route('obligation-ops.show') }}">Ver vencimientos</a></div>
</form>
</section>
</div>
<x-operational-theme /><x-operational-interactions />
</body></html>
