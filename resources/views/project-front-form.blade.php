<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $project ? 'Editar proyecto' : 'Nuevo proyecto' }} · Central ARPYNET</title>

    <style>
        :root {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color-scheme: light dark;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #0b1020; color: #f8fafc; }
        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }
        .shell { width: min(100%, 980px); margin: 0 auto; padding: 24px 16px 80px; }
        .topbar, .hero, .actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .topbar { margin-bottom: 24px; }
        .brand { font-weight: 850; letter-spacing: -.03em; }
        .hero { align-items: end; margin-bottom: 18px; }
        h1 { margin: 0; font-size: clamp(31px, 7vw, 46px); line-height: 1; letter-spacing: -.05em; }
        .subtitle, .help { color: #94a3b8; }
        .subtitle { margin-top: 7px; font-size: 13px; }
        .panel { padding: 16px; border: 1px solid #24304b; border-radius: 17px; background: #11182b; }
        .grid { display: grid; gap: 11px; }
        .field { display: grid; gap: 5px; }
        .field label { color: #cbd5e1; font-size: 11px; font-weight: 820; }
        .field input, .field select, .field textarea {
            width: 100%; min-height: 42px; padding: 9px 10px; border: 1px solid #334155;
            border-radius: 10px; background: #0f172a; color: #f8fafc;
        }
        .field textarea { min-height: 96px; resize: vertical; }
        .help { font-size: 10px; line-height: 1.4; }
        .check { display: flex; align-items: center; gap: 8px; min-height: 42px; }
        .check input { width: auto; min-height: auto; }
        .actions { justify-content: flex-start; flex-wrap: wrap; margin-top: 15px; }
        .primary, .secondary {
            display: inline-flex; align-items: center; justify-content: center; min-height: 42px;
            padding: 9px 13px; border-radius: 10px; font-size: 12px; font-weight: 820; cursor: pointer;
        }
        .primary { border: 0; background: #2563eb; color: #fff; }
        .secondary { border: 1px solid #334155; background: #0f172a; color: #cbd5e1; }
        .success, .errors, .readonly {
            margin-bottom: 14px; padding: 11px 13px; border-radius: 12px; font-size: 12px;
        }
        .success { border: 1px solid #166534; background: #052e16; color: #bbf7d0; }
        .errors { border: 1px solid #991b1b; background: #450a0a; color: #fecaca; }
        .readonly { border: 1px solid #475569; background: #0f172a; color: #cbd5e1; }
        .section-title { margin: 18px 0 10px; color: #93c5fd; font-size: 12px; font-weight: 850; text-transform: uppercase; letter-spacing: .05em; }
        @media (min-width: 760px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .span-2 { grid-column: 1 / -1; }
        }
        @media (prefers-color-scheme: light) {
            body { background: #f8fafc; color: #0f172a; }
            .panel { background: #fff; border-color: #e2e8f0; }
            .field input, .field select, .field textarea, .secondary { background: #fff; color: #0f172a; border-color: #cbd5e1; }
            .field label { color: #334155; }
            .readonly { background: #fff; border-color: #cbd5e1; color: #475569; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="projects" />
    </div>

    @if (session('project_front_success'))
        <div class="success">{{ session('project_front_success') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="hero">
        <div>
            <h1>{{ $project ? 'Editar proyecto' : 'Nuevo proyecto' }}</h1>
            <div class="subtitle">Planificación completa desde CENTRAL Front.</div>
        </div>
    </section>

    @if (! $canWrite)
        <div class="readonly">Tienes acceso de solo lectura a este proyecto.</div>
    @endif

    <section class="panel">
        @php
            $organizationId = (int) old('organization_id', $project?->organization_id ?? $defaultOrganizationId);
            $selectedParticipants = collect(old(
                'participants',
                $project?->participants?->pluck('id')->all() ?? [],
            ))->map(fn ($id) => (int) $id)->all();
        @endphp

        <form
            method="POST"
            action="{{ $project ? route('project-front.update', $project) : route('project-front.store') }}"
        >
            @csrf

            <div class="section-title">Proyecto</div>
            <div class="grid">
                <div class="field">
                    <label for="organization_id">Empresa / ámbito</label>
                    <select id="organization_id" name="organization_id" {{ $project || ! $canWrite ? 'disabled' : '' }} required>
                        @foreach ($writableOrganizations as $organization)
                            <option value="{{ $organization->id }}" {{ $organizationId === (int) $organization->id ? 'selected' : '' }}>
                                {{ $organization->name }}
                            </option>
                        @endforeach
                    </select>
                    @if ($project)
                        <input type="hidden" name="organization_id" value="{{ $project->organization_id }}">
                        <div class="help">El ámbito de un proyecto existente no se cambia desde esta ficha.</div>
                    @endif
                </div>

                <div class="field">
                    <label for="status">Estado</label>
                    <select id="status" name="status" {{ ! $canWrite ? 'disabled' : '' }} required>
                        @foreach (\App\Models\Project::statusOptions() as $value => $label)
                            <option value="{{ $value }}" {{ old('status', $project?->status ?? 'planned') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field span-2">
                    <label for="name">Nombre</label>
                    <input id="name" name="name" maxlength="255" required value="{{ old('name', $project?->name) }}" {{ ! $canWrite ? 'disabled' : '' }}>
                </div>

                <div class="field">
                    <label for="type">Tipo</label>
                    <select id="type" name="type" {{ ! $canWrite ? 'disabled' : '' }} required>
                        @foreach (\App\Models\Project::typeOptions() as $value => $label)
                            <option value="{{ $value }}" {{ old('type', $project?->type ?? 'project') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="horizon">Horizonte</label>
                    <select id="horizon" name="horizon" {{ ! $canWrite ? 'disabled' : '' }} required>
                        @foreach (\App\Models\Project::horizonOptions() as $value => $label)
                            <option value="{{ $value }}" {{ old('horizon', $project?->horizon ?? 'short') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field span-2">
                    <label for="description">Descripción</label>
                    <textarea id="description" name="description" {{ ! $canWrite ? 'disabled' : '' }}>{{ old('description', $project?->description) }}</textarea>
                </div>
            </div>

            <div class="section-title">Planificación</div>
            <div class="grid">
                <div class="field">
                    <label for="start_date">Fecha de inicio</label>
                    <input id="start_date" type="date" name="start_date" value="{{ old('start_date', $project?->start_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}>
                </div>
                <div class="field">
                    <label for="target_date">Fecha objetivo</label>
                    <input id="target_date" type="date" name="target_date" value="{{ old('target_date', $project?->target_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}>
                </div>
                <div class="field">
                    <label for="budget">Presupuesto</label>
                    <input id="budget" type="number" min="0" step="0.01" name="budget" value="{{ old('budget', $project?->budget) }}" {{ ! $canWrite ? 'disabled' : '' }}>
                </div>
                <div class="field">
                    <label for="currency">Moneda</label>
                    <select id="currency" name="currency" {{ ! $canWrite ? 'disabled' : '' }} required>
                        @foreach (['PEN' => 'Soles (PEN)', 'USD' => 'Dólares (USD)', 'EUR' => 'Euros (EUR)'] as $value => $label)
                            <option value="{{ $value }}" {{ old('currency', $project?->currency ?? 'PEN') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field span-2">
                    <label for="next_action">Próxima acción</label>
                    <input id="next_action" name="next_action" maxlength="255" value="{{ old('next_action', $project?->next_action) }}" {{ ! $canWrite ? 'disabled' : '' }}>
                </div>
                <div class="field span-2">
                    <label for="blockers">Bloqueadores</label>
                    <textarea id="blockers" name="blockers" {{ ! $canWrite ? 'disabled' : '' }}>{{ old('blockers', $project?->blockers) }}</textarea>
                </div>
                <div class="field span-2">
                    <label for="notes">Notas</label>
                    <textarea id="notes" name="notes" {{ ! $canWrite ? 'disabled' : '' }}>{{ old('notes', $project?->notes) }}</textarea>
                </div>
                <div class="field span-2">
                    <label class="check">
                        <input type="checkbox" name="is_private" value="1" {{ old('is_private', $project?->is_private ?? false) ? 'checked' : '' }} {{ ! $canWrite ? 'disabled' : '' }}>
                        Proyecto privado
                    </label>
                </div>
            </div>

            @if ($project)
                <div class="section-title">Participantes</div>
                <div class="field">
                    <label for="participants">Equipo del proyecto</label>
                    <select id="participants" name="participants[]" multiple size="{{ min(max(count($participantOptions), 3), 8) }}" {{ ! $canWrite ? 'disabled' : '' }}>
                        @foreach ($participantOptions as $userId => $name)
                            <option value="{{ $userId }}" {{ in_array((int) $userId, $selectedParticipants, true) ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    <div class="help">Solo usuarios activos con rol operativo en la empresa del proyecto.</div>
                </div>
            @endif

            <div class="actions">
                @if ($canWrite)
                    <button class="primary" type="submit" data-busy-label="Guardando…">
                        {{ $project ? 'Guardar cambios' : 'Crear proyecto' }}
                    </button>
                @endif
                <a class="secondary" href="{{ route('project-ops.show') }}">Volver a proyectos</a>
            </div>
        </form>
    </section>
</div>

<x-operational-theme />
<x-operational-interactions />
</body>
</html>
