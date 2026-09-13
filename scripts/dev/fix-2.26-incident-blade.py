#!/usr/bin/env python3
from pathlib import Path

path = Path("resources/views/incident-360.blade.php")
text = path.read_text()

replacements = {
    "@php($defaultOrganization = (int) old('organization_id', $selectedScope ?: auth()->user()->current_organization_id))": "@php\n                    $defaultOrganization = (int) old('organization_id', $selectedScope ?: auth()->user()->current_organization_id);\n                @endphp",
    "@selected($defaultOrganization === (int)$organization->id)": "{{ $defaultOrganization == $organization->id ? 'selected' : '' }}",
    "@selected((int)old('assigned_to', auth()->id()) === (int)$userId)": "{{ old('assigned_to', auth()->id()) == $userId ? 'selected' : '' }}",
    "@selected(old('severity','medium') === $value)": "{{ old('severity','medium') === $value ? 'selected' : '' }}",
    "@selected(old('status','new') === $value)": "{{ old('status','new') === $value ? 'selected' : '' }}",
    "@selected(old('category','availability') === $value)": "{{ old('category','availability') === $value ? 'selected' : '' }}",
    "@selected(old('source','manual') === $value)": "{{ old('source','manual') === $value ? 'selected' : '' }}",
    "@selected((int)old('client_id') === (int)$client->id)": "{{ old('client_id') == $client->id ? 'selected' : '' }}",
    "@selected((int)old('service_order_id') === (int)$service->id)": "{{ old('service_order_id') == $service->id ? 'selected' : '' }}",
    "@selected((int)old('project_id') === (int)$project->id)": "{{ old('project_id') == $project->id ? 'selected' : '' }}",
    "@checked(old('is_private'))": "{{ old('is_private') ? 'checked' : '' }}",
    "@selected(old('severity',$incident->severity)===$value)": "{{ old('severity',$incident->severity) === $value ? 'selected' : '' }}",
    "@selected(old('status',$incident->status)===$value)": "{{ old('status',$incident->status) === $value ? 'selected' : '' }}",
    "@selected(old('category',$incident->category)===$value)": "{{ old('category',$incident->category) === $value ? 'selected' : '' }}",
    "@selected(old('source',$incident->source)===$value)": "{{ old('source',$incident->source) === $value ? 'selected' : '' }}",
    "@selected((int)old('assigned_to',$incident->assigned_to)===(int)$userId)": "{{ old('assigned_to',$incident->assigned_to) == $userId ? 'selected' : '' }}",
    "@selected((int)old('client_id',$incident->client_id)===(int)$client->id)": "{{ old('client_id',$incident->client_id) == $client->id ? 'selected' : '' }}",
    "@selected((int)old('service_order_id',$incident->service_order_id)===(int)$service->id)": "{{ old('service_order_id',$incident->service_order_id) == $service->id ? 'selected' : '' }}",
    "@selected((int)old('project_id',$incident->project_id)===(int)$project->id)": "{{ old('project_id',$incident->project_id) == $project->id ? 'selected' : '' }}",
    "@checked(old('is_private',$incident->is_private))": "{{ old('is_private',$incident->is_private) ? 'checked' : '' }}",
}

changed = 0
for old, new in replacements.items():
    if old in text:
        text = text.replace(old, new)
        changed += 1

path.write_text(text)
print(f"Incident 360 Blade saneado: {changed} reemplazos")

# Guard rails: the generated front forms should not keep Blade attribute
# directives that previously produced an invalid compiled PHP view.
remaining = [token for token in ("@selected(", "@checked(") if token in text]
if remaining:
    raise SystemExit(
        "ERROR: quedan directivas de atributo por sanear: " + ", ".join(remaining)
    )

print("2.26 Blade fix aplicado correctamente")
