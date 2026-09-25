<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $project ? 'Editar proyecto' : 'Nuevo proyecto' }} · Central ARPYNET</title>

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/project-front-form.css') }}?v=2.39.2"
    >
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
