<?php

namespace App\Support;

use App\Models\AgentActionProposal;
use App\Models\AutomationRuleRun;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Carbon\CarbonImmutable;

class SafetyRecoverySnapshot
{
    public function __construct(
        private readonly AutomationRuleCatalog $catalog,
        private readonly GlobalUndoService $undo,
    ) {
    }

    public function build(
        User $user,
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $organizationIds = $user->activeOrganizationIds();

        $runIssues = AutomationRuleRun::query()
            ->with(['rule', 'organization'])
            ->whereIn('organization_id', $organizationIds)
            ->whereIn('outcome', ['failed', 'blocked', 'stale'])
            ->latest('evaluated_at')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (AutomationRuleRun $run): array => [
                'id' => $run->id,
                'organization_id' => (int) $run->organization_id,
                'organization' => $run->organization?->name ?? 'Sin ámbito',
                'rule' => $run->rule?->name ?? 'Regla no disponible',
                'title' => trim((string) data_get(
                    $run->payload,
                    'title',
                    $run->subject_type.' #'.$run->subject_id,
                )),
                'outcome' => (string) $run->outcome,
                'outcome_label' => $this->runOutcomeLabel(
                    (string) $run->outcome,
                ),
                'evaluated_at' => $run->evaluated_at,
                'has_internal_error' => filled($run->error),
            ])
            ->values();

        $proposalIssues = AgentActionProposal::query()
            ->with('organization')
            ->visibleTo($user)
            ->whereIn('status', ['pending', 'stale'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (AgentActionProposal $proposal): array => [
                'id' => $proposal->id,
                'organization_id' => (int) $proposal->organization_id,
                'organization' => $proposal->organization?->name ?? 'Sin ámbito',
                'title' => $proposal->subject_title,
                'action' => $proposal->action_label,
                'status' => (string) $proposal->status,
                'status_label' => AgentActionProposal::statusOptions()[
                    $proposal->status
                ] ?? $proposal->status,
                'created_at' => $proposal->created_at,
            ])
            ->values();

        $calendar = GoogleCalendarConnection::query()
            ->where('user_id', $user->id)
            ->first();

        $calendarDegraded = $calendar
            && $calendar->last_error_at
            && (
                ! $calendar->last_sync_at
                || $calendar->last_error_at->greaterThanOrEqualTo(
                    $calendar->last_sync_at,
                )
            );

        $currentUndo = $this->undo->current($user);
        $contract = $this->catalog->contract();

        $counts = [
            'run_issues' => $runIssues->count(),
            'failed_runs' => $runIssues
                ->where('outcome', 'failed')
                ->count(),
            'blocked_runs' => $runIssues
                ->where('outcome', 'blocked')
                ->count(),
            'stale_runs' => $runIssues
                ->where('outcome', 'stale')
                ->count(),
            'proposal_issues' => $proposalIssues->count(),
            'pending_proposals' => $proposalIssues
                ->where('status', 'pending')
                ->count(),
            'stale_proposals' => $proposalIssues
                ->where('status', 'stale')
                ->count(),
        ];

        $status = match (true) {
            $counts['failed_runs'] > 0 || $calendarDegraded => 'attention',
            $counts['blocked_runs'] > 0
                || $counts['stale_runs'] > 0
                || $counts['stale_proposals'] > 0 => 'watch',
            default => 'healthy',
        };

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'attention' => 'Requiere atención',
                'watch' => 'Vigilar',
                default => 'Operación estable',
            },
            'generated_at' => $now,
            'counts' => $counts,
            'run_issues' => $runIssues,
            'proposal_issues' => $proposalIssues,
            'undo' => $currentUndo
                ? [
                    'id' => $currentUndo->id,
                    'label' => $currentUndo->label,
                    'entity_type' => $currentUndo->entity_type,
                    'expires_at' => $currentUndo->expires_at,
                ]
                : null,
            'calendar' => [
                'connected' => (bool) $calendar?->isConnected(),
                'degraded' => (bool) $calendarDegraded,
                'last_sync_at' => $calendar?->last_sync_at,
                'last_error_at' => $calendar?->last_error_at,
                'error_details_exposed' => false,
            ],
            'autonomy' => [
                'level_one' => (bool) (
                    $contract['autonomy_level_one_enabled'] ?? false
                ),
                'level_two' => (bool) (
                    $contract['autonomy_level_two_enabled'] ?? false
                ),
                'level_three' => (bool) (
                    $contract['autonomy_level_three_enabled'] ?? false
                ),
                'level_two_daily_limit' => (int) (
                    $contract[
                        'autonomous_subject_mutation_daily_limit'
                    ] ?? 0
                ),
                'level_three_daily_limit' => (int) (
                    $contract[
                        'bounded_autonomous_cross_module_daily_limit'
                    ] ?? 0
                ),
                'external_channels' => (bool) (
                    $contract['external_channels'] ?? false
                ),
                'network_calls' => (bool) (
                    $contract['network_calls'] ?? false
                ),
                'delete_actions' => (bool) (
                    $contract['delete_actions'] ?? false
                ),
                'bulk_execution' => (bool) (
                    $contract['bulk_execution'] ?? false
                ),
                'contract' => (string) (
                    $contract['contract'] ?? 'unknown'
                ),
            ],
            'scheduler' => [
                'automation_enabled' => (bool) (
                    $contract['scheduler_enabled'] ?? false
                ),
                'overlap_guard_expected' => true,
                'automatic_scope' => (string) (
                    $contract['automatic_execution_scope'] ?? 'unknown'
                ),
            ],
            'sensitive_details_exposed' => false,
        ];
    }

    private function runOutcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            'failed' => 'Fallida',
            'blocked' => 'Bloqueada',
            'stale' => 'Desactualizada',
            default => ucfirst($outcome),
        };
    }
}
