<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Support\ServiceOrderOperationalState;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceOrderOpsController extends Controller
{
    public function show(Request $request): View
    {
        $validated = $request->validate([
            'scope' => [
                'nullable',
                'integer',
            ],
            'stage' => [
                'nullable',
                'string',
                'max:40',
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

        $stageOptions = ServiceOrder::stageOptions();

        $selectedStage =
            $validated['stage'] ?? null;

        if (
            $selectedStage
            && ! array_key_exists(
                $selectedStage,
                $stageOptions,
            )
        ) {
            abort(422);
        }

        $focus = $validated['focus']
            ?? 'attention';

        $search = trim(
            (string) ($validated['q'] ?? ''),
        );

        $query = ServiceOrder::query()
            ->with([
                'organization',
                'client',
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

        if ($selectedStage) {
            $query->where(
                'stage',
                $selectedStage,
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

        $orders = $query
            ->latest('updated_at')
            ->get();

        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $orders->each(
            function (ServiceOrder $order) use ($now): void {
                $state = ServiceOrderOperationalState::evaluate(
                    $order,
                    $now,
                );

                foreach ($state as $key => $value) {
                    $order->setAttribute(
                        'ops_'.$key,
                        $value,
                    );
                }
            },
        );

        if ($focus === 'attention') {
            $orders = $orders
                ->filter(
                    fn (ServiceOrder $order): bool =>
                        in_array(
                            $order->ops_level,
                            [
                                'critical',
                                'attention',
                                'watch',
                            ],
                            true,
                        ),
                )
                ->values();
        }

        $orders = $orders
            ->sortByDesc('ops_rank')
            ->values();

        $summary = [
            'critical' => $orders
                ->where(
                    'ops_level',
                    'critical',
                )
                ->count(),
            'attention' => $orders
                ->whereIn(
                    'ops_level',
                    ['attention', 'watch'],
                )
                ->count(),
            'execution' => $orders
                ->where(
                    'stage',
                    'execution',
                )
                ->count(),
            'invoice' => $orders
                ->where(
                    'stage',
                    'invoiced',
                )
                ->count(),
            'total' => $orders->count(),
        ];

        return view(
            'service-orders-ops',
            compact(
                'now',
                'orders',
                'organizations',
                'stageOptions',
                'selectedScope',
                'selectedStage',
                'focus',
                'search',
                'summary',
            ),
        );
    }
}
