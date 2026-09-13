#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: Path, old: str, new: str, marker: str) -> None:
    text = path.read_text()

    if marker in text:
        print(f"{path}: ya integrado ({marker})")
        return

    if old not in text:
        raise SystemExit(f"ERROR: no se encontro ancla en {path}: {marker}")

    path.write_text(text.replace(old, new, 1))
    print(f"{path}: actualizado ({marker})")


def insert_before(path: Path, anchor: str, content: str, marker: str) -> None:
    text = path.read_text()

    if marker in text:
        print(f"{path}: ya integrado ({marker})")
        return

    if anchor not in text:
        raise SystemExit(f"ERROR: no se encontro ancla en {path}: {marker}")

    path.write_text(text.replace(anchor, content + anchor, 1))
    print(f"{path}: actualizado ({marker})")


routes = Path("routes/web.php")
route_anchor = """Route::middleware('auth')->group(function (): void {
    Route::get(
        '/incidentes',
        [
            \\App\\Http\\Controllers\\Incident360Controller::class,
            'index',
        ],
    )->name('incident-360.index');
});
"""
route_new = route_anchor + """
Route::middleware('auth')->group(function (): void {
    Route::post(
        '/incidentes',
        [
            \\App\\Http\\Controllers\\Incident360ActionController::class,
            'store',
        ],
    )->name('incident-360.store');

    Route::post(
        '/incidentes/{incident}/actualizar',
        [
            \\App\\Http\\Controllers\\Incident360ActionController::class,
            'update',
        ],
    )->name('incident-360.update');
});
"""
replace_once(
    routes,
    route_anchor,
    route_new,
    "incident-360.store",
)

nav = Path("resources/views/components/operational-nav.blade.php")
text = nav.read_text()
if "Administración avanzada →" not in text:
    old = """            <a href=\"{{ url('/admin') }}\">
                Panel administrativo →
            </a>
"""
    new = """            <a href=\"{{ url('/admin') }}\">
                Administración avanzada →
            </a>
"""
    if old not in text:
        raise SystemExit("ERROR: no se encontro enlace de administracion avanzada")
    nav.write_text(text.replace(old, new, 1))
    print(f"{nav}: admin identificado como avanzado")
else:
    print(f"{nav}: admin avanzado ya integrado")

view = Path("resources/views/incident-360.blade.php")
text = view.read_text()

if "<x-operational-theme />" not in text:
    title_anchor = "<title>Incidentes 360 · Central ARPYNET</title>\n"
    if title_anchor not in text:
        raise SystemExit("ERROR: no se encontro title Incident 360")
    text = text.replace(
        title_anchor,
        title_anchor + "    <x-operational-theme />\n",
        1,
    )
    view.write_text(text)
    print(f"{view}: tema operacional agregado")

text = view.read_text()
old_admin = """        <a class=\"admin-link\" href=\"{{ url('/admin/incidentes') }}\">+ Crear / administrar</a>
"""
new_admin = """        @if ($writableOrganizations->isNotEmpty())
            <a class=\"admin-link\" href=\"#nuevo-incidente\">+ Nuevo incidente</a>
        @endif
"""
if "href=\"#nuevo-incidente\"" not in text:
    if old_admin not in text:
        raise SystemExit("ERROR: no se encontro CTA admin de Incident 360")
    view.write_text(text.replace(old_admin, new_admin, 1))
    print(f"{view}: CTA admin eliminado")

