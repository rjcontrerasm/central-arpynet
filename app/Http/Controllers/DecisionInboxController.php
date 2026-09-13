<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\ControlledDelegationPolicy;
use App\Support\DecisionEngine;
use App\Support\ExecutiveSummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DecisionInboxController extends Controller
{
    public function index(
        Request $request,
        ExecutiveSummaryBuilder $builder,
        DecisionEngine $engine,
        ControlledDelegationPolicy $delegationPolicy,
    ): View {
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

        $candidates = collect($summary['attention_all'] ?? [])
            ->when(
                $type !== 'all',
                fn ($items) => $items->where('type', $type),
            )
            ->values();

        $decisionEngine = $engine->evaluate($candidates->all());
        $decisions = collect($decisionEngine['decisions'])
            ->map(
                fn (array $decision): array =>
                    $decision + [
                        'delegation' =>
                            $delegationPolicy->evaluate(
                                $decision,
                            ),
                    ],
            );

        $counts = [
            'total' => $decisions->count(),
            'immediate' => (int) ($decisionEngine['counts']['immediate'] ?? 0),
            'today' => (int) ($decisionEngine['counts']['today'] ?? 0),
            'high_evidence' => (int) ($decisionEngine['counts']['high_evidence'] ?? 0),
            'critical' => $decisions->where('level', 'critical')->count(),
            'no_next_action' => $decisions->where('no_next_action', true)->count(),
            'stagnant' => $decisions->where('stagnant', true)->count(),
        ];

        $decisionEngineSummary = (string) $decisionEngine['summary'];
        $decisionScoreVersion = (string) $decisionEngine['score_version'];

        return view('decision-inbox', compact(
            'now',
            'organizations',
            'selectedScope',
            'type',
            'decisions',
            'counts',
            'decisionEngineSummary',
            'decisionScoreVersion',
        ));
    }
}
