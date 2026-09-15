<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $serviceOrder ? 'Editar servicio' : 'Nuevo servicio' }} · Central ARPYNET</title>
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:light dark}
        *{box-sizing:border-box}body{margin:0;background:#0b1020;color:#f8fafc}a{color:inherit;text-decoration:none}button,input,select,textarea{font:inherit}
        .shell{width:min(100%,1040px);margin:0 auto;padding:24px 16px 80px}.topbar,.hero,.actions{display:flex;align-items:center;justify-content:space-between;gap:12px}.topbar{margin-bottom:24px}.brand{font-weight:850;letter-spacing:-.03em}.hero{align-items:end;margin-bottom:18px}h1{margin:0;font-size:clamp(31px,7vw,46px);line-height:1;letter-spacing:-.05em}.subtitle,.help{color:#94a3b8}.subtitle{margin-top:7px;font-size:13px}
        .panel{padding:16px;border:1px solid #24304b;border-radius:17px;background:#11182b}.grid{display:grid;gap:11px}.field{display:grid;gap:5px}.field label{color:#cbd5e1;font-size:11px;font-weight:820}.field input,.field select,.field textarea{width:100%;min-height:42px;padding:9px 10px;border:1px solid #334155;border-radius:10px;background:#0f172a;color:#f8fafc}.field textarea{min-height:90px;resize:vertical}.check{display:flex;align-items:center;gap:8px;min-height:42px}.check input{width:auto;min-height:auto}.help{font-size:10px;line-height:1.4}.section-title{margin:18px 0 10px;color:#93c5fd;font-size:12px;font-weight:850;text-transform:uppercase;letter-spacing:.05em}.actions{justify-content:flex-start;flex-wrap:wrap;margin-top:15px}.primary,.secondary{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 13px;border-radius:10px;font-size:12px;font-weight:820;cursor:pointer}.primary{border:0;background:#2563eb;color:#fff}.secondary{border:1px solid #334155;background:#0f172a;color:#cbd5e1}.success,.errors,.readonly{margin-bottom:14px;padding:11px 13px;border-radius:12px;font-size:12px}.success{border:1px solid #166534;background:#052e16;color:#bbf7d0}.errors{border:1px solid #991b1b;background:#450a0a;color:#fecaca}.readonly{border:1px solid #475569;background:#0f172a;color:#cbd5e1}
        @media(min-width:760px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.span-2{grid-column:1/-1}.span-3{grid-column:1/-1}}
        @media(prefers-color-scheme:light){body{background:#f8fafc;color:#0f172a}.panel{background:#fff;border-color:#e2e8f0}.field input,.field select,.field textarea,.secondary{background:#fff;color:#0f172a;border-color:#cbd5e1}.field label{color:#334155}.readonly{background:#fff;border-color:#cbd5e1;color:#475569}}
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="services" />
    </div>

    @if(session('service_front_success'))<div class="success">{{ session('service_front_success') }}</div>@endif
    @if($errors->any())<div class="errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="hero"><div><h1>{{ $serviceOrder ? 'Editar servicio' : 'Nuevo servicio' }}</h1><div class="subtitle">Seguimiento comercial, ejecución y finanzas desde CENTRAL Front.</div></div></section>
    @if(! $canWrite)<div class="readonly">Tienes acceso de solo lectura a este servicio.</div>@endif

    @php
        $organizationId=(int)old('organization_id',$serviceOrder?->organization_id ?? $defaultOrganizationId);
        $selectedClient=(int)old('client_id',$serviceOrder?->client_id ?? 0);
        $selectedAssignee=(int)old('assigned_to',$serviceOrder?->assigned_to ?? auth()->id());
    @endphp

    <section class="panel">
        <form method="POST" action="{{ $serviceOrder ? route('service-order-front.update',$serviceOrder) : route('service-order-front.store') }}">
            @csrf
            <div class="section-title">Servicio</div>
            <div class="grid">
                <div class="field">
                    <label for="organization_id">Empresa / ámbito</label>
                    <select id="organization_id" name="organization_id" {{ $serviceOrder || ! $canWrite ? 'disabled' : '' }} required>
                        @foreach($writableOrganizations as $organization)<option value="{{ $organization->id }}" @selected($organizationId === (int)$organization->id)>{{ $organization->name }}</option>@endforeach
                    </select>
                    @if($serviceOrder)<input type="hidden" name="organization_id" value="{{ $serviceOrder->organization_id }}"><div class="help">El ámbito de un servicio existente no se cambia desde esta ficha.</div>@endif
                </div>

                <div class="field">
                    <label for="client_id">Cliente</label>
                    <select id="client_id" name="client_id" {{ ! $canWrite ? 'disabled' : '' }} required>
                        <option value="">Seleccionar cliente</option>
                        @foreach($clientOptions as $id=>$client)
                            <option value="{{ $id }}" data-organizations="{{ implode(',',$client['organization_ids']) }}" @selected($selectedClient === (int)$id)>{{ $client['name'] }} — {{ implode(' · ',$client['organization_names']) }}</option>
                        @endforeach
                    </select>
                    <div class="help">Solo se habilitan clientes asociados a la empresa seleccionada.</div>
                </div>

                <div class="field span-2"><label for="title">Servicio / asunto</label><input id="title" name="title" maxlength="255" required value="{{ old('title',$serviceOrder?->title) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="stage">Etapa</label><select id="stage" name="stage" {{ ! $canWrite ? 'disabled' : '' }} required>@foreach(\App\Models\ServiceOrder::stageOptions() as $value=>$label)<option value="{{ $value }}" @selected(old('stage',$serviceOrder?->stage ?? 'opportunity') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="assigned_to">Responsable</label><select id="assigned_to" name="assigned_to" {{ ! $canWrite ? 'disabled' : '' }}><option value="">Sin asignar</option>@foreach($assigneeOptions as $id=>$assignee)<option value="{{ $id }}" data-organizations="{{ implode(',',$assignee['organization_ids']) }}" @selected($selectedAssignee === (int)$id)>{{ $assignee['name'] }}</option>@endforeach</select></div>
                <div class="field span-2"><label for="description">Descripción</label><textarea id="description" name="description" {{ ! $canWrite ? 'disabled' : '' }}>{{ old('description',$serviceOrder?->description) }}</textarea></div>
                <div class="field"><label for="next_action">Próxima acción</label><input id="next_action" name="next_action" maxlength="255" value="{{ old('next_action',$serviceOrder?->next_action) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="next_action_at">Fecha de seguimiento</label><input id="next_action_at" type="datetime-local" name="next_action_at" value="{{ old('next_action_at',$serviceOrder?->next_action_at?->format('Y-m-d\TH:i')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
            </div>

            <div class="section-title">Cotización y orden</div>
            <div class="grid">
                <div class="field"><label for="quotation_number">N.º de cotización</label><input id="quotation_number" name="quotation_number" maxlength="80" value="{{ old('quotation_number',$serviceOrder?->quotation_number) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="quotation_date">Fecha de cotización</label><input id="quotation_date" type="date" name="quotation_date" value="{{ old('quotation_date',$serviceOrder?->quotation_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="order_number">N.º de orden</label><input id="order_number" name="order_number" maxlength="100" value="{{ old('order_number',$serviceOrder?->order_number) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="order_received_date">Recepción de orden</label><input id="order_received_date" type="date" name="order_received_date" value="{{ old('order_received_date',$serviceOrder?->order_received_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="start_date">Inicio</label><input id="start_date" type="date" name="start_date" value="{{ old('start_date',$serviceOrder?->start_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="end_date">Fin previsto / contractual</label><input id="end_date" type="date" name="end_date" value="{{ old('end_date',$serviceOrder?->end_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
            </div>

            <div class="section-title">Monto y facturación</div>
            <div class="grid">
                <div class="field"><label for="amount">Monto de la operación</label><input id="amount" type="number" min="0" step="0.01" name="amount" value="{{ old('amount',$serviceOrder?->amount) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="currency">Moneda</label><select id="currency" name="currency" {{ ! $canWrite ? 'disabled' : '' }} required>@foreach(['PEN'=>'Soles (PEN)','USD'=>'Dólares (USD)','EUR'=>'Euros (EUR)'] as $value=>$label)<option value="{{ $value }}" @selected(old('currency',$serviceOrder?->currency ?? 'PEN') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="field span-2"><label class="check"><input type="checkbox" name="includes_tax" value="1" @checked(old('includes_tax',$serviceOrder?->includes_tax ?? true)) {{ ! $canWrite ? 'disabled' : '' }}> Monto incluye IGV</label></div>
                <div class="field"><label for="report_submitted_date">Informe presentado</label><input id="report_submitted_date" type="date" name="report_submitted_date" value="{{ old('report_submitted_date',$serviceOrder?->report_submitted_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="conformity_date">Conformidad recibida</label><input id="conformity_date" type="date" name="conformity_date" value="{{ old('conformity_date',$serviceOrder?->conformity_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="invoice_number">Factura</label><input id="invoice_number" name="invoice_number" maxlength="100" value="{{ old('invoice_number',$serviceOrder?->invoice_number) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="invoice_date">Fecha de factura</label><input id="invoice_date" type="date" name="invoice_date" value="{{ old('invoice_date',$serviceOrder?->invoice_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="invoice_due_date">Vencimiento de factura</label><input id="invoice_due_date" type="date" name="invoice_due_date" value="{{ old('invoice_due_date',$serviceOrder?->invoice_due_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="invoice_amount">Monto facturado</label><input id="invoice_amount" type="number" min="0" step="0.01" name="invoice_amount" value="{{ old('invoice_amount',$serviceOrder?->invoice_amount) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="paid_date">Fecha de pago</label><input id="paid_date" type="date" name="paid_date" value="{{ old('paid_date',$serviceOrder?->paid_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="closed_date">Fecha de cierre</label><input id="closed_date" type="date" name="closed_date" value="{{ old('closed_date',$serviceOrder?->closed_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
            </div>

            <div class="section-title">Documentos y notas</div>
            <div class="grid">
                <div class="field span-2"><label for="drive_url">Carpeta de Google Drive</label><input id="drive_url" type="url" name="drive_url" maxlength="255" value="{{ old('drive_url',$serviceOrder?->drive_url) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field span-2"><label for="notes">Notas</label><textarea id="notes" name="notes" {{ ! $canWrite ? 'disabled' : '' }}>{{ old('notes',$serviceOrder?->notes) }}</textarea></div>
            </div>

            <div class="actions">
                @if($canWrite)<button class="primary" type="submit" data-busy-label="Guardando…">{{ $serviceOrder ? 'Guardar cambios' : 'Crear servicio' }}</button>@endif
                <a class="secondary" href="{{ route('service-orders-ops.show') }}">Volver a servicios</a>
            </div>
        </form>
    </section>
</div>
<script>
(() => {
    const organization = document.getElementById('organization_id');
    const client = document.getElementById('client_id');
    const assignee = document.getElementById('assigned_to');
    if (!organization || organization.disabled) return;
    const filter = () => {
        const id = organization.value;
        client?.querySelectorAll('option[data-organizations]').forEach((option) => {
            option.disabled = !option.dataset.organizations.split(',').includes(id);
        });
        assignee?.querySelectorAll('option[data-organizations]').forEach((option) => {
            option.disabled = !option.dataset.organizations.split(',').includes(id);
        });
    };
    organization.addEventListener('change', filter);
    filter();
})();
</script>
<x-operational-theme />
<x-operational-interactions />
</body>
</html>