style_anchor = """        .external { color:#93c5fd; text-decoration:underline; text-underline-offset:2px; }
"""
style_new = style_anchor + """        .incident-flash { margin:0 0 14px; padding:11px 13px; border:1px solid #93c5fd; border-radius:12px; background:var(--op-card,#fff); color:var(--op-text,#10213a); font-size:12px; }
        .incident-flash.error { border-color:#ef9a9a; }
        .incident-compose,.incident-editor { margin:0 0 16px; border:1px solid var(--op-border,#d2dde9); border-radius:16px; background:var(--op-card,#fff); overflow:hidden; }
        .incident-compose > summary,.incident-editor > summary { cursor:pointer; list-style:none; padding:12px 14px; font-size:12px; font-weight:850; color:var(--op-text,#10213a); }
        .incident-compose > summary::-webkit-details-marker,.incident-editor > summary::-webkit-details-marker { display:none; }
        .incident-form { padding:0 14px 14px; }
        .incident-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .incident-field { display:grid; gap:5px; min-width:0; }
        .incident-field.full { grid-column:1/-1; }
        .incident-field label { color:var(--op-muted,#5e6f85); font-size:10px; font-weight:850; text-transform:uppercase; letter-spacing:.04em; }
        .incident-field input,.incident-field select,.incident-field textarea { width:100%; min-height:40px; padding:8px 10px; border:1px solid var(--op-border-strong,#bdcad9); border-radius:10px; background:var(--op-card,#fff); color:inherit; font:inherit; font-size:12px; }
        .incident-field textarea { min-height:78px; resize:vertical; }
        .incident-actions { display:flex; align-items:center; gap:9px; flex-wrap:wrap; margin-top:12px; }
        .incident-save { min-height:38px; padding:8px 13px; border:0; border-radius:10px; background:var(--op-primary,#245fd7); color:#fff; font:inherit; font-size:12px; font-weight:850; cursor:pointer; }
        .incident-checkbox { display:flex; align-items:center; gap:8px; min-height:40px; font-size:12px; }
        .incident-checkbox input { width:auto; min-height:auto; }
        .admin-hint { color:var(--op-muted,#5e6f85); font-size:10px; }
        @media(max-width:720px){ .incident-form-grid{grid-template-columns:1fr}.incident-field.full{grid-column:auto} }
"""
replace_once(
    view,
    style_anchor,
    style_new,
    ".incident-form-grid",
)

