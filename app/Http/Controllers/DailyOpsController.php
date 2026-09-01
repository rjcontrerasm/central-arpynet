<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\Task;
use App\Support\DailyTaskPriority;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyOpsController extends Controller
{
    public function show(Request $request): View
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
        ]);

        $user = $request->user();

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
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday'],
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
            function (Task $task) use ($now): void {
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
            },
        );

        if ($selectedPriority) {
            $tasks = $tasks
                ->filter(
                    fn (Task $task): bool =>
                        $task->display_priority_band
                        === $selectedPriority,
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
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'status',
                ['resolved', 'closed', 'cancelled'],
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

        return view(
            'daily-ops',
            compact(
                'now',
                'organizations',
                'selectedScope',
                'search',
                'selectedPriority',
                'overdueCount',
                'todayCount',
                'weekCount',
                'noDateCount',
                'waitingCount',
                'waitingTasks',
                'nowTasks',
                'todayTasks',
                'upcomingTasks',
                'noDateTasks',
                'upcomingObligations',
                'openIncidents',
            ),
        );
    }
}
