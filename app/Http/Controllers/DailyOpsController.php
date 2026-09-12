<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringTaskRule;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Support\DailyTaskPriority;
use App\Support\GlobalTrackingItemFactory;
use App\Support\RecurringTaskGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyOpsController extends Controller
{
    public function show(
        Request $request,
        RecurringTaskGenerator $recurringGenerator,
    ): View
    {
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
                'in:critical,today,week,planned',
            ],
            'view' => [
                'nullable',
                'in:mine,team,unassigned',
            ],
        ]);

        $user = $request->user();
        $selectedWorkView = $validated['view'] ?? 'mine';

        $organizationIds = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        $organizations = Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedScope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if (
            $selectedScope
            && ! $organizationIds->contains($selectedScope)
        ) {
            abort(403);
        }

        $search = trim(
            (string) ($validated['q'] ?? ''),
        );

        $selectedPriority =
            $validated['priority'] ?? null;

        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        $now = CarbonImmutable::now($timezone);
        $todayStart = $now->startOfDay();
        $todayEnd = $now->endOfDay();
        $weekEnd = $now->addDays(7)->endOfDay();

        $tasksQuery = Task::query()
            ->with([
                'organization',
                'assignee',
                'recurringRun.rule',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday'],
            );

        $this->applyWorkView(
            $tasksQuery,
            $selectedWorkView,
            $user->id,
        );

        if ($selectedScope) {
            $tasksQuery->where(
                'organization_id',
                $selectedScope,
            );
        }

        if ($search !== '') {
            $tasksQuery->where(
                'title',
                'like',
                '%'.$search.'%',
            );
        }

        $tasks = $tasksQuery
            ->orderByRaw(
                'CASE WHEN due_at IS NULL THEN 1 ELSE 0 END',
            )
            ->orderBy('due_at')
            ->get();

        $tasks->each(
            function (Task $task) use (
                $now,
                $recurringGenerator,
            ): void {
                $score = DailyTaskPriority::score(
                    $task,
                    $now,
                );

                $band = DailyTaskPriority::band(
                    $task,
                    $now,
                );

                $task->setAttribute(
                    'display_priority_score',
                    $score,
                );

                $task->setAttribute(
                    'display_priority_band',
                    $band,
                );

                $task->setAttribute(
                    'display_priority_label',
                    DailyTaskPriority::label($band),
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
                } else {
                    $task->setAttribute(
                        'recurrence_label',
                        null,
                    );

                    $task->setAttribute(
                        'recurrence_next_date',
                        null,
                    );
                }
            },
        );

        if ($selectedPriority) {
            $tasks = $tasks
                ->filter(
                    function (Task $task) use (
                        $selectedPriority,
                        $todayStart,
                    ): bool {
                        if (
                            $selectedPriority === 'critical'
                        ) {
                            return (
                                $task->due_at
                                && $task->due_at->isBefore(
                                    $todayStart,
                                )
                            )
                            || $task->display_priority_band
                                === 'critical';
                        }

                        return $task->display_priority_band
                            === $selectedPriority;
                    },
                )
                ->values();
        }

        $allWaitingTasks = $tasks
            ->filter(
                fn (Task $task): bool =>
                    ! is_null($task->waiting_since),
            )
            ->values();

        $waitingCount = $allWaitingTasks->count();

        $waitingTasks = $allWaitingTasks
            ->sortBy(
                fn (Task $task): string =>
                    (string) (
                        $task->waiting_until
                        ?? '9999-12-31'
                    ),
            )
            ->take(12)
            ->values();

        $activeTasks = $tasks
            ->filter(
                fn (Task $task): bool =>
                    is_null($task->waiting_since),
            )
            ->values();

        $overdueCount = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    $task->due_at
                    && $task->due_at->isBefore(
                        $todayStart,
                    ),
            )
            ->count();

        $todayCount = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    $task->due_at
                    && $task->due_at->isSameDay($now),
            )
            ->count();

        $weekCount = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    $task->due_at
                    && $task->due_at->isAfter(
                        $todayEnd,
                    )
                    && $task->due_at->lessThanOrEqualTo(
                        $weekEnd,
                    ),
            )
            ->count();

        $noDateCount = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    is_null($task->due_at),
            )
            ->count();

        $criticalCount = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    (
                        $task->due_at
                        && $task->due_at->isBefore(
                            $todayStart,
                        )
                    )
                    || $task->display_priority_band
                        === 'critical',
            )
            ->count();

        $priorityTodayCount = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    $task->display_priority_band
                    === 'today',
            )
            ->count();

        $priorityWeekCount = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    $task->display_priority_band
                    === 'week',
            )
            ->count();

        $plannedCount = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    $task->display_priority_band
                    === 'planned',
            )
            ->count();

        $nowTasks = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    (
                        $task->due_at
                        && $task->due_at->isBefore(
                            $todayStart,
                        )
                    )
                    || $task->display_priority_score >= 85,
            )
            ->sortByDesc('display_priority_score')
            ->take(8)
            ->values();

        $nowIds = $nowTasks->pluck('id');

        $todayTasks = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    ! $nowIds->contains($task->id)
                    && $task->due_at
                    && $task->due_at->isSameDay($now),
            )
            ->sortByDesc('display_priority_score')
            ->take(8)
            ->values();

        $usedIds = $nowIds
            ->merge($todayTasks->pluck('id'));

        $upcomingTasks = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    ! $usedIds->contains($task->id)
                    && $task->due_at
                    && $task->due_at->isAfter(
                        $todayEnd,
                    )
                    && $task->due_at->lessThanOrEqualTo(
                        $weekEnd,
                    ),
            )
            ->sortByDesc('display_priority_score')
            ->take(8)
            ->values();

        $noDateTasks = $activeTasks
            ->filter(
                fn (Task $task): bool =>
                    is_null($task->due_at),
            )
            ->sortByDesc('display_priority_score')
            ->take(8)
            ->values();

        $upcomingObligations =
            ObligationOccurrence::query()
                ->with([
                    'organization',
                    'obligation',
                ])
                ->whereIn(
                    'organization_id',
                    $organizationIds,
                )
                ->where('status', 'pending')
                ->where(
                    'due_date',
                    '<=',
                    $weekEnd->toDateString(),
                );

        if ($selectedScope) {
            $upcomingObligations->where(
                'organization_id',
                $selectedScope,
            );
        }

        $upcomingObligations =
            $upcomingObligations
                ->orderBy('due_date')
                ->limit(6)
                ->get();

        $openIncidents = Incident::query()
            ->with([
                'organization',
                'assignee',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'status',
                ['resolved', 'closed', 'cancelled'],
            );

        $this->applyWorkView(
            $openIncidents,
            $selectedWorkView,
            $user->id,
        );

        if ($selectedScope) {
            $openIncidents->where(
                'organization_id',
                $selectedScope,
            );
        }

        $openIncidents = $openIncidents
            ->orderByRaw(
                "CASE severity
                    WHEN 'critical' THEN 0
                    WHEN 'high' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'low' THEN 3
                    ELSE 4
                END",
            )
            ->latest('id')
            ->limit(5)
            ->get();

        $serviceOrders = ServiceOrder::query()
            ->with([
                'organization',
                'client',
                'assignee',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'stage',
                ['closed', 'cancelled'],
            );

        $this->applyWorkView(
            $serviceOrders,
            $selectedWorkView,
            $user->id,
        );

        if ($selectedScope) {
            $serviceOrders->where(
                'organization_id',
                $selectedScope,
            );
        }

        if ($search !== '') {
            $serviceOrders->where(
                function (Builder $query) use ($search): void {
                    $like = '%'.$search.'%';

                    $query
                        ->where('title', 'like', $like)
                        ->orWhere('next_action', 'like', $like)
                        ->orWhere('order_number', 'like', $like)
                        ->orWhere('quotation_number', 'like', $like);
                },
            );
        }

        $serviceOrders = $serviceOrders
            ->orderByRaw(
                'CASE WHEN next_action_at IS NULL THEN 1 ELSE 0 END',
            )
            ->orderBy('next_action_at')
            ->latest('updated_at')
            ->limit(6)
            ->get();

        $projectsQuery = Project::query()
            ->visibleTo($user)
            ->with('organization')
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' =>
                    fn ($taskQuery) => $taskQuery->where(
                        'status',
                        'completed',
                    ),
            ])
            ->whereNotIn(
                'status',
                ['completed', 'cancelled'],
            );

        if ($selectedScope) {
            $projectsQuery->where(
                'organization_id',
                $selectedScope,
            );
        }

        if ($search !== '') {
            $projectsQuery->where(
                function ($projectQuery) use ($search): void {
                    $like = '%'.$search.'%';

                    $projectQuery
                        ->where('name', 'like', $like)
                        ->orWhere('next_action', 'like', $like)
                        ->orWhere('blockers', 'like', $like);
                },
            );
        }

        $projectRows = $projectsQuery
            ->limit(150)
            ->get()
            ->map(
                function (Project $project) use (
                    $now,
                    $todayStart,
                    $weekEnd,
                ): array {
                    $signal =
                        GlobalTrackingItemFactory::project(
                            $project,
                            $now,
                        );

                    $rank = (int) $signal['rank'];
                    $targetSoon = false;

                    if ($project->target_date) {
                        if (
                            $project->target_date->isBefore(
                                $todayStart,
                            )
                        ) {
                            $rank = max($rank, 95);
                            $targetSoon = true;
                        } elseif (
                            $project->target_date->isSameDay(
                                $now,
                            )
                        ) {
                            $rank = max($rank, 85);
                            $targetSoon = true;
                        } elseif (
                            $project->target_date
                                ->lessThanOrEqualTo($weekEnd)
                        ) {
                            $rank = max($rank, 65);
                            $targetSoon = true;
                        }
                    }

                    return [
                        'project' => $project,
                        'signal' => $signal,
                        'rank' => $rank,
                        'target_soon' => $targetSoon,
                    ];
                },
            )
            ->filter(
                fn (array $row): bool =>
                    $row['target_soon']
                    || in_array(
                        $row['signal']['level'],
                        ['critical', 'attention', 'watch'],
                        true,
                    ),
            )
            ->sortByDesc('rank')
            ->values();

        $projectsAttentionCount =
            $projectRows->count();

        $projectsAttention = $projectRows
            ->take(6)
            ->values();

        return view(
            'daily-ops',
            compact(
                'now',
                'organizations',
                'selectedScope',
                'selectedWorkView',
                'search',
                'selectedPriority',
                'overdueCount',
                'todayCount',
                'weekCount',
                'noDateCount',
                'criticalCount',
                'priorityTodayCount',
                'priorityWeekCount',
                'plannedCount',
                'waitingCount',
                'waitingTasks',
                'nowTasks',
                'todayTasks',
                'upcomingTasks',
                'noDateTasks',
                'upcomingObligations',
                'openIncidents',
                'serviceOrders',
                'projectsAttention',
                'projectsAttentionCount',
            ),
        );
    }

    private function applyWorkView(
        Builder $query,
        string $selectedWorkView,
        int $userId,
    ): Builder {
        return match ($selectedWorkView) {
            'mine' => $query->where('assigned_to', $userId),
            'unassigned' => $query->whereNull('assigned_to'),
            default => $query,
        };
    }
}