hero_close = """    </section>

    <section class=\"stats\">
"""
create_panel = """    </section>

    @if(session('incident_success'))
        <div class=\"incident-flash\">{{ session('incident_success') }}</div>
    @endif

    @if($errors->any())
        <div class=\"incident-flash error\">{{ $errors->first() }}</div>
    @endif

    @if ($writableOrganizations->isNotEmpty())
        <details
            class=\"incident-compose\"
            id=\"nuevo-incidente\"
            @if(old('_incident_form') === 'create') open @endif
        >
            <summary>＋ Nuevo incidente sin salir de CENTRAL</summary>
            <form
                class=\"incident-form\"
                method=\"POST\"
                action=\"{{ route('incident-360.store') }}\"
                data-incident-create
            >
                @csrf
                <input type=\"hidden\" name=\"_incident_form\" value=\"create\">
                @php($defaultOrganization = (int) old('organization_id', $selectedScope ?: auth()->user()->current_organization_id))
                <div class=\"incident-form-grid\">
                    <div class=\"incident-field full\">
                        <label>Título</label>
                        <input name=\"title\" value=\"{{ old('title') }}\" maxlength=\"255\" required>
                    </div>
                    <div class=\"incident-field\">
                        <label>Ámbito</label>
                        <select name=\"organization_id\" data-incident-organization required>
                            @foreach($writableOrganizations as $organization)
                                <option value=\"{{ $organization->id }}\" @selected($defaultOrganization === (int)$organization->id)>{{ $organization->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class=\"incident-field\">
                        <label>Responsable</label>
                        <select name=\"assigned_to\" data-incident-scoped>
                            <option value=\"\">Sin asignar</option>
                            @foreach($assigneeOptions as $organizationId => $options)
                                @foreach($options as $userId => $name)
                                    <option value=\"{{ $userId }}\" data-org=\"{{ $organizationId }}\" @selected((int)old('assigned_to', auth()->id()) === (int)$userId)>{{ $name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class=\"incident-field\">
                        <label>Severidad</label>
                        <select name=\"severity\" required>
                            @foreach($severityOptions as $value => $label)
                                <option value=\"{{ $value }}\" @selected(old('severity','medium') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class=\"incident-field\">
                        <label>Estado</label>
                        <select name=\"status\" required>
                            @foreach($statusOptions as $value => $label)
                                <option value=\"{{ $value }}\" @selected(old('status','new') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class=\"incident-field\">
                        <label>Categoría</label>
                        <select name=\"category\" required>
                            @foreach($categoryOptions as $value => $label)
                                <option value=\"{{ $value }}\" @selected(old('category','availability') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class=\"incident-field\">
                        <label>Origen</label>
                        <select name=\"source\" required>
                            @foreach($sourceOptions as $value => $label)
                                <option value=\"{{ $value }}\" @selected(old('source','manual') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class=\"incident-field\">
                        <label>Cliente</label>
                        <select name=\"client_id\" data-incident-scoped>
                            <option value=\"\">Sin cliente</option>
                            @foreach($clients as $client)
                                <option value=\"{{ $client->id }}\" data-org=\"{{ $client->organization_id }}\" @selected((int)old('client_id') === (int)$client->id)>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class=\"incident-field\">
                        <label>Servicio / orden</label>
                        <select name=\"service_order_id\" data-incident-scoped>
                            <option value=\"\">Sin servicio</option>
                            @foreach($services as $service)
                                <option value=\"{{ $service->id }}\" data-org=\"{{ $service->organization_id }}\" @selected((int)old('service_order_id') === (int)$service->id)>{{ $service->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class=\"incident-field\">
                        <label>Proyecto</label>
                        <select name=\"project_id\" data-incident-scoped>
                            <option value=\"\">Sin proyecto</option>
                            @foreach($projects as $project)
                                <option value=\"{{ $project->id }}\" data-org=\"{{ $project->organization_id }}\" @selected((int)old('project_id') === (int)$project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class=\"incident-field\">
                        <label>Servicio afectado</label>
                        <input name=\"affected_service\" value=\"{{ old('affected_service') }}\" maxlength=\"255\">
                    </div>
                    <div class=\"incident-field\">
                        <label>Detectado</label>
                        <input type=\"datetime-local\" name=\"detected_at\" value=\"{{ old('detected_at', now()->format('Y-m-d\\TH:i')) }}\">
                    </div>
                    <div class=\"incident-field\">
                        <label>SLA respuesta</label>
                        <input type=\"datetime-local\" name=\"response_due_at\" value=\"{{ old('response_due_at') }}\">
                    </div>
                    <div class=\"incident-field\">
                        <label>SLA solución</label>
                        <input type=\"datetime-local\" name=\"resolution_due_at\" value=\"{{ old('resolution_due_at') }}\">
                    </div>
                    <div class=\"incident-field full\">
                        <label>Próxima acción</label>
                        <input name=\"next_action\" value=\"{{ old('next_action') }}\" maxlength=\"255\">
                    </div>
                    <div class=\"incident-field\">
                        <label>Próximo seguimiento</label>
                        <input type=\"datetime-local\" name=\"next_action_at\" value=\"{{ old('next_action_at') }}\">
                    </div>
                    <div class=\"incident-field\">
                        <label>ID externo</label>
                        <input name=\"external_id\" value=\"{{ old('external_id') }}\" maxlength=\"255\">
                    </div>
                    <div class=\"incident-field full\">
                        <label>Descripción</label>
                        <textarea name=\"description\">{{ old('description') }}</textarea>
                    </div>
                    <div class=\"incident-field full\">
                        <label>Evidencia externa</label>
                        <input type=\"url\" name=\"external_url\" value=\"{{ old('external_url') }}\" maxlength=\"255\" placeholder=\"https://...\">
                    </div>
                    <label class=\"incident-checkbox full\">
                        <input type=\"checkbox\" name=\"is_private\" value=\"1\" @checked(old('is_private'))>
                        Incidente privado
                    </label>
                </div>
                <div class=\"incident-actions\">
                    <button class=\"incident-save\" type=\"submit\">Crear incidente</button>
                    <span class=\"admin-hint\">La operación queda dentro del front de CENTRAL.</span>
                </div>
            </form>
        </details>
    @endif

    <section class=\"stats\">
"""
replace_once(
    view,
    hero_close,
    create_panel,
    "data-incident-create",
)

