<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringTaskRule;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkTeam;
use App\Support\DailyTaskPriority;
use App\Support\GlobalUndoService;
use App\Support\RecurringTaskGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyTaskWaitingController extends Controller
{
    public function wait(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'waiting_until' => [
                'required',
                'date',
            ],
            'waiting_reason' => [
                'required',
                'string',
                'max:255',
            ],
            'scope' => [
                'nullable',
                'integer',
            ],
            'q' => [
                'nullable',
                'string',
                'max:120',
            ],
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

        $this->authorizeTask($request, $task);

        $timezone = config(
            'app.timezone',
            'America/Lima',
        );
        $now = CarbonImmutable::now($timezone);

        $before = $undo->captureTask(
            $task,
        );

        $task->forceFill([
            'waiting_since' => $now,
            'waiting_until' =>
                $validated['waiting_until'],
            'waiting_reason' =>
                trim($validated['waiting_reason']),
        ])->save();

        $filters = $this->filters(
            $request,
            $validated,
        );

        $undoAction = $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            'Tarea puesta en espera',
            route(
                'daily-ops.show',
                $filters,
                false,
            ),
        );

        if ($this->isLive($request)) {
            $task->loadMissing([
                'organization',
                'assignee',
                'workTeams',
            ]);

            return response()->json([
                'ok' => true,
                'task_id' => $task->id,
                'label' => 'Tarea puesta en espera',
                'waiting' => [
                    'reason' =>
                        $task->waiting_reason,
                    'until' =>
                        $task->waiting_until
                            ?->format('d/m/Y'),
                    'card_html' => view(
                        'partials.daily-waiting-card',
                        array_merge(
                            ['task' => $task],
                            $this->viewContext(
                                $request,
                                $filters,
                                $now,
                            ),
                        ),
                    )->render(),
                ],
                'undo' =>
                    $undo->clientPayload(
                        $undoAction,
                    ),
            ]);
        }

        return redirect()
            ->route(
                'daily-ops.show',
                $filters,
            )
            ->with(
                'daily_action_success',
                'Tarea puesta en espera.',
            );
    }

    public function resume(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
        RecurringTaskGenerator $recurringGenerator,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'scope' => [
                'nullable',
                'integer',
            ],
            'q' => [
                'nullable',
                'string',
                'max:120',
            ],
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

        $this->authorizeTask($request, $task);

        $before = $undo->captureTask(
            $task,
        );

        $task->forceFill([
            'waiting_since' => null,
            'waiting_until' => null,
            'waiting_reason' => null,
        ])->save();

        $filters = $this->filters(
            $request,
            $validated,
        );

        $undoAction = $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            'Tarea reactivada',
            route(
                'daily-ops.show',
                $filters,
                false,
            ),
        );

        if ($this->isLive($request)) {
            $now = CarbonImmutable::now(
                config(
                    'app.timezone',
                    'America/Lima',
                ),
            );

            $task->loadMissing([
                'organization',
                'assignee',
                'workTeams',
                'project',
                'recurringRun.rule',
            ]);

            $presentation = $this->presentation(
                $task,
                $now,
            );

            $this->decorateTask(
                $task,
                $presentation,
                $recurringGenerator,
            );

            return response()->json([
                'ok' => true,
                'task_id' => $task->id,
                'label' => 'Tarea reactivada',
                'presentation' => $presentation,
                'card_html' => view(
                    'partials.daily-task-card',
                    array_merge(
                        ['task' => $task],
                        $this->viewContext(
                            $request,
                            $filters,
                            $now,
                        ),
                        $this->editorContext(
                            $request,
                        ),
                    ),
                )->render(),
                'undo' =>
                    $undo->clientPayload(
                        $undoAction,
                    ),
            ]);
        }

        return redirect()
            ->route(
                'daily-ops.show',
                $filters,
            )
            ->with(
                'daily_action_success',
                'Tarea reactivada.',
            );
    }

    private function isLive(
        Request $request,
    ): bool {
        return $request->expectsJson()
            || $request->boolean('_live')
            || $request->header(
                'X-Central-Live-Action',
            ) === '1';
    }

    private function presentation(
        Task $task,
        CarbonImmutable $now,
    ): array {
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

        return [
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
        ];
    }

    private function viewContext(
        Request $request,
        array $filters,
        CarbonImmutable $now,
    ): array {
        return [
            'now' => $now,
            'currentUser' => $request->user(),
            'selectedWorkView' =>
                $filters['view'] ?? 'mine',
            'selectedScope' =>
                $filters['scope'] ?? null,
            'search' =>
                $filters['q'] ?? '',
            'selectedPriority' =>
                $filters['priority'] ?? null,
            'selectedRecurringRule' =>
                $filters['recurring_rule'] ?? null,
            'selectedWorkTeam' =>
                $filters['work_team'] ?? null,
        ];
    }

    private function decorateTask(
        Task $task,
        array $presentation,
        RecurringTaskGenerator $recurringGenerator,
    ): void {
        $task->setAttribute(
            'display_priority_score',
            $presentation['priority_score'],
        );
        $task->setAttribute(
            'display_priority_band',
            $presentation['priority_band'],
        );
        $task->setAttribute(
            'display_priority_label',
            $presentation['priority_label'],
        );

        $run = $task->recurringRun;
        $rule = $run?->rule;

        if ($rule && $run?->scheduled_for) {
            $task->setAttribute(
                'recurrence_label',
                RecurringTaskRule::frequencyOptions()[
                    $rule->frequency
                ] ?? $rule->frequency,
            );
            $task->setAttribute(
                'recurrence_next_date',
                $recurringGenerator
                    ->nextScheduledDate(
                        $rule,
                        $run->scheduled_for,
                    ),
            );

            return;
        }

        $task->setAttribute(
            'recurrence_label',
            null,
        );
        $task->setAttribute(
            'recurrence_next_date',
            null,
        );
    }

    private function editorContext(
        Request $request,
    ): array {
        $user = $request->user();

        $workTeams = WorkTeam::query()
            ->visibleTo($user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'work_teams.id',
                'work_teams.name',
            ]);

        $visibleScopeOrganizationIds = collect(
            $user->activeOrganizationIds(),
        )
            ->merge(
                $user->taskScopeOrganizationIds(),
            )
            ->unique()
            ->values();

        $organizations = Organization::query()
            ->whereIn(
                'id',
                $visibleScopeOrganizationIds,
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $organizationAssigneeIds = DB::table(
            'organization_user',
        )
            ->whereIn(
                'organization_id',
                $visibleScopeOrganizationIds,
            )
            ->where('is_active', true)
            ->whereIn(
                'role',
                ['owner', 'admin', 'member'],
            )
            ->pluck('user_id');

        $teamAssigneeIds = DB::table(
            'work_team_user',
        )
            ->whereIn(
                'work_team_id',
                $workTeams->pluck('id'),
            )
            ->where('is_active', true)
            ->whereIn(
                'role',
                ['lead', 'member'],
            )
            ->pluck('user_id');

        $taskAssignees = User::query()
            ->whereIn(
                'id',
                $organizationAssigneeIds
                    ->merge($teamAssigneeIds)
                    ->push($user->id)
                    ->unique()
                    ->values(),
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $taskAssigneeIds = $taskAssignees
            ->pluck('id');

        $taskAssigneeOrganizationIds = DB::table(
            'organization_user',
        )
            ->whereIn('user_id', $taskAssigneeIds)
            ->whereIn(
                'organization_id',
                $visibleScopeOrganizationIds,
            )
            ->where('is_active', true)
            ->whereIn(
                'role',
                ['owner', 'admin', 'member'],
            )
            ->get([
                'user_id',
                'organization_id',
            ])
            ->groupBy('user_id')
            ->map(
                fn ($rows) => $rows
                    ->pluck('organization_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
            );

        $taskAssigneeWorkTeamIds = DB::table(
            'work_team_user',
        )
            ->whereIn('user_id', $taskAssigneeIds)
            ->whereIn(
                'work_team_id',
                $workTeams->pluck('id'),
            )
            ->where('is_active', true)
            ->whereIn(
                'role',
                ['lead', 'member'],
            )
            ->get([
                'user_id',
                'work_team_id',
            ])
            ->groupBy('user_id')
            ->map(
                fn ($rows) => $rows
                    ->pluck('work_team_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
            );

        $taskProjects = Project::query()
            ->whereIn(
                'organization_id',
                $visibleScopeOrganizationIds,
            )
            ->whereNotIn(
                'status',
                ['completed', 'cancelled'],
            )
            ->orderBy('name')
            ->get([
                'id',
                'organization_id',
                'name',
            ]);

        return compact(
            'organizations',
            'workTeams',
            'taskAssignees',
            'taskAssigneeOrganizationIds',
            'taskAssigneeWorkTeamIds',
            'taskProjects',
        );
    }

    private function authorizeTask(
        Request $request,
        Task $task,
    ): void {
        abort_unless(
            $task->canBeUpdatedBy($request->user()),
            403,
        );
    }

    private function filters(
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
