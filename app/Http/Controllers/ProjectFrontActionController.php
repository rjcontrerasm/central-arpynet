<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\GlobalUndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectFrontActionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        $project = DB::transaction(function () use (
            $request,
            $validated,
        ): Project {
            $project = Project::query()->create(
                $this->attributes($request, $validated),
            );

            $project->syncParticipants(
                $validated['participants'] ?? [],
            );

            return $project;
        });

        return redirect()
            ->route('project-front.edit', $project)
            ->with('project_front_success', 'Proyecto creado.');
    }

    public function update(
        Request $request,
        Project $project,
        GlobalUndoService $undo,
    ): RedirectResponse {
        abort_unless(
            $request->user()->canWriteToOrganization(
                (int) $project->organization_id,
            ),
            403,
        );

        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        if ($organizationId !== (int) $project->organization_id) {
            throw ValidationException::withMessages([
                'organization_id' =>
                    'El ámbito de un proyecto existente no se cambia desde esta ficha.',
            ]);
        }

        $before = $undo->captureProject($project);

        DB::transaction(function () use (
            $request,
            $project,
            $validated,
        ): void {
            $project->fill(
                $this->attributes($request, $validated),
            )->save();

            $project->syncParticipants(
                $validated['participants'] ?? [],
            );
        });

        $undo->rememberProjectMutation(
            $request->user(),
            $project,
            $before,
            'Proyecto actualizado',
            route('project-front.edit', $project, false),
        );

        return redirect()
            ->route('project-front.edit', $project)
            ->with('project_front_success', 'Proyecto actualizado.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => [
                'required',
                Rule::in(array_keys(Project::typeOptions())),
            ],
            'horizon' => [
                'required',
                Rule::in(array_keys(Project::horizonOptions())),
            ],
            'status' => [
                'required',
                Rule::in(array_keys(Project::statusOptions())),
            ],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'in:PEN,USD,EUR'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'blockers' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_private' => ['nullable', 'boolean'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['integer'],
        ]);
    }

    private function attributes(
        Request $request,
        array $validated,
    ): array {
        foreach ([
            'description',
            'next_action',
            'blockers',
            'notes',
        ] as $field) {
            $value = trim((string) ($validated[$field] ?? ''));
            $validated[$field] = $value !== '' ? $value : null;
        }

        $validated['name'] = trim((string) $validated['name']);
        $validated['is_private'] = $request->boolean('is_private');

        unset($validated['participants']);

        return $validated;
    }
}
