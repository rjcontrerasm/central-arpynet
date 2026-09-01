<?php

namespace App\Http\Controllers;

use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Support\ObligationOperationalState;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ObligationOpsController extends Controller
{
    public function show(Request $request): View
    {
        $validated = $request->validate([
            'scope' => [
                'nullable',
                'integer',
            ],
            'focus' => [
                'nullable',
                'in:attention,all,overdue,today,upcoming,pending,paid,skipped',
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

        $focus = $validated['focus']
            ?? 'attention';

        $search = trim(
            (string) ($validated['q'] ?? ''),
        );

        $query = ObligationOccurrence::query()
            ->with([
                'organization',
                'obligation',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            );

        if ($selectedScope) {
            $query->where(
                'organization_id',
                $selectedScope,
            );
        }

        if ($search !== '') {
            $query->whereHas(
                'obligation',
                function ($subQuery) use ($search): void {
                    $subQuery->where(
                        'name',
                        'like',
                        '%'.$search.'%',
                    )
                        ->orWhere(
                            'provider',
                            'like',
                            '%'.$search.'%',
                        )
                        ->orWhere(
                            'reference',
                            'like',
                            '%'.$search.'%',
                        );
                },
            );
        }

        $occurrences = $query
            ->orderBy('due_date')
            ->get();

        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $occurrences->each(
            function (
                ObligationOccurrence $occurrence,
            ) use ($now): void {
                $state =
                    ObligationOperationalState::evaluate(
                        $occurrence,
                        $now,
                    );

                foreach ($state as $key => $value) {
                    $occurrence->setAttribute(
                        'ops_'.$key,
                        $value,
                    );
                }
            },
        );

        $occurrences = $this->applyFocus(
            $occurrences,
            $focus,
        )
            ->sortByDesc('ops_rank')
            ->values();

        $summary = [
            'overdue' => $occurrences
                ->where('ops_level', 'overdue')
                ->count(),
            'today' => $occurrences
                ->where('ops_level', 'today')
                ->count(),
            'upcoming' => $occurrences
                ->whereIn(
                    'ops_level',
                    ['critical', 'upcoming'],
                )
                ->count(),
            'pending' => $occurrences
                ->where('status', 'pending')
                ->count(),
            'total' => $occurrences->count(),
        ];

        $moneySummary = $occurrences
            ->groupBy('currency')
            ->map(
                function ($items): array {
                    $pending = $items->filter(
                        fn ($item): bool =>
                            $item->status === 'pending',
                    );

                    $paid = $items->filter(
                        fn ($item): bool =>
                            $item->status === 'paid',
                    );

                    $overdue = $items->filter(
                        fn ($item): bool =>
                            $item->ops_level === 'overdue',
                    );

                    return [
                        'expected' => $items->sum(
                            fn ($item): float =>
                                (float) (
                                    $item->expected_amount
                                    ?? 0
                                ),
                        ),
                        'pending' => $pending->sum(
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
                        'paid' => $paid->sum(
                            fn ($item): float =>
                                (float) (
                                    $item->actual_amount
                                    ?? $item->expected_amount
                                    ?? 0
                                ),
                        ),
                    ];
                },
            );

        return view(
            'obligations-ops',
            compact(
                'now',
                'occurrences',
                'organizations',
                'selectedScope',
                'focus',
                'search',
                'summary',
                'moneySummary',
            ),
        );
    }

    private function applyFocus(
        $occurrences,
        string $focus,
    ) {
        return match ($focus) {
            'all' => $occurrences,

            'overdue' => $occurrences->filter(
                fn ($item): bool =>
                    $item->ops_level === 'overdue',
            ),

            'today' => $occurrences->filter(
                fn ($item): bool =>
                    $item->ops_level === 'today',
            ),

            'upcoming' => $occurrences->filter(
                fn ($item): bool =>
                    in_array(
                        $item->ops_level,
                        [
                            'critical',
                            'upcoming',
                        ],
                        true,
                    ),
            ),

            'pending' => $occurrences->filter(
                fn ($item): bool =>
                    $item->status === 'pending',
            ),

            'paid' => $occurrences->filter(
                fn ($item): bool =>
                    $item->status === 'paid',
            ),

            'skipped' => $occurrences->filter(
                fn ($item): bool =>
                    $item->status === 'skipped',
            ),

            default => $occurrences->filter(
                fn ($item): bool =>
                    $item->status === 'pending'
                    && in_array(
                        $item->ops_level,
                        [
                            'overdue',
                            'today',
                            'critical',
                            'upcoming',
                        ],
                        true,
                    ),
            ),
        };
    }
}
