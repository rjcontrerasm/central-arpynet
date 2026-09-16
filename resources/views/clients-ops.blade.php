<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Clientes · Central ARPYNET</title>

    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color-scheme:light dark}
        *{box-sizing:border-box}body{margin:0;background:#0b1020;color:#f8fafc}a{color:inherit;text-decoration:none}button,input,select,textarea{font:inherit}
        .shell{width:min(100%,1200px);margin:0 auto;padding:24px 16px 80px}.topbar,.hero,.section-head,.client-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.topbar{margin-bottom:24px}.brand{font-weight:850;letter-spacing:-.03em}.hero{align-items:end;margin-bottom:18px}h1{margin:0;font-size:clamp(31px,7vw,46px);line-height:1;letter-spacing:-.05em}h2{margin:0;font-size:18px}.subtitle,.meta,.empty,.field-help{color:#94a3b8}.subtitle{margin-top:7px;font-size:13px}
        .success,.errors{margin-bottom:14px;padding:11px 13px;border-radius:12px;font-size:12px}.success{border:1px solid #166534;background:#052e16;color:#bbf7d0;font-weight:750}.errors{border:1px solid #991b1b;background:#450a0a;color:#fecaca}
        .filters{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:9px;margin-bottom:18px}.filters input{min-height:42px;padding:9px 11px;border:1px solid #334155;border-radius:11px;background:#0f172a;color:#f8fafc}.filters button,.primary{min-height:42px;padding:9px 13px;border:0;border-radius:11px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}
        .scopes{display:flex;gap:7px;overflow-x:auto;padding-bottom:14px}.chip{flex:0 0 auto;padding:7px 10px;border:1px solid #334155;border-radius:999px;background:#0f172a;color:#cbd5e1;font-size:12px;font-weight:750}.chip.active{border-color:#60a5fa;background:#172554;color:#dbeafe}
        .layout{display:grid;gap:14px}.panel,.client{border:1px solid #24304b;border-radius:16px;background:#11182b}.panel{padding:15px}.list{display:grid;gap:9px}.client{display:block;padding:13px}.client.selected{border-color:#60a5fa}.client-title{font-weight:820;line-height:1.3}.meta{margin-top:4px;font-size:12px;line-height:1.45}.status{flex:0 0 auto;font-size:10px;font-weight:850}.status.active{color:#86efac}.status.inactive{color:#fca5a5}.section-head{margin-bottom:11px}.empty{padding:22px 12px;text-align:center;font-size:12px}
        .form-grid{display:grid;gap:10px}.field{display:grid;gap:5px}.field label{font-size:11px;font-weight:800;color:#cbd5e1}.field-heading{display:flex;align-items:center;justify-content:space-between;gap:12px}.selection-action{appearance:none;border:0;background:transparent;color:#60a5fa;padding:2px 0;font-size:11px;font-weight:800;line-height:1.2;cursor:pointer}.selection-action:hover{text-decoration:underline}.selection-action:focus-visible{outline:2px solid #60a5fa;outline-offset:3px;border-radius:4px}.field input,.field select,.field textarea{width:100%;min-height:40px;padding:8px 10px;border:1px solid #334155;border-radius:10px;background:#0f172a;color:#f8fafc}.field textarea{min-height:92px;resize:vertical}.field-help{font-size:10px;line-height:1.4}.check{display:flex;align-items:center;gap:8px;min-height:40px}.check input{width:auto;min-height:auto}.org-checks{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:7px;padding:9px;border:1px solid #334155;border-radius:10px;background:#0f172a}.org-check{display:flex;align-items:center;gap:8px;min-height:32px;font-size:12px}.org-check input{width:auto;min-height:auto}.actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.secondary{display:inline-flex;align-items:center;min-height:42px;padding:9px 13px;border:1px solid #334155;border-radius:11px;color:#cbd5e1;font-size:12px;font-weight:800}
        @media(min-width:900px){.layout{grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);align-items:start}.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.span-2{grid-column:1/-1}}
        @media(prefers-color-scheme:light){body{background:#f8fafc;color:#0f172a}.panel,.client{background:#fff;border-color:#e2e8f0}.filters input,.field input,.field select,.field textarea,.chip,.org-checks{background:#fff;color:#0f172a;border-color:#cbd5e1}.chip.active{background:#eff6ff;color:#1d4ed8;border-color:#60a5fa}.field label{color:#334155}.secondary{color:#475569;border-color:#cbd5e1}.selection-action{color:#2563eb}}
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="clients" />
    </div>

    @if(session('client_success'))<div class="success">{{ session('client_success') }}</div>@endif
    @if($errors->any())<div class="errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="hero">
        <div>
            <h1>Clientes</h1>
            <div class="subtitle">Base maestra compartida: una ficha puede atenderse desde varias empresas.</div>
        </div>
    </section>

    <div class="scopes">
        <a class="chip {{ $selectedScope ? '' : 'active' }}" href="{{ route('client-ops.index') }}">Todos los ámbitos</a>
        @foreach($organizations as $organization)
            <a class="chip {{ $selectedScope === (int)$organization->id ? 'active' : '' }}" href="{{ route('client-ops.index',['scope'=>$organization->id]) }}">{{ $organization->name }}</a>
        @endforeach
    </div>

    <form class="filters" method="GET" action="{{ route('client-ops.index') }}">
        @if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif
        <input type="search" name="q" value="{{ $search }}" placeholder="Buscar nombre, RUC, contacto, correo o teléfono">
        <button type="submit">Buscar</button>
    </form>

    <div class="layout">
        <section class="panel">
            <div class="section-head"><h2>Directorio</h2><span class="meta">{{ $clients->count() }} clientes</span></div>
            <div class="list">
                @forelse($clients as $client)
                    <a class="client {{ $selectedClient?->id === $client->id ? 'selected' : '' }}" href="{{ route('client-ops.index',array_filter(['scope'=>$selectedScope,'q'=>$search,'client'=>$client->id])) }}">
                        <div class="client-head">
                            <div>
                                <div class="client-title">{{ $client->name }}</div>
                                <div class="meta">{{ $client->organizations->pluck('name')->join(' · ') ?: 'Sin empresa asociada' }}</div>
                            </div>
                            <span class="status {{ $client->is_active ? 'active' : 'inactive' }}">{{ $client->is_active ? 'ACTIVO' : 'INACTIVO' }}</span>
                        </div>
                        <div class="meta">{{ $client->tax_id ?: 'Sin RUC' }}@if($client->contact_name) · {{ $client->contact_name }}@endif</div>
                        @if($client->email || $client->phone)<div class="meta">{{ $client->email }}{{ $client->email && $client->phone ? ' · ' : '' }}{{ $client->phone }}</div>@endif
                    </a>
                @empty
                    <div class="empty">No hay clientes con estos filtros.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            @php
                $editing = $selectedClient;
                $linkedOrganizationIds = $editing
                    ? $editing->organizations->pluck('id')->map(fn($id)=>(int)$id)->all()
                    : [];
                $canEdit = $editing
                    ? collect($linkedOrganizationIds)->isNotEmpty()
                        && collect($linkedOrganizationIds)->every(fn($id)=>$writableIds->contains((int)$id))
                    : $writableOrganizations->isNotEmpty();
                $defaultOrganizationId = $selectedScope
                    ?? auth()->user()->current_organization_id
                    ?? $writableOrganizations->first()?->id;
                $selectedOrganizationIds = collect(old(
                    'organization_ids',
                    $editing
                        ? $linkedOrganizationIds
                        : array_filter([(int)$defaultOrganizationId]),
                ))->map(fn($id)=>(int)$id)->all();
            @endphp

            <div class="section-head">
                <h2>{{ $editing ? 'Ficha maestra del cliente' : 'Nuevo cliente' }}</h2>
                @if($editing)<a class="secondary" href="{{ route('client-ops.index',array_filter(['scope'=>$selectedScope,'q'=>$search])) }}">Nuevo</a>@endif
            </div>

            @if($canEdit)
                <form method="POST" action="{{ $editing ? route('client-ops.update',$editing) : route('client-ops.store') }}">
                    @csrf
                    @if($selectedScope)<input type="hidden" name="scope" value="{{ $selectedScope }}">@endif
                    <div class="form-grid">
                        <div class="field span-2" data-org-selector>
                            <div class="field-heading">
                                <label>Empresas / ámbitos asociados</label>
                                <button type="button" class="selection-action" data-org-toggle aria-pressed="false">Seleccionar todas</button>
                            </div>
                            <div class="org-checks" data-org-checks>
                                @foreach($writableOrganizations as $organization)
                                    <label class="org-check">
                                        <input type="checkbox" name="organization_ids[]" value="{{ $organization->id }}" @checked(in_array((int)$organization->id,$selectedOrganizationIds,true))>
                                        {{ $organization->name }}
                                    </label>
                                @endforeach
                            </div>
                            <div class="field-help">Una sola ficha de cliente se comparte entre las empresas seleccionadas. Los servicios continúan perteneciendo a una empresa específica.</div>
                        </div>

                        <div class="field"><label for="tax_id">RUC / documento</label><input id="tax_id" name="tax_id" maxlength="20" value="{{ old('tax_id',$editing?->tax_id) }}"></div>
                        <div class="field span-2"><label for="name">Nombre comercial</label><input id="name" name="name" maxlength="255" required value="{{ old('name',$editing?->name) }}"></div>
                        <div class="field span-2"><label for="legal_name">Razón social</label><input id="legal_name" name="legal_name" maxlength="255" value="{{ old('legal_name',$editing?->legal_name) }}"></div>
                        <div class="field"><label for="contact_name">Contacto</label><input id="contact_name" name="contact_name" maxlength="255" value="{{ old('contact_name',$editing?->contact_name) }}"></div>
                        <div class="field"><label for="email">Correo</label><input id="email" type="email" name="email" maxlength="255" value="{{ old('email',$editing?->email) }}"></div>
                        <div class="field"><label for="phone">Teléfono</label><input id="phone" name="phone" maxlength="40" value="{{ old('phone',$editing?->phone) }}"></div>
                        <div class="field"><label for="drive_url">Carpeta / documento Drive</label><input id="drive_url" type="url" name="drive_url" maxlength="255" value="{{ old('drive_url',$editing?->drive_url) }}"></div>
                        <div class="field span-2"><label for="notes">Notas</label><textarea id="notes" name="notes">{{ old('notes',$editing?->notes) }}</textarea></div>
                        <div class="field span-2"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$editing?->is_active ?? true))> Cliente activo</label></div>
                    </div>
                    <div class="actions"><button class="primary" type="submit" data-busy-label="Guardando…">{{ $editing ? 'Guardar cambios' : 'Crear cliente' }}</button></div>
                </form>
            @else
                <div class="empty">Tienes acceso de solo lectura. Para modificar una ficha maestra compartida necesitas permiso de escritura en todas sus empresas asociadas.</div>
            @endif
        </section>
    </div>
</div>
<script>
    (() => {
        document.querySelectorAll('[data-org-selector]').forEach((selector) => {
            const toggle = selector.querySelector('[data-org-toggle]');
            const group = selector.querySelector('[data-org-checks]');

            if (!toggle || !group) return;

            const boxes = () => Array.from(
                group.querySelectorAll('input[type="checkbox"][name="organization_ids[]"]'),
            );

            const syncToggle = () => {
                const currentBoxes = boxes();
                const allSelected = currentBoxes.length > 0
                    && currentBoxes.every((box) => box.checked);

                toggle.textContent = allSelected ? 'Limpiar' : 'Seleccionar todas';
                toggle.setAttribute('aria-pressed', allSelected ? 'true' : 'false');
            };

            toggle.addEventListener('click', () => {
                const currentBoxes = boxes();
                const shouldSelect = ! currentBoxes.every((box) => box.checked);

                currentBoxes.forEach((box) => {
                    box.checked = shouldSelect;
                });

                syncToggle();
            });

            group.addEventListener('change', syncToggle);
            syncToggle();
        });
    })();
</script>
<x-operational-theme />
<x-operational-interactions />
</body>
</html>
