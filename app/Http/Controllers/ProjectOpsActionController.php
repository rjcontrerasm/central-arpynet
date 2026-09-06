<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectOpsActionController extends Controller
{
    public function update(
        Request $request,
        Project $project,
    ): RedirectResponse {
        $validated = $request->validate([
            'next_action' => ['nullable', 'string', 'max:255'],
            'blockers' => ['nullable', 'string', 'max:5000'],
            'status' => [
                'required',
                Rule::in(array_keys(Project::statusOptions())),
            ],
            'scope' => ['nullable', 'integer'],
            'focus' => [
                'nullable',
                'in:all,attention,stagnant,no_next_action,active',
            ],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $this->authorizeProject(
            $request,
            $project,
            isset($validated['scope'])
                ? (int) $validated['scope']
                : null,
        );

        $project->fill([
            'next_action' => $this->nullableText(
                $validated['next_action'] ?? null,
            ),
            'blockers' => $this->nullableText(
                $validated['blockers'] ?? null,
            ),
            'status' => $validated['status'],
        ])->save();

        return redirect()
            ->route(
                'project-ops.show',
                $this->redirectParams($validated),
            )
            ->with(
                'project_action_success',
                'Proyecto actualizado.',
            );
    }

    public function storeTask(
        Request $request,
        Project $project,
    ): RedirectResponse {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'urgency' => [
                'required',
                Rule::in(['low', 'normal', 'high', 'critical']),
            ],
            'scope' => ['nullable', 'integer'],
            'focus' => [
                'nullable',
                'in:all,attention,stagnant,no_next_action,active',
            ],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $this->authorizeProject(
            $request,
            $project,
            isset($validated['scope'])
                ? (int) $validated['scope']
                : null,
        );

        abort_if(
            in_array(
                $project->status,
                ['completed', 'cancelled'],
                true,
            ),
            422,
        );

        $dueAt = null;

        if (! empty($validated['due_date'])) {
            $dueAt = CarbonImmutable::parse(
                $validated['due_date'],
                config('app.timezone', 'America/Lima'),
            )->setTime(17, 0);
        }

        Task::query()->create([
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'title' => trim($validated['title']),
            'status' => 'pending',
            'urgency' => $validated['urgency'],
            'impact' => 'normal',
            'due_at' => $dueAt,
            'source' => 'project_ops',
            'assigned_to' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route(
                'project-ops.show',
                $this->redirectParams($validated),
            )
            ->with(
                'project_action_success',
                'Tarea creada y vinculada al proyecto.',
            );
    }

    private function authorizeProject(
        Request $request,
        Project $project,
        ?int $scope,
    ): void {
        $userId = $request->user()->id;

        $canUseProject = DB::table('organization_user')
            ->where('user_id', $userId)
            ->where(
                'organization_id',
                $project->organization_id,
            )
            ->where('is_active', true)
            ->exists();

        abort_unless($canUseProject, 403);

        if ($scope) {
            $canUseScope = DB::table('organization_user')
                ->where('user_id', $userId)
                ->where('organization_id', $scope)
                ->where('is_active', true)
                ->exists();

            abort_unless($canUseScope, 403);
        }
    }

    private function redirectParams(array $validated): array
    {
        $params = [];

        if (! empty($validated['scope'])) {
            $params['scope'] = (int) $validated['scope'];
        }

        if (! empty($validated['focus'])) {
            $params['focus'] = $validated['focus'];
        }

        $q = trim((string) ($validated['q'] ?? ''));

        if ($q !== '') {
            $params['q'] = $q;
        }

        return $params;
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