edit_anchor = """                </div>

                <div class=\"detail-grid\">
"""
edit_panel = """                </div>

                @if(auth()->user()->canWriteToOrganization((int)$incident->organization_id))
                    <details
                        class=\"incident-editor\"
                        @if(old('_incident_form') === 'edit') open @endif
                    >
                        <summary>Editar incidente</summary>
                        <form class=\"incident-form\" method=\"POST\" action=\"{{ route('incident-360.update', $incident) }}\">
                            @csrf
                            <input type=\"hidden\" name=\"_incident_form\" value=\"edit\">
                            <input type=\"hidden\" name=\"organization_id\" value=\"{{ $incident->organization_id }}\">
                            <div class=\"incident-form-grid\">
                                <div class=\"incident-field full\"><label>Título</label><input name=\"title\" value=\"{{ old('title',$incident->title) }}\" maxlength=\"255\" required></div>
                                <div class=\"incident-field\"><label>Severidad</label><select name=\"severity\" required>@foreach($severityOptions as $value=>$label)<option value=\"{{ $value }}\" @selected(old('severity',$incident->severity)===$value)>{{ $label }}</option>@endforeach</select></div>
                                <div class=\"incident-field\"><label>Estado</label><select name=\"status\" required>@foreach($statusOptions as $value=>$label)<option value=\"{{ $value }}\" @selected(old('status',$incident->status)===$value)>{{ $label }}</option>@endforeach</select></div>
                                <div class=\"incident-field\"><label>Categoría</label><select name=\"category\" required>@foreach($categoryOptions as $value=>$label)<option value=\"{{ $value }}\" @selected(old('category',$incident->category)===$value)>{{ $label }}</option>@endforeach</select></div>
                                <div class=\"incident-field\"><label>Origen</label><select name=\"source\" required>@foreach($sourceOptions as $value=>$label)<option value=\"{{ $value }}\" @selected(old('source',$incident->source)===$value)>{{ $label }}</option>@endforeach</select></div>
                                <div class=\"incident-field\"><label>Responsable</label><select name=\"assigned_to\"><option value=\"\">Sin asignar</option>@foreach($assigneeOptions->get((int)$incident->organization_id,[]) as $userId=>$name)<option value=\"{{ $userId }}\" @selected((int)old('assigned_to',$incident->assigned_to)===(int)$userId)>{{ $name }}</option>@endforeach</select></div>
                                <div class=\"incident-field\"><label>Cliente</label><select name=\"client_id\"><option value=\"\">Sin cliente</option>@foreach($clients->where('organization_id',$incident->organization_id) as $client)<option value=\"{{ $client->id }}\" @selected((int)old('client_id',$incident->client_id)===(int)$client->id)>{{ $client->name }}</option>@endforeach</select></div>
                                <div class=\"incident-field\"><label>Servicio / orden</label><select name=\"service_order_id\"><option value=\"\">Sin servicio</option>@foreach($services->where('organization_id',$incident->organization_id) as $service)<option value=\"{{ $service->id }}\" @selected((int)old('service_order_id',$incident->service_order_id)===(int)$service->id)>{{ $service->title }}</option>@endforeach</select></div>
                                <div class=\"incident-field\"><label>Proyecto</label><select name=\"project_id\"><option value=\"\">Sin proyecto</option>@foreach($projects->where('organization_id',$incident->organization_id) as $project)<option value=\"{{ $project->id }}\" @selected((int)old('project_id',$incident->project_id)===(int)$project->id)>{{ $project->name }}</option>@endforeach</select></div>
                                <div class=\"incident-field\"><label>Servicio afectado</label><input name=\"affected_service\" value=\"{{ old('affected_service',$incident->affected_service) }}\" maxlength=\"255\"></div>
                                <div class=\"incident-field\"><label>Detectado</label><input type=\"datetime-local\" name=\"detected_at\" value=\"{{ old('detected_at',$incident->detected_at?->format('Y-m-d\\TH:i')) }}\"></div>
                                <div class=\"incident-field\"><label>SLA respuesta</label><input type=\"datetime-local\" name=\"response_due_at\" value=\"{{ old('response_due_at',$incident->response_due_at?->format('Y-m-d\\TH:i')) }}\"></div>
                                <div class=\"incident-field\"><label>SLA solución</label><input type=\"datetime-local\" name=\"resolution_due_at\" value=\"{{ old('resolution_due_at',$incident->resolution_due_at?->format('Y-m-d\\TH:i')) }}\"></div>
                                <div class=\"incident-field full\"><label>Próxima acción</label><input name=\"next_action\" value=\"{{ old('next_action',$incident->next_action) }}\" maxlength=\"255\"></div>
                                <div class=\"incident-field\"><label>Próximo seguimiento</label><input type=\"datetime-local\" name=\"next_action_at\" value=\"{{ old('next_action_at',$incident->next_action_at?->format('Y-m-d\\TH:i')) }}\"></div>
                                <div class=\"incident-field\"><label>ID externo</label><input name=\"external_id\" value=\"{{ old('external_id',$incident->external_id) }}\" maxlength=\"255\"></div>
                                <div class=\"incident-field full\"><label>Descripción</label><textarea name=\"description\">{{ old('description',$incident->description) }}</textarea></div>
                                <div class=\"incident-field full\"><label>Causa raíz</label><textarea name=\"root_cause\">{{ old('root_cause',$incident->root_cause) }}</textarea></div>
                                <div class=\"incident-field full\"><label>Resumen de solución</label><textarea name=\"resolution_summary\">{{ old('resolution_summary',$incident->resolution_summary) }}</textarea></div>
                                <div class=\"incident-field full\"><label>Evidencia externa</label><input type=\"url\" name=\"external_url\" value=\"{{ old('external_url',$incident->external_url) }}\" maxlength=\"255\"></div>
                                <div class=\"incident-field full\"><label>Notas</label><textarea name=\"notes\">{{ old('notes',$incident->notes) }}</textarea></div>
                                <label class=\"incident-checkbox full\"><input type=\"checkbox\" name=\"is_private\" value=\"1\" @checked(old('is_private',$incident->is_private))> Incidente privado</label>
                            </div>
                            <div class=\"incident-actions\"><button class=\"incident-save\" type=\"submit\">Guardar cambios</button></div>
                        </form>
                    </details>
                @endif

                <div class=\"detail-grid\">
"""
replace_once(
    view,
    edit_anchor,
    edit_panel,
    "route('incident-360.update'",
)

