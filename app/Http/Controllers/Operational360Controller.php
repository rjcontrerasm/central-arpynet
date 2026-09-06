<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Support\GlobalTrackingItemFactory;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class Operational360Controller extends Controller
{
    public function show(Request $request): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        $organizations = $user->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->orderBy('organizations.name')
            ->get(['organizations.id', 'organizations.name']);

        $organizationIds = $organizations
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $selectedScope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if (
            $selectedScope
            && ! $organizationIds->contains($selectedScope)
        ) {
            abort(403);
        }

        $clients = Client::query()
            ->visibleTo($user)
            ->where('is_active', true)
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->with('organization')
            ->withCount([
                'serviceOrders',
                'serviceOrders as open_services_count' =>
                    fn ($query) => $query->whereNotIn(
                        'stage',
                        ['paid', 'closed', 'cancelled'],
                    ),
            ])
            ->orderBy('name')
            ->limit(100)
            ->get();

        $services = ServiceOrder::query()
            ->visibleTo($user)
            ->with(['organization', 'client'])
            ->whereNotIn(
                'stage',
                ['paid', 'closed', 'cancelled'],
            )
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->limit(200)
            ->get();

        $financialServices = ServiceOrder::query()
            ->visibleTo($user)
            ->where('stage', '!=', 'cancelled')
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->limit(1000)
            ->get();

        $projects = Project::query()
            ->visibleTo($user)
            ->with('organization')
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' =>
                    fn ($query) => $query->where(
                        'status',
                        'completed',
                    ),
            ])
            ->whereNotIn(
                'status',
                ['completed', 'cancelled'],
            )
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->limit(200)
            ->get();

        $tasks = Task::query()
            ->visibleTo($user)
            ->with(['organization', 'project'])
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday'],
            )
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->limit(300)
            ->get();

        $obligations = ObligationOccurrence::query()
            ->visibleTo($user)
            ->with(['organization', 'obligation'])
            ->where('status', 'pending')
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->limit(300)
            ->get();

        $incidents = Incident::query()
            ->visibleTo($user)
            ->open()
            ->with([
                'organization',
                'client',
                'serviceOrder',
                'project',
            ])
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->limit(200)
            ->get()
            ->sortByDesc(
                fn (Incident $incident): int => match (
                    $incident->severity
                ) {
                    'critical' => 50,
                    'high' => 40,
                    'medium' => 30,
                    'low' => 20,
                    default => 10,
                },
            )
            ->values();

        $now = CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $attention = collect()
            ->concat(
                $tasks->map(
                    fn (Task $task): array =>
                        GlobalTrackingItemFactory::task(
                            $task,
                            $now,
                        ),
                ),
            )
            ->concat(
                $projects->map(
                    fn (Project $project): array =>
                        GlobalTrackingItemFactory::project(
                            $project,
                            $now,
                        ),
                ),
            )
            ->concat(
                $services->map(
                    fn (ServiceOrder $order): array =>
                        GlobalTrackingItemFactory::serviceOrder(
                            $order,
                            $now,
                        ),
                ),
            )
            ->concat(
                $obligations->map(
                    fn (ObligationOccurrence $occurrence): array =>
                        GlobalTrackingItemFactory::obligation(
                            $occurrence,
                            $now,
                        ),
                ),
            )
            ->filter(
                fn (array $item): bool =>
                    GlobalTrackingItemFactory::needsAttention(
                        $item,
                    ),
            )
            ->sortByDesc('rank')
            ->take(15)
            ->values();

        $finances = $this->financeByCurrency(
            $financialServices,
            $projects,
            $obligations,
            $now,
        );

        return view('operational-360', [
            'organizations' => $organizations,
            'selectedScope' => $selectedScope,
            'clients' => $clients,
            'services' => $services,
            'projects' => $projects,
            'tasks' => $tasks,
            'obligations' => $obligations,
            'incidents' => $incidents,
            'attention' => $attention,
            'finances' => $finances,
            'counts' => [
                'clients' => $clients->count(),
                'services' => $services->count(),
                'projects' => $projects->count(),
                'tasks' => $tasks->count(),
                'obligations' => $obligations->count(),
                'incidents' => $incidents->count(),
            ],
        ]);
    }

    private function financeByCurrency(
        Collection $services,
        Collection $projects,
        Collection $obligations,
        CarbonImmutable $now,
    ): Collection {
        $currencies = collect()
            ->concat($services->pluck('currency'))
            ->concat($projects->pluck('currency'))
            ->concat($obligations->pluck('currency'))
            ->filter(
                fn ($currency): bool =>
                    is_string($currency)
                    && trim($currency) !== '',
            )
            ->map(
                fn (string $currency): string =>
                    strtoupper(trim($currency)),
            )
            ->unique()
            ->sort()
            ->values();

        return $currencies->mapWithKeys(
            function (string $currency) use (
                $services,
                $projects,
                $obligations,
                $now,
            ): array {
                $serviceRows = $services->filter(
                    fn (ServiceOrder $order): bool =>
                        strtoupper(
                            (string) $order->currency,
                        ) === $currency,
                );

                $openServiceRows = $serviceRows->filter(
                    fn (ServiceOrder $order): bool =>
                        ! in_array(
                            $order->stage,
                            ['paid', 'closed', 'cancelled'],
                            true,
                        ),
                );

                $invoiced = $serviceRows->filter(
                    fn (ServiceOrder $order): bool =>
                        filled($order->invoice_number)
                        || $order->invoice_date !== null
                        || (float) (
                            $order->invoice_amount ?? 0
                        ) > 0,
                );

                $receivable = $invoiced->filter(
                    fn (ServiceOrder $order): bool =>
                        $order->paid_date === null,
                );

                $overdueReceivable = $receivable->filter(
                    fn (ServiceOrder $order): bool =>
                        $order->invoice_due_date
                        && $order->invoice_due_date
                            ->isBefore($now->startOfDay()),
                );

                $projectRows = $projects->filter(
                    fn (Project $project): bool =>
                        strtoupper(
                            (string) $project->currency,
                        ) === $currency,
                );

                $obligationRows = $obligations->filter(
                    fn (ObligationOccurrence $item): bool =>
                        strtoupper(
                            (string) $item->currency,
                        ) === $currency,
                );

                $overdueObligations = $obligationRows->filter(
                    fn (ObligationOccurrence $item): bool =>
                        $item->due_date->isBefore(
                            $now->startOfDay(),
                        ),
                );

                return [
                    $currency => [
                        'service_open' =>
                            $openServiceRows->sum(
                                fn (ServiceOrder $order): float =>
                                    (float) (
                                        $order->amount ?? 0
                                    ),
                            ),
                        'invoiced' => $invoiced->sum(
                            fn (ServiceOrder $order): float =>
                                (float) (
                                    $order->invoice_amount
                                    ?? $order->amount
                                    ?? 0
                                ),
                        ),
                        'receivable' => $receivable->sum(
                            fn (ServiceOrder $order): float =>
                                (float) (
                                    $order->invoice_amount
                                    ?? $order->amount
                                    ?? 0
                                ),
                        ),
                        'receivable_overdue' =>
                            $overdueReceivable->sum(
                                fn (ServiceOrder $order): float =>
                                    (float) (
                                        $order->invoice_amount
                                        ?? $order->amount
                                        ?? 0
                                    ),
                            ),
                        'project_budget' =>
                            $projectRows->sum(
                                fn (Project $project): float =>
                                    (float) (
                                        $project->budget ?? 0
                                    ),
                            ),
                        'obligation_pending' =>
                            $obligationRows->sum(
                                fn (ObligationOccurrence $item): float =>
                                    (float) (
                                        $item->expected_amount
                                        ?? 0
                                    ),
                            ),
                        'obligation_overdue' =>
                            $overdueObligations->sum(
                                fn (ObligationOccurrence $item): float =>
                                    (float) (
                                        $item->expected_amount
                                        ?? 0
                                    ),
                            ),
                    ],
                ];
            },
        );
    }
}
