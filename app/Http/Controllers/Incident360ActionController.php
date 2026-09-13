<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Incident;
use App\Models\Project;
use App\Models\ServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Incident360ActionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        $this->validateRelations($validated, $organizationId);

        $incident = Incident::query()->create(
            $this->attributes($request, $validated),
        );

        return redirect()
            ->route('incident-360.index', [
                'scope' => $organizationId,
                'focus' => 'all',
                'incident' => $incident->id,
            ])
            ->with('incident_success', 'Incidente creado.');
    }

    public function update(
        Request $request,
        Incident $incident,
    ): RedirectResponse {
        abort_unless(
            $request->user()->canWriteToOrganization(
                (int) $incident->organization_id,
            ),
            403,
        );

        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        if ($organizationId !== (int) $incident->organization_id) {
            throw ValidationException::withMessages([
                'organization_id' =>
                    'El ámbito de un incidente existente no se cambia desde Incident 360.',
            ]);
        }

        $this->validateRelations($validated, $organizationId);

        $incident->forceFill(
            $this->attributes($request, $validated),
        )->save();

        return redirect()
            ->route('incident-360.index', [
                'scope' => $organizationId,
                'focus' => 'all',
                'incident' => $incident->id,
            ])
            ->with('incident_success', 'Incidente actualizado.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'category' => [
                'required',
                'string',
                Rule::in(array_keys(Incident::categoryOptions())),
            ],
            'severity' => [
                'required',
                'string',
                Rule::in(array_keys(Incident::severityOptions())),
            ],
            'status' => [
                'required',
                'string',
                Rule::in(array_keys(Incident::statusOptions())),
            ],
            'source' => [
                'required',
                'string',
                Rule::in(array_keys(Incident::sourceOptions())),
            ],
            'assigned_to' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'service_order_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'affected_service' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'detected_at' => ['nullable', 'date'],
            'response_due_at' => ['nullable', 'date'],
            'resolution_due_at' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_at' => ['nullable', 'date'],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'resolution_summary' => ['nullable', 'string', 'max:10000'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'external_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_private' => ['nullable', 'boolean'],
        ]);
    }

    private function attributes(
        Request $request,
        array $validated,
    ): array {
        $nullableStrings = [
            'affected_service',
            'description',
            'next_action',
            'root_cause',
            'resolution_summary',
            'external_id',
            'external_url',
            'notes',
        ];

        foreach ($nullableStrings as $field) {
            $value = trim((string) ($validated[$field] ?? ''));
            $validated[$field] = $value !== '' ? $value : null;
        }

        foreach ([
            'assigned_to',
            'client_id',
            'service_order_id',
            'project_id',
        ] as $field) {
            $validated[$field] = isset($validated[$field])
                ? (int) $validated[$field]
                : null;
        }

        $validated['is_private'] = $request->boolean('is_private');
        $validated['last_activity_at'] = now();

        return $validated;
    }

    private function validateRelations(
        array $validated,
        int $organizationId,
    ): void {
        $relations = [
            'client_id' => [Client::class, 'El cliente no pertenece al ámbito seleccionado.'],
            'service_order_id' => [ServiceOrder::class, 'El servicio no pertenece al ámbito seleccionado.'],
            'project_id' => [Project::class, 'El proyecto no pertenece al ámbito seleccionado.'],
        ];

        foreach ($relations as $field => [$model, $message]) {
            $id = $validated[$field] ?? null;

            if (! $id) {
                continue;
            }

            $exists = $model::query()
                ->whereKey((int) $id)
                ->where('organization_id', $organizationId)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    $field => $message,
                ]);
            }
        }
    }
}
