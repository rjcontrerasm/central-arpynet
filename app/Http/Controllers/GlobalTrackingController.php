<?php

namespace App\Http\Controllers;

use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Support\GlobalTrackingItemFactory;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GlobalTrackingController extends Controller
{
    public function show(Request $request): View
    {
        $validated = $request->validate([
            'scope' => [
                'nullable',
                'integer',
            ],
            'type' => [
                'nullable',
                'in:all,task,project,service,obligation',
            ],
            'focus' => [
                'nullable',
                'in:attention,all',
            ],
            'q' => [
                'nullable',
                'string',
                'max:120',
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

        $type = $validated['type']
            ?? 'all';

        $focus = $validated['focus']
            ?? 'attention';

        $search = trim(
            (string) ($validated['q'] ?? ''),
        );

        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $items = collect();

        if (in_array($type, ['all', 'task'], true)) {
            $items = $items->concat(
                $this->taskItems(
                    $organizationIds,
                    $selectedScope,
                    $search,
                    $now,
                ),
            );
        }

        if (in_array($type, ['all', 'project'], true)) {
            $items = $items->concat(
                $this->projectItems(
                    $organizationIds,
                    $selectedScope,
                    $search,
                    $now,
                ),
            );
        }

        if (in_array($type, ['all', 'service'], true)) {
            $items = $items->concat(
                $this->serviceItems(
                    $organizationIds,
                    $selectedScope,
                    $search,
                    $now,
                ),
            );
        }

        if (in_array($type, ['all', 'obligation'], true)) {
            $items = $items->concat(
                $this->obligationItems(
                    $organizationIds,
                    $selectedScope,
                    $search,
                    $now,
                ),
            );
        }

        if ($focus === 'attention') {
            $items = $items->filter(
                fn (array $item): bool =>
                    GlobalTrackingItemFactory::needsAttention(
                        $item,
                    ),
            );
        }

        $items = $items
            ->sortByDesc('rank')
            ->values();

        $summary = [
            'critical' => $items
                ->where('level', 'critical')
                ->count(),
            'attention' => $items
                ->whereIn(
                    'level',
                    ['attention', 'watch'],
                )
                ->count(),
            'tasks' => $items
                ->where('type', 'task')
                ->count(),
            'projects' => $items
                ->where('type', 'project')
                ->count(),
            'services' => $items
                ->where('type', 'service')
                ->count(),
            'obligations' => $items
                ->where('type', 'obligation')
                ->count(),
        ];

        return view(
            'global-tracking',
            compact(
                'now',
                'items',
                'organizations',
                'selectedScope',
                'type',
                'focus',
                'search',
                'summary',
            ),
        );
    }

    private function taskItems(
        Collection $organizationIds,
        ?int $selectedScope,
        string $search,
        CarbonImmutable $now,
    ): Collection {
        $query = Task::query()
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
            $query->where(
                'organization_id',
                $selectedScope,
            );
        }

        if ($search !== '') {
            $query->where(
                'title',
                'like',
                '%'.$search.'%',
            );
        }

        return $query
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
        string $search,
        CarbonImmutable $now,
    ): Collection {
        $query = Project::query()
            ->with('organization')
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' =>
                    fn ($subQuery) =>
                        $subQuery->where(
                            'status',
                            'completed',
                        ),
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'status',
                ['completed', 'cancelled'],
            );

        if ($selectedScope) {
            $query->where(
                'organization_id',
                $selectedScope,
            );
        }

        if ($search !== '') {
            $query->where(
                'name',
                'like',
                '%'.$search.'%',
            );
        }

        return $query
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
        string $search,
        CarbonImmutable $now,
    ): Collection {
        $query = ServiceOrder::query()
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->whereNotIn(
                'stage',
                ['paid', 'closed', 'cancelled'],
            );

        if ($selectedScope) {
            $query->where(
                'organization_id',
                $selectedScope,
            );
        }

        if ($search !== '') {
            $query->where(
                function ($subQuery) use ($search): void {
                    $subQuery
                        ->where(
                            'title',
                            'like',
                            '%'.$search.'%',
                        )
                        ->orWhere(
                            'order_number',
                            'like',
                            '%'.$search.'%',
                        )
                        ->orWhere(
                            'quotation_number',
                            'like',
                            '%'.$search.'%',
                        );
                },
            );
        }

        return $query
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
        string $search,
        CarbonImmutable $now,
    ): Collection {
        $query = ObligationOccurrence::query()
            ->with([
                'organization',
                'obligation',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->where('status', 'pending');

        if ($selectedScope) {
            $query->where(
                'organization_id',
                $selectedScope,
            );
        }

        if ($search !== '') {
            $query->whereHas(
                'obligation',
                fn ($subQuery) =>
                    $subQuery->where(
                        'name',
                        'like',
                        '%'.$search.'%',
                    ),
            );
        }

        return $query
            ->limit(150)
            ->get()
            ->map(
                fn (ObligationOccurrence $occurrence): array =>
                    GlobalTrackingItemFactory::obligation(
                        $occurrence,
                        $now,
                    ),
            );
    }
}
