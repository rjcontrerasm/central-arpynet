<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\GlobalUndoService;
use App\Support\OperationalTaskActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyTaskActionController extends Controller
{
    public function update(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
        OperationalTaskActionService $actions,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'action' => [
                'required',
                'in:complete,start,today,tomorrow,next_week',
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
        ]);

        $actions->preview(
            $request->user(),
            $task,
            $validated['action'],
        );

        $filters = $this->filterParams(
            $request,
            $validated,
        );

        $before = $undo->captureTask(
            $task,
        );

        $result = $actions->execute(
            $request->user(),
            $task,
            $validated['action'],
            confirmed: true,
        );

        $undoAction = $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            $result['label'],
            route(
                'daily-ops.show',
                $filters,
                false,
            ),
        );

        if (
            $request->boolean('_live')
            || $request->expectsJson()
            || $request->header(
                'X-Central-Live-Action',
            ) === '1'
        ) {
            return response()->json([
                'ok' => true,
                'task_id' => $task->id,
                'action' => $validated['action'],
                'label' => $result['label'],
                'undo' => $undoAction
                    ? [
                        'id' => $undoAction->id,
                        'label' => $undoAction->label,
                        'expires_at' => $undoAction->expires_at?->toIso8601String(),
                        'url' => route('global-undo.restore'),
                    ]
                    : null,
            ]);
        }

        return redirect()
            ->route(
                'daily-ops.show',
                $filters,
            )
            ->with(
                'daily_action_success',
                $result['label'].'.',
            );
    }

    private function filterParams(
        Request $request,
        array $validated,
    ): array {
        $params = [];
        $scope = $validated['scope'] ?? null;

        if ($scope) {
            abort_unless(
                $request->user()
                    ->canAccessTaskScopeOrganization(
                        (int) $scope,
                    ),
                403,
            );

            $params['scope'] = $scope;
        }

        $q = trim(
            (string) (
                $validated['q']
                ?? ''
            ),
        );

        if ($q !== '') {
            $params['q'] = $q;
        }

        if (! empty(
            $validated['priority']
                ?? null
        )) {
            $params['priority'] =
                $validated['priority'];
        }

        if (! empty($validated['view'])) {
            $params['view'] = $validated['view'];
        }

        if (! empty($validated['recurring_rule'])) {
            $params['recurring_rule'] =
                (int) $validated['recurring_rule'];
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
