<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\DailyTaskPriority;
use App\Support\GlobalUndoService;
use App\Support\OperationalTaskActionService;
use Carbon\CarbonImmutable;
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
            $now = CarbonImmutable::now(
                config(
                    'app.timezone',
                    'America/Lima',
                ),
            );
            $todayStart = $now->startOfDay();
            $todayEnd = $now->endOfDay();
            $weekEnd = $now->addDays(7)->endOfDay();
            $band = DailyTaskPriority::band(
                $task,
                $now,
            );
            $score = DailyTaskPriority::score(
                $task,
                $now,
            );
            $isOverdue = $task->due_at
                && $task->due_at->isBefore(
                    $todayStart,
                );

            $destination = null;

            if ($isOverdue) {
                $destination = 'vencidas';
            } elseif ($band === 'critical') {
                $destination = 'prioridad-critica';
            } elseif (
                $task->due_at
                && $task->due_at->isSameDay($now)
            ) {
                $destination = 'hoy';
            } elseif (
                $task->due_at
                && $task->due_at->isAfter($todayEnd)
                && $task->due_at
                    ->lessThanOrEqualTo($weekEnd)
            ) {
                $destination = 'esta-semana';
            } elseif (is_null($task->due_at)) {
                $destination = 'planificados';
            }

            return response()->json([
                'ok' => true,
                'task_id' => $task->id,
                'action' => $validated['action'],
                'label' => $result['label'],
                'presentation' => [
                    'due_date' => $task->due_at
                        ?->format('d/m/Y'),
                    'due_today' => (bool) (
                        $task->due_at
                        && $task->due_at->isSameDay($now)
                    ),
                    'overdue' => (bool) $isOverdue,
                    'priority_band' => $band,
                    'priority_label' =>
                        DailyTaskPriority::label($band),
                    'priority_score' => $score,
                    'destination' => $destination,
                ],
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
