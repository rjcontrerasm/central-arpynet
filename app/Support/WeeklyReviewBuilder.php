<?php

namespace App\Support;

use App\Models\ObligationOccurrence;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WeeklyReviewBuilder
{
    public function build(
        User $user,
        CarbonImmutable $now,
    ): array {
        $organizationIds = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $user->id,
            )
            ->where(
                'is_active',
                true,
            )
            ->pluck('organization_id');

        $today = $now->startOfDay();

        $weekStart = $today
            ->startOfWeek();

        $weekEnd = $weekStart
            ->addDays(6)
            ->endOfDay();

        $sevenDays = $today
            ->addDays(7)
            ->endOfDay();

        $thirtyDays = $today
            ->addDays(30)
            ->endOfDay();

        $carryoverTasks = Task::query()
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'status',
                [
                    'completed',
                    'cancelled',
                    'someday',
                    'waiting',
                ],
            )
            ->whereNotNull('due_at')
            ->where('due_at', '<', $today)
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        $waitingOverdue = Task::query()
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->where('status', 'waiting')
            ->whereNotNull('waiting_until')
            ->where(
                'waiting_until',
                '<',
                $today,
            )
            ->orderBy('waiting_until')
            ->limit(50)
            ->get();

        $taskSignals = Task::query()
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'status',
                [
                    'completed',
                    'cancelled',
                    'someday',
                ],
            )
            ->limit(400)
            ->get()
            ->map(
                function (Task $task) use (
                    $now,
                ): array {
                    $signal =
                        TaskOperationalSignals::evaluate(
                            $task,
                            $now,
                        );

                    return [
                        'task' => $task,
                        ...$signal,
                    ];
                },
            )
            ->filter(
                fn (array $item): bool =>
                    $item['stagnant']
                    || $item[
                        'no_next_action'
                    ],
            )
            ->sortByDesc('rank')
            ->values();

        $stagnantProjects =
            Project::query()
                ->with('organization')
                ->whereIn(
                    'organization_id',
                    $organizationIds,
                )
                ->whereNotIn(
                    'status',
                    [
                        'completed',
                        'cancelled',
                    ],
                )
                ->get()
                ->filter(
                    fn (Project $project): bool =>
                        $project
                            ->stagnation_days
                            >= 15
                        || filled(
                            $project->blockers,
                        ),
                )
                ->sortByDesc(
                    'stagnation_days',
                )
                ->values();

        $receivables = ServiceOrder::query()
            ->with([
                'organization',
                'client',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'stage',
                [
                    'paid',
                    'closed',
                    'cancelled',
                ],
            )
            ->whereNull('paid_date')
            ->where(
                function ($query): void {
                    $query
                        ->whereNotNull(
                            'invoice_number',
                        )
                        ->orWhereNotNull(
                            'invoice_date',
                        )
                        ->orWhere(
                            'invoice_amount',
                            '>',
                            0,
                        );
                },
            )
            ->get();

        $overdueReceivables =
            $receivables
                ->filter(
                    fn (
                        ServiceOrder $order,
                    ): bool =>
                        $order
                            ->invoice_due_date
                        && $order
                            ->invoice_due_date
                            ->isBefore($today),
                )
                ->sortBy(
                    'invoice_due_date',
                )
                ->values();

        $readyToInvoice =
            ServiceOrder::query()
                ->with([
                    'organization',
                    'client',
                ])
                ->whereIn(
                    'organization_id',
                    $organizationIds,
                )
                ->where(
                    'stage',
                    'conformity',
                )
                ->whereNull(
                    'invoice_number',
                )
                ->orderBy(
                    'stage_changed_at',
                )
                ->limit(50)
                ->get();

        $receivableTotals =
            $receivables
                ->groupBy('currency')
                ->map(
                    fn (
                        Collection $orders,
                    ): float =>
                        $orders->sum(
                            fn (
                                ServiceOrder $order,
                            ): float =>
                                (float) (
                                    $order
                                        ->invoice_amount
                                    ?? $order->amount
                                    ?? 0
                                ),
                        ),
                );

        $overdueTotals =
            $overdueReceivables
                ->groupBy('currency')
                ->map(
                    fn (
                        Collection $orders,
                    ): float =>
                        $orders->sum(
                            fn (
                                ServiceOrder $order,
                            ): float =>
                                (float) (
                                    $order
                                        ->invoice_amount
                                    ?? $order->amount
                                    ?? 0
                                ),
                        ),
                );

        $overdueObligations =
            ObligationOccurrence::query()
                ->with([
                    'organization',
                    'obligation',
                ])
                ->whereIn(
                    'organization_id',
                    $organizationIds,
                )
                ->where(
                    'status',
                    'pending',
                )
                ->whereDate(
                    'due_date',
                    '<',
                    $today->toDateString(),
                )
                ->orderBy('due_date')
                ->limit(50)
                ->get();

        $next30Obligations =
            ObligationOccurrence::query()
                ->with([
                    'organization',
                    'obligation',
                ])
                ->whereIn(
                    'organization_id',
                    $organizationIds,
                )
                ->where(
                    'status',
                    'pending',
                )
                ->whereDate(
                    'due_date',
                    '>=',
                    $today->toDateString(),
                )
                ->whereDate(
                    'due_date',
                    '<=',
                    $thirtyDays
                        ->toDateString(),
                )
                ->orderBy('due_date')
                ->limit(100)
                ->get();

        $horizon = [
            'tasks_7' =>
                $this->tasksUntil(
                    $organizationIds,
                    $today,
                    $sevenDays,
                ),
            'tasks_30' =>
                $this->tasksUntil(
                    $organizationIds,
                    $today,
                    $thirtyDays,
                ),
            'services_7' =>
                $this->servicesUntil(
                    $organizationIds,
                    $today,
                    $sevenDays,
                ),
            'services_30' =>
                $this->servicesUntil(
                    $organizationIds,
                    $today,
                    $thirtyDays,
                ),
            'obligations_7' =>
                $next30Obligations
                    ->filter(
                        fn (
                            ObligationOccurrence $item,
                        ): bool =>
                            $item->due_date
                                ->lte(
                                    $sevenDays,
                                ),
                    )
                    ->count(),
            'obligations_30' =>
                $next30Obligations->count(),
        ];

        $nextItems = collect()
            ->concat(
                Task::query()
                    ->with('organization')
                    ->whereIn(
                        'organization_id',
                        $organizationIds,
                    )
                    ->whereNotIn(
                        'status',
                        [
                            'completed',
                            'cancelled',
                            'someday',
                        ],
                    )
                    ->whereBetween(
                        'due_at',
                        [
                            $today,
                            $thirtyDays,
                        ],
                    )
                    ->orderBy('due_at')
                    ->limit(8)
                    ->get()
                    ->map(
                        fn (Task $task): array => [
                            'date' =>
                                $task->due_at,
                            'title' =>
                                $task->title,
                            'organization' =>
                                $task
                                    ->organization
                                    ?->name,
                            'type' => 'Tarea',
                        ],
                    ),
            )
            ->concat(
                $next30Obligations
                    ->take(8)
                    ->map(
                        fn (
                            ObligationOccurrence $item,
                        ): array => [
                            'date' =>
                                $item->due_date,
                            'title' =>
                                $item
                                    ->obligation
                                    ?->name
                                ?: 'Vencimiento',
                            'organization' =>
                                $item
                                    ->organization
                                    ?->name,
                            'type' =>
                                'Vencimiento',
                        ],
                    ),
            )
            ->sortBy('date')
            ->take(10)
            ->values();

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'carryover_tasks' =>
                $carryoverTasks,
            'waiting_overdue' =>
                $waitingOverdue,
            'task_signals' =>
                $taskSignals,
            'stagnant_projects' =>
                $stagnantProjects,
            'receivables' =>
                $receivables,
            'overdue_receivables' =>
                $overdueReceivables,
            'ready_to_invoice' =>
                $readyToInvoice,
            'receivable_totals' =>
                $receivableTotals,
            'overdue_totals' =>
                $overdueTotals,
            'overdue_obligations' =>
                $overdueObligations,
            'next30_obligations' =>
                $next30Obligations,
            'horizon' => $horizon,
            'next_items' =>
                $nextItems,
            'counts' => [
                'carryover' =>
                    $carryoverTasks->count()
                    + $waitingOverdue
                        ->count(),
                'stagnation' =>
                    $taskSignals->count()
                    + $stagnantProjects
                        ->count(),
                'finance' =>
                    $overdueReceivables
                        ->count()
                    + $readyToInvoice
                        ->count(),
                'obligations' =>
                    $overdueObligations
                        ->count()
                    + $next30Obligations
                        ->count(),
                'horizon_7' =>
                    $horizon['tasks_7']
                    + $horizon[
                        'services_7'
                    ]
                    + $horizon[
                        'obligations_7'
                    ],
                'horizon_30' =>
                    $horizon['tasks_30']
                    + $horizon[
                        'services_30'
                    ]
                    + $horizon[
                        'obligations_30'
                    ],
            ],
        ];
    }

    private function tasksUntil(
        Collection $organizationIds,
        CarbonImmutable $today,
        CarbonImmutable $end,
    ): int {
        return Task::query()
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'status',
                [
                    'completed',
                    'cancelled',
                    'someday',
                ],
            )
            ->whereBetween(
                'due_at',
                [$today, $end],
            )
            ->count();
    }

    private function servicesUntil(
        Collection $organizationIds,
        CarbonImmutable $today,
        CarbonImmutable $end,
    ): int {
        return ServiceOrder::query()
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'stage',
                [
                    'paid',
                    'closed',
                    'cancelled',
                ],
            )
            ->whereBetween(
                'next_action_at',
                [$today, $end],
            )
            ->count();
    }
}
