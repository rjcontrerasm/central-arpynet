<?php

namespace App\Support;

use App\Models\ObligationOccurrence;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ExecutiveSummaryBuilder
{
    public function build(
        Collection $organizationIds,
        ?int $selectedScope,
        string $period,
        CarbonImmutable $now,
    ): array {
        $start = $now->startOfDay();

        $end = $period === 'week'
            ? $start->addDays(7)->endOfDay()
            : $start->endOfDay();

        $attention = collect()
            ->concat(
                $this->taskItems(
                    $organizationIds,
                    $selectedScope,
                    $now,
                ),
            )
            ->concat(
                $this->projectItems(
                    $organizationIds,
                    $selectedScope,
                    $now,
                ),
            )
            ->concat(
                $this->serviceItems(
                    $organizationIds,
                    $selectedScope,
                    $now,
                ),
            )
            ->concat(
                $this->obligationItems(
                    $organizationIds,
                    $selectedScope,
                    $now,
                ),
            )
            ->filter(
                fn (array $item): bool =>
                    GlobalTrackingItemFactory::needsAttention(
                        $item,
                    ),
            )
            ->sortByDesc('rank')
            ->values();

        $decisions = $attention
            ->filter(
                fn (array $item): bool =>
                    ExecutiveDecisionAdvisor::isDecision(
                        $item,
                    ),
            )
            ->map(
                function (
                    array $item,
                ): array {
                    $advice =
                        ExecutiveDecisionAdvisor::recommend(
                            $item,
                        );

                    return $item + [
                        'recommended_action' =>
                            $advice['action'],
                        'decision_reason' =>
                            $advice['reason'],
                    ];
                },
            )
            ->take(6)
            ->values();

        $dueTasks = Task::query()
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday'],
            )
            ->whereNotNull('due_at')
            ->where(
                'due_at',
                '<=',
                $end,
            )
            ->orderBy('due_at')
            ->limit(100)
            ->get();

        $waitingFollowUps = Task::query()
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->whereNotNull('waiting_since')
            ->whereNotNull('waiting_until')
            ->where(
                'waiting_until',
                '<=',
                $end,
            )
            ->orderBy('waiting_until')
            ->limit(100)
            ->get();

        $serviceActions = ServiceOrder::query()
            ->with([
                'organization',
                'client',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->whereNotIn(
                'stage',
                ['paid', 'closed', 'cancelled'],
            )
            ->whereNotNull('next_action_at')
            ->where(
                'next_action_at',
                '<=',
                $end,
            )
            ->orderBy('next_action_at')
            ->limit(100)
            ->get();

        $obligations = ObligationOccurrence::query()
            ->with([
                'organization',
                'obligation',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->where('status', 'pending')
            ->where(
                'due_date',
                '<=',
                $end->toDateString(),
            )
            ->orderBy('due_date')
            ->limit(100)
            ->get();

        $projects = Project::query()
            ->with('organization')
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' =>
                    fn ($query) =>
                        $query->where(
                            'status',
                            'completed',
                        ),
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->whereNotIn(
                'status',
                ['completed', 'cancelled'],
            )
            ->get()
            ->filter(
                fn (Project $project): bool =>
                    $project->stagnation_days >= 15
                    || (
                        $project->target_date
                        && $project->target_date
                            ->lte($end->toDateString())
                    )
                    || filled($project->blockers),
            )
            ->sortByDesc('stagnation_days')
            ->take(50)
            ->values();

        $serviceFinancial = ServiceOrder::query()
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->get()
            ->groupBy('currency')
            ->map(
                function ($orders): array {
                    $invoiced = $orders->filter(
                        fn ($order): bool =>
                            $order->invoice_number
                            || $order->invoice_date
                            || (float) (
                                $order->invoice_amount
                                ?? 0
                            ) > 0,
                    );

                    $receivable = $invoiced->filter(
                        fn ($order): bool =>
                            ! $order->paid_date,
                    );

                    $overdue = $receivable->filter(
                        fn ($order): bool =>
                            $order->invoice_due_date
                            && $order->invoice_due_date->isPast(),
                    );

                    return [
                        'invoiced' => $invoiced->sum(
                            fn ($order): float =>
                                (float) (
                                    $order->invoice_amount
                                    ?? $order->amount
                                    ?? 0
                                ),
                        ),
                        'receivable' => $receivable->sum(
                            fn ($order): float =>
                                (float) (
                                    $order->invoice_amount
                                    ?? $order->amount
                                    ?? 0
                                ),
                        ),
                        'overdue' => $overdue->sum(
                            fn ($order): float =>
                                (float) (
                                    $order->invoice_amount
                                    ?? $order->amount
                                    ?? 0
                                ),
                        ),
                    ];
                },
            );

        $obligationFinancial = ObligationOccurrence::query()
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->where('status', 'pending')
            ->get()
            ->groupBy('currency')
            ->map(
                function ($items) use ($now): array {
                    $overdue = $items->filter(
                        fn ($item): bool =>
                            $item->due_date->isBefore(
                                $now->startOfDay(),
                            ),
                    );

                    return [
                        'pending' => $items->sum(
                            fn ($item): float =>
                                (float) (
                                    $item->expected_amount
                                    ?? 0
                                ),
                        ),
                        'overdue' => $overdue->sum(
                            fn ($item): float =>
                                (float) (
                                    $item->expected_amount
                                    ?? 0
                                ),
                        ),
                    ];
                },
            );

        return [
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'attention' => $attention->take(12),
            'decisions' => $decisions,
            'counts' => [
                'decisions' =>
                    $decisions->count(),
                'critical' => $attention
                    ->where('level', 'critical')
                    ->count(),
                'attention' => $attention
                    ->whereIn(
                        'level',
                        ['attention', 'watch'],
                    )
                    ->count(),
                'tasks_due' => $dueTasks->count(),
                'waiting_followups' =>
                    $waitingFollowUps->count(),
                'service_actions' =>
                    $serviceActions->count(),
                'obligations' =>
                    $obligations->count(),
                'projects' =>
                    $projects->count(),
            ],
            'due_tasks' => $dueTasks,
            'waiting_followups' => $waitingFollowUps,
            'service_actions' => $serviceActions,
            'obligations' => $obligations,
            'projects' => $projects,
            'service_financial' => $serviceFinancial,
            'obligation_financial' =>
                $obligationFinancial,
        ];
    }

    private function taskItems(
        Collection $organizationIds,
        ?int $selectedScope,
        CarbonImmutable $now,
    ): Collection {
        return Task::query()
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday'],
            )
            ->limit(250)
            ->get()
            ->map(
                fn (Task $task): array =>
                    GlobalTrackingItemFactory::task(
                        $task,
                        $now,
                    ),
            );
    }

    private function projectItems(
        Collection $organizationIds,
        ?int $selectedScope,
        CarbonImmutable $now,
    ): Collection {
        return Project::query()
            ->with('organization')
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' =>
                    fn ($query) =>
                        $query->where(
                            'status',
                            'completed',
                        ),
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->whereNotIn(
                'status',
                ['completed', 'cancelled'],
            )
            ->limit(150)
            ->get()
            ->map(
                fn (Project $project): array =>
                    GlobalTrackingItemFactory::project(
                        $project,
                        $now,
                    ),
            );
    }

    private function serviceItems(
        Collection $organizationIds,
        ?int $selectedScope,
        CarbonImmutable $now,
    ): Collection {
        return ServiceOrder::query()
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->whereNotIn(
                'stage',
                ['paid', 'closed', 'cancelled'],
            )
            ->limit(150)
            ->get()
            ->map(
                fn (ServiceOrder $order): array =>
                    GlobalTrackingItemFactory::serviceOrder(
                        $order,
                        $now,
                    ),
            );
    }

    private function obligationItems(
        Collection $organizationIds,
        ?int $selectedScope,
        CarbonImmutable $now,
    ): Collection {
        return ObligationOccurrence::query()
            ->with([
                'organization',
                'obligation',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->where('status', 'pending')
            ->limit(150)
            ->get()
            ->map(
                fn (
                    ObligationOccurrence $occurrence,
                ): array =>
                    GlobalTrackingItemFactory::obligation(
                        $occurrence,
                        $now,
                    ),
            );
    }
}