script_anchor = """</div>
</body>
</html>
"""
script = """</div>
<script>
document.querySelectorAll('[data-incident-create]').forEach((form) => {
    const organization = form.querySelector('[data-incident-organization]');
    if (!organization) return;

    const sync = () => {
        const organizationId = organization.value;
        form.querySelectorAll('[data-incident-scoped]').forEach((select) => {
            Array.from(select.options).forEach((option) => {
                const optionOrganization = option.dataset.org;
                const visible = !optionOrganization || optionOrganization === organizationId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible && option.selected) select.value = '';
            });
        });
    };

    organization.addEventListener('change', sync);
    sync();
});
</script>
</body>
</html>
"""
replace_once(
    view,
    script_anchor,
    script,
    "data-incident-organization]",
)

overview = Path("resources/views/operational-360.blade.php")
text = overview.read_text()

old = """<div class=\"section-head\"><h2>Clientes</h2><a class=\"section-link\" href=\"{{ url('/admin/clientes') }}\">Administrar →</a></div>
"""
new = """<div class=\"section-head\"><h2>Clientes</h2><span class=\"meta\">Vista consolidada</span></div>
"""
if "<h2>Clientes</h2><span class=\"meta\">Vista consolidada</span>" not in text:
    if old not in text:
        raise SystemExit("ERROR: no se encontro enlace admin de clientes en Vista 360")
    text = text.replace(old, new, 1)

old = """<div class=\"section-head\"><h2>Incidentes</h2><a class=\"section-link\" href=\"{{ url('/admin/incidentes') }}\">Administrar →</a></div>
"""
new = """<div class=\"section-head\"><h2>Incidentes</h2><a class=\"section-link\" href=\"{{ route('incident-360.index',array_filter(['scope'=>$selectedScope])) }}\">Incident 360 →</a></div>
"""
if "Incident 360 →" not in text:
    if old not in text:
        raise SystemExit("ERROR: no se encontro enlace admin de incidentes en Vista 360")
    text = text.replace(old, new, 1)

old = """@if($incident->client)<a href=\"{{ url('/admin/clientes') }}\">Cliente: {{ $incident->client->name }}</a>@endif
"""
new = """@if($incident->client)<span class=\"meta\">Cliente: {{ $incident->client->name }}</span>@endif
"""
if "<span class=\"meta\">Cliente: {{ $incident->client->name }}</span>" not in text:
    if old not in text:
        raise SystemExit("ERROR: no se encontro enlace admin de cliente relacionado")
    text = text.replace(old, new, 1)

overview.write_text(text)
print(f"{overview}: saltos operativos a admin eliminados")

print("2.26 front consistency patch aplicado correctamente")
