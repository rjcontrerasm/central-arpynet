<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\ExecutiveDecisionAdvisor;
use App\Support\ExecutiveSummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DecisionInboxController extends Controller
{
    public function index(Request $request, ExecutiveSummaryBuilder $builder): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:all,task,project,service,obligation'],
        ]);

        $organizationIds = DB::table('organization_user')
            ->where('user_id', $request->user()->id)
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

        if ($selectedScope && ! $organizationIds->contains($selectedScope)) {
            abort(403);
        }

        $type = $validated['type'] ?? 'all';
        $now = CarbonImmutable::now(config('app.timezone', 'America/Lima'));

        $summary = $builder->build(
            $organizationIds,
            $selectedScope,
            'today',
            $now,
        );

        $decisions = collect($summary['attention_all'] ?? [])
            ->filter(
                fn (array $item): bool =>
                    ExecutiveDecisionAdvisor::isDecision($item),
            )
            ->map(function (array $item): array {
                $advice = ExecutiveDecisionAdvisor::recommend($item);

                return $item + [
                    'recommended_action' => $advice['action'],
                    'decision_reason' => $advice['reason'],
                ];
            })
            ->when(
                $type !== 'all',
                fn ($items) => $items->where('type', $type),
            )
            ->sortByDesc('rank')
            ->values();

        $counts = [
            'total' => $decisions->count(),
            'critical' => $decisions->where('level', 'critical')->count(),
            'no_next_action' => $decisions->where('no_next_action', true)->count(),
            'stagnant' => $decisions->where('stagnant', true)->count(),
        ];

        return view('decision-inbox', compact(
            'now',
            'organizations',
            'selectedScope',
            'type',
            'decisions',
            'counts',
        ));
    }
}
