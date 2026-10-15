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
use Illuminate\Support\Facades\DB;

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
            'follow_up_mode' => [
                'nullable',
                'in:none,tomorrow,custom',
            ],
            'follow_up_due_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
                'required_if:follow_up_mode,custom',
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

        $operation = DB::transaction(
            function () use (
                $request,
                $task,
                $validated,
                $actions,
            ): array {
                $lockedTask = Task::query()
                    ->whereKey($task->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $result = $actions->execute(
                    $request->user(),
                    $lockedTask,
                    $validated['action'],
                    confirmed: true,
                );

                $followUp = null;
                $followUpCreated = false;

                if (
                    $validated['action'] === 'complete'
                    && in_array(
                        $validated['follow_up_mode']
                            ?? 'none',
                        ['tomorrow', 'custom'],
                        true,
                    )
                    && filled($lockedTask->next_action)
                ) {
                    [
                        $followUp,
                        $followUpCreated,
                    ] = $this->createFollowUpTask(
                        $request,
                        $lockedTask,
                        (string) (
                            $validated[
                                'follow_up_mode'
                            ]
                        ),
                        $validated[
                            'follow_up_due_date'
                        ] ?? null,
                    );
                }

                return [
                    'result' => $result,
                    'follow_up' => $followUp,
                    'follow_up_created' =>
                        $followUpCreated,
                ];
            },
        );

        $result = $operation['result'];
        $followUp = $operation['follow_up'];
        $followUpCreated =
            $operation['follow_up_created'];

        $task->refresh();

        $label = $followUpCreated
            ? $result['label']
                .' · próxima acción creada'
            : $result['label'];

        $cleanup = (
            $followUp
            && $followUpCreated
        )
            ? [
                'kind' => 'task',
                'id' => $followUp->id,
                'updated_at' =>
                    $followUp->updated_at
                        ?->toIso8601String(),
                'parent_task_id' => $task->id,
            ]
            : [];

        $undoAction = $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            $label,
            route(
                'daily-ops.show',
                $filters,
                false,
            ),
            $cleanup,
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
                'label' => $label,
                'follow_up' => $followUp
                    ? [
                        'id' => $followUp->id,
                        'title' => $followUp->title,
                        'due_date' => $followUp->due_at
                            ?->format('d/m/Y'),
                        'created' => $followUpCreated,
                    ]
                    : null,
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
                $label.'.',
            );
    }

    private function createFollowUpTask(
        Request $request,
        Task $parent,
        string $mode,
        ?string $customDate,
    ): array {
        $externalId =
            'parent:'.$parent->id.':next-action';

        $existing = Task::query()
            ->where(
                'external_system',
                'central_next_action',
            )
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        $dueAt = $mode === 'tomorrow'
            ? CarbonImmutable::now($timezone)
                ->addDay()
                ->setTime(17, 0)
            : CarbonImmutable::parse(
                (string) $customDate,
                $timezone,
            )->setTime(17, 0);

        $followUp = Task::query()->create([
            'organization_id' =>
                $parent->organization_id,
            'project_id' => $parent->project_id,
            'parent_task_id' => $parent->id,
            'title' => trim(
                (string) $parent->next_action,
            ),
            'status' => 'pending',
            'urgency' => $parent->urgency,
            'impact' => $parent->impact,
            'due_at' => $dueAt,
            'source' => 'next_action',
            'external_system' =>
                'central_next_action',
            'external_id' => $externalId,
            'assigned_to' =>
                $parent->assigned_to,
            'created_by' =>
                $request->user()->id,
            'is_private' =>
                (bool) $parent->is_private,
            'visibility_scope' =>
                $parent->visibility_scope
                    ?: 'organization',
        ]);

        $teamIds = $parent->workTeams()
            ->pluck('work_teams.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($teamIds !== []) {
            $followUp->workTeams()->sync(
                $teamIds,
            );
        }

        return [$followUp->fresh(), true];
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
