<?php

namespace App\Http\Controllers;

use App\Models\AgentActionProposal;
use App\Models\DailyReviewSession;
use App\Support\CentralAgentGateway;
use App\Support\CentralCopilot;
use App\Support\JarvisDailyPlan;
use App\Support\JarvisDailyReviewAssistant;
use App\Support\JarvisExecutivePrioritization;
use App\Support\JarvisOperationalIntelligence;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CentralCopilotController extends Controller
{
    public function index(
        Request $request,
        CentralAgentGateway $gateway,
        JarvisOperationalIntelligence $intelligence,
        JarvisExecutivePrioritization $executivePrioritizationService,
        JarvisDailyPlan $dailyPlanService,
        JarvisDailyReviewAssistant $dailyReviewAssistantService,
        CentralCopilot $copilot,
    ): View {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $organizationIds = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        $selectedScope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if ($selectedScope && ! $organizationIds->contains($selectedScope)) {
            abort(403);
        }

        $organizations = $user->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->orderBy('organizations.name')
            ->get([
                'organizations.id',
                'organizations.name',
            ]);

        $focusOrganization = $selectedScope
            ? $organizations->firstWhere('id', $selectedScope)
            : null;

        if (! $focusOrganization) {
            $currentId = (int) ($user->current_organization_id ?? 0);

            if ($currentId > 0) {
                $focusOrganization = $organizations->firstWhere('id', $currentId);
            }
        }

        $focusOrganization ??= $organizations->first();

        $executiveContexts = $organizations
            ->map(
                fn ($organization): array => $gateway->organizationContext(
                    $user,
                    $organization,
                ),
            )
            ->all();

        $operationalContext = $focusOrganization
            ? collect($executiveContexts)->first(
                fn (array $context): bool =>
                    (int) $context['id'] === (int) $focusOrganization->id,
            )
            : null;

        $operationalIntelligence = $intelligence->analyze($operationalContext);

        $executivePrioritization = $executivePrioritizationService->analyze(
            $executiveContexts,
        );

        $dailyPlan = $dailyPlanService->build($executivePrioritization);

        $now = CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $dailyReviewSession = DailyReviewSession::query()
            ->where('user_id', $user->id)
            ->whereDate('review_date', $now->toDateString())
            ->first();

        $dailyReviewAssistant = $dailyReviewAssistantService->build(
            $executivePrioritization,
            $dailyPlan,
            $dailyReviewSession,
            $now,
        );

        $proposalQuery = AgentActionProposal::query()
            ->visibleTo($user)
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            );

        $proposalCounts = (clone $proposalQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $proposalSummary = [
            'pending' => (int) ($proposalCounts['pending'] ?? 0),
            'approved' => (int) ($proposalCounts['approved'] ?? 0),
            'executed' => (int) ($proposalCounts['executed'] ?? 0),
            'stale' => (int) ($proposalCounts['stale'] ?? 0),
            'rejected' => (int) ($proposalCounts['rejected'] ?? 0),
        ];

        $answer = $copilot->answer(
            $validated['q'] ?? null,
            $operationalContext,
            $operationalIntelligence,
            $executivePrioritization,
            $dailyPlan,
            $dailyReviewAssistant,
            $proposalSummary,
            $focusOrganization?->name,
        );

        return view('central-copilot', [
            'organizations' => $organizations,
            'selectedScope' => $selectedScope,
            'focusOrganization' => $focusOrganization,
            'answer' => $answer,
            'query' => trim((string) ($validated['q'] ?? '')),
            'proposalSummary' => $proposalSummary,
        ]);
    }
}
