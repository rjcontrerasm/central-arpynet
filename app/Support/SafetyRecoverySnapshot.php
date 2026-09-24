<?php

namespace App\Support;

use App\Models\AgentActionProposal;
use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\ExternalMonitorSyncState;
use App\Models\GoogleCalendarConnection;
use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\RecurringTaskRule;
use App\Models\RecurringTaskRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SafetyRecoverySnapshot
{
    public function __construct(
        private readonly AutomationRuleCatalog $catalog,
        private readonly GlobalUndoService $undo,
        private readonly RecurringTaskGenerator $recurringTaskGenerator,
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

        $currentUndo = $this->undo->current($user);
        $contract = $this->catalog->contract();
        $schedulerHealth = $this->schedulerHealth($now);
        $recurringHealth = $this->recurringHealth(
            $user,
            $now,
        );
        $obligationHealth = $this->obligationHealth(
            $user,
            $now,
        );
        $calendarHealth = $this->calendarHealth(
            $calendar,
            $now,
        );
        $automationHealth = $this->automationHealth(
            $organizationIds->all(),
            $now,
        );
        $whatsappHealth = $this->whatsappHealth($now);
        $summaryHealth = $this->summaryHealth(
            $user,
            $now,
        );
        $externalMonitorHealth =
            $this->externalMonitorHealth(
                $organizationIds->all(),
                $now,
            );

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
            'recurring_active' => $recurringHealth['active_rules'],
            'recurring_missing' => $recurringHealth['missing_rules'],
            'obligation_active' => $obligationHealth['active_rules'],
            'obligation_missing' => $obligationHealth['missing_rules'],
            'automation_active' => $automationHealth['active_rules'],
            'automation_failed_24h' => $automationHealth['failed_24h'],
            'whatsapp_failed_24h' => $whatsappHealth['failed_24h'],
        ];

        $status = match (true) {
            $counts['failed_runs'] > 0
                || $schedulerHealth['status'] === 'attention'
                || $counts['recurring_missing'] > 0
                || $counts['obligation_missing'] > 0
                || $calendarHealth['status'] === 'attention'
                || $automationHealth['status'] === 'attention'
                || $whatsappHealth['status'] === 'attention'
                || $summaryHealth['status'] === 'attention'
                || $externalMonitorHealth['status'] === 'attention'
                => 'attention',
            $counts['blocked_runs'] > 0
                || $counts['stale_runs'] > 0
                || $counts['stale_proposals'] > 0
                || $schedulerHealth['status'] === 'watch'
                || $calendarHealth['status'] === 'watch'
                || $automationHealth['status'] === 'watch'
                || $whatsappHealth['status'] === 'watch'
                || $summaryHealth['status'] === 'watch'
                || $externalMonitorHealth['status'] === 'watch'
                => 'watch',
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
            'calendar' => $calendarHealth,
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
                ...$schedulerHealth,
            ],
            'recurring' => $recurringHealth,
            'obligations' => $obligationHealth,
            'automations' => $automationHealth,
            'whatsapp' => $whatsappHealth,
            'summaries' => $summaryHealth,
            'external_monitor' => $externalMonitorHealth,
            'sensitive_details_exposed' => false,
        ];
    }

    private function schedulerHealth(
        CarbonImmutable $now,
    ): array {
        $path = storage_path(
            'app/central/scheduler-heartbeat.json',
        );

        if (! is_file($path)) {
            return [
                'status' => 'attention',
                'status_label' => 'Heartbeat no registrado',
                'last_seen_at' => null,
                'age_minutes' => null,
            ];
        }

        $modifiedAt = filemtime($path);

        if ($modifiedAt === false) {
            return [
                'status' => 'attention',
                'status_label' => 'Heartbeat no legible',
                'last_seen_at' => null,
                'age_minutes' => null,
            ];
        }

        $lastSeenAt = CarbonImmutable::createFromTimestamp(
            $modifiedAt,
            config('app.timezone', 'America/Lima'),
        );

        $ageMinutes = max(
            0,
            (int) floor(
                $lastSeenAt->diffInSeconds($now) / 60,
            ),
        );

        $status = match (true) {
            $ageMinutes <= 3 => 'healthy',
            $ageMinutes <= 10 => 'watch',
            default => 'attention',
        };

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'healthy' => 'Scheduler activo',
                'watch' => 'Scheduler con retraso',
                default => 'Scheduler sin señal reciente',
            },
            'last_seen_at' => $lastSeenAt,
            'age_minutes' => $ageMinutes,
        ];
    }

    private function recurringHealth(
        User $user,
        CarbonImmutable $now,
    ): array {
        $rules = RecurringTaskRule::query()
            ->with('organization')
            ->visibleTo($user)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return [
                'active_rules' => 0,
                'missing_rules' => 0,
                'issues' => collect(),
                'last_generated_at' => null,
            ];
        }

        $today = $now->startOfDay();
        $maximumHorizon = $today->addDays(90);
        $ruleIds = $rules->pluck('id')->all();

        $runs = RecurringTaskRun::query()
            ->whereIn('recurring_task_rule_id', $ruleIds)
            ->whereBetween(
                'scheduled_for',
                [$today, $maximumHorizon],
            )
            ->get()
            ->keyBy(
                fn (RecurringTaskRun $run): string =>
                    $run->recurring_task_rule_id
                    .'|'
                    .$run->scheduled_for?->format('Y-m-d'),
            );

        $lastGeneratedValue = RecurringTaskRun::query()
            ->whereIn('recurring_task_rule_id', $ruleIds)
            ->whereNotNull('generated_at')
            ->max('generated_at');

        $issues = collect();

        foreach ($rules as $rule) {
            if (! $rule->anchor_date) {
                continue;
            }

            $cursor = CarbonImmutable::parse(
                $rule->anchor_date->toDateString(),
                config('app.timezone', 'America/Lima'),
            )->startOfDay();

            $endDate = $rule->end_date
                ? CarbonImmutable::parse(
                    $rule->end_date->toDateString(),
                    config('app.timezone', 'America/Lima'),
                )->endOfDay()
                : null;

            if ($endDate && $endDate->lt($today)) {
                continue;
            }

            while ($cursor->lt($today)) {
                $cursor = $this->recurringTaskGenerator
                    ->nextScheduledDate($rule, $cursor);
            }

            $horizon = $today->addDays(
                max(
                    0,
                    min(
                        90,
                        (int) $rule->create_days_before,
                    ),
                ),
            );

            while ($cursor->lte($horizon)) {
                if ($endDate && $cursor->gt($endDate)) {
                    break;
                }

                $key = $rule->id.'|'.$cursor->format('Y-m-d');

                if (! $runs->has($key)) {
                    $issues->push([
                        'rule_id' => $rule->id,
                        'title' => $rule->title,
                        'organization' => $rule->organization?->name
                            ?? 'Sin ámbito',
                        'scheduled_for' => $cursor,
                    ]);

                    break;
                }

                $cursor = $this->recurringTaskGenerator
                    ->nextScheduledDate($rule, $cursor);
            }
        }

        return [
            'active_rules' => $rules->count(),
            'missing_rules' => $issues->count(),
            'issues' => $issues->take(20)->values(),
            'last_generated_at' => $lastGeneratedValue
                ? CarbonImmutable::parse(
                    $lastGeneratedValue,
                    config('app.timezone', 'America/Lima'),
                )
                : null,
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
