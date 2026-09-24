<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Clientes · Central ARPYNET</title>

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/clients-ops.css') }}?v=2.39.1"
    >
</head>
<body>
<div class="shell">
    <x-operational-page-header
        active="clients"
        title="Clientes"
        subtitle="Base maestra compartida: una ficha puede atenderse desde varias empresas."
    />

    @if(session('client_success'))<div class="success">{{ session('client_success') }}</div>@endif
    @if($errors->any())<div class="errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

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
<script src="{{ asset('central-assets/pages/clients-ops.js') }}?v=2.39.1"></script>
<x-operational-theme />
<x-operational-interactions />
</body>
</html>
