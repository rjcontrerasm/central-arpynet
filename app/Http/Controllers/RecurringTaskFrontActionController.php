<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RecurringTaskRule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecurringTaskFrontActionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        $this->validateRelations(
            $organizationId,
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
            isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
        );

        $attributes = $this->attributes($request, $validated);
        $attributes['created_by'] = $request->user()->id;
        $attributes['assigned_to'] ??= $request->user()->id;

        $rule = RecurringTaskRule::query()->create($attributes);

        return redirect()
            ->route('recurring-task-front.edit', $rule)
            ->with('recurring_task_success', 'Tarea recurrente creada.');
    }

    public function update(
        Request $request,
        RecurringTaskRule $recurringTaskRule,
    ): RedirectResponse {
        abort_unless(
            $request->user()->canWriteToOrganization(
                (int) $recurringTaskRule->organization_id,
            ),
            403,
        );

        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        if ($organizationId !== (int) $recurringTaskRule->organization_id) {
            throw ValidationException::withMessages([
                'organization_id' =>
                    'El ámbito de una recurrencia existente no se cambia desde esta ficha.',
            ]);
        }

        $this->validateRelations(
            $organizationId,
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
            isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
        );

        $recurringTaskRule->fill(
            $this->attributes($request, $validated),
        )->save();

        return redirect()
            ->route('recurring-task-front.edit', $recurringTaskRule)
            ->with('recurring_task_success', 'Tarea recurrente actualizada.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'frequency' => [
                'required',
                Rule::in(array_keys(RecurringTaskRule::frequencyOptions())),
            ],
            'anchor_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:anchor_date'],
            'create_days_before' => ['required', 'integer', 'min:0', 'max:90'],
            'due_time' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'urgency' => [
                'required',
                Rule::in(array_keys(Task::urgencyOptions())),
            ],
            'impact' => [
                'required',
                Rule::in(array_keys(Task::impactOptions())),
            ],
            'is_private' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function validateRelations(
        int $organizationId,
        ?int $projectId,
        ?int $assignedTo,
    ): void {
        if ($projectId !== null) {
            $projectIsValid = Project::query()
                ->whereKey($projectId)
                ->where('organization_id', $organizationId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->exists();

            if (! $projectIsValid) {
                throw ValidationException::withMessages([
                    'project_id' =>
                        'El proyecto debe estar abierto y pertenecer al mismo ámbito.',
                ]);
            }
        }

        if ($assignedTo === null) {
            return;
        }

        $assigneeIsValid = User::query()
            ->whereKey($assignedTo)
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn ($query) => $query
                    ->where('organizations.id', $organizationId)
                    ->where('organizations.is_active', true)
                    ->where('organization_user.is_active', true)
                    ->whereIn(
                        'organization_user.role',
                        ['owner', 'admin', 'member'],
                    ),
            )
            ->exists();

        if (! $assigneeIsValid) {
            throw ValidationException::withMessages([
                'assigned_to' =>
                    'El responsable debe tener acceso operativo al mismo ámbito.',
            ]);
        }
    }

    private function attributes(Request $request, array $validated): array
    {
        foreach (['title', 'description', 'next_action'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = trim((string) $validated[$field]);
            $validated[$field] = $value !== '' ? $value : null;
        }

        $validated['title'] = trim((string) $validated['title']);
        $validated['is_private'] = $request->boolean('is_private');
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
