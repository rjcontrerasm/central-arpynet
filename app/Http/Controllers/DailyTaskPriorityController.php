<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\GlobalUndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyTaskPriorityController extends Controller
{
    public function update(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $validated = $request->validate([
            'urgency' => [
                'required',
                'in:low,normal,medium,high,critical',
            ],
            'impact' => [
                'required',
                'in:low,normal,medium,high,critical',
            ],
            'scope' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:120'],
            'priority' => [
                'nullable',
                'in:overdue,critical,today,week,planned',
            ],
            'view' => [
                'nullable',
                'in:mine,team,unassigned',
            ],
            'recurring_rule' => [
                'nullable',
                'integer',
            ],
            'work_team' => [
                'nullable',
                'integer',
            ],
            'display' => [
                'nullable',
                'in:detailed,quick',
            ],
        ]);

        abort_unless(
            $task->canBeUpdatedBy($request->user()),
            403,
        );

        foreach (['urgency', 'impact'] as $field) {
            if ($validated[$field] === 'medium') {
                $validated[$field] = 'normal';
            }
        }

        $before = $undo->captureTask($task);

        $task->forceFill([
            'urgency' => $validated['urgency'],
            'impact' => $validated['impact'],
        ])->save();

        $params = $this->filterParams(
            $request,
            $validated,
        );

        $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            'Prioridad actualizada',
            route(
                'daily-ops.show',
                $params,
                false,
            ),
        );

        return redirect()
            ->route(
                'daily-ops.show',
                $params,
            )
            ->with(
                'daily_action_success',
                'Prioridad actualizada.',
            );
    }

    private function filterParams(
        Request $request,
        array $validated,
    ): array {
        $params = [];

        if (! empty($validated['scope'])) {
            $scope = (int) $validated['scope'];

            abort_unless(
                $request->user()
                    ->canAccessTaskScopeOrganization(
                        $scope,
                    ),
                403,
            );

            $params['scope'] = $scope;
        }

        $q = trim(
            (string) ($validated['q'] ?? ''),
        );

        if ($q !== '') {
            $params['q'] = $q;
        }

        if (! empty($validated['priority'])) {
            $params['priority'] =
                $validated['priority'];
        }

        if (! empty($validated['view'])) {
            $params['view'] =
                $validated['view'];
        }

        if (! empty($validated['recurring_rule'])) {
            $params['recurring_rule'] =
                (int) $validated['recurring_rule'];
        }

        if (! empty($validated['display'])) {
            $params['display'] =
                $validated['display'];
        }

        if (! empty($validated['work_team'])) {
            $workTeamId =
                (int) $validated['work_team'];

            abort_unless(
                $request->user()
                    ->canAccessWorkTeam($workTeamId),
                403,
            );

            $params['work_team'] = $workTeamId;
            $params['view'] = 'team';
        }

        return $params;
    }
}
