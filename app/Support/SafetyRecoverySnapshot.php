<?php

namespace App\Support;

use App\Models\AgentActionProposal;
use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\ExternalMonitorSyncState;
use App\Models\GoogleCalendarConnection;
use App\Models\ObligationOccurrence;
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
        private readonly DatabaseConnectionHealth $databaseConnectionHealth,
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
            $organizationIds,
            $now,
        );
        $whatsappHealth = $this->whatsappHealth($now);
        $summaryHealth = $this->summaryHealth(
            $user,
            $now,
        );
        $externalMonitorHealth =
            $this->externalMonitorHealth(
                $organizationIds,
                $now,
            );
        $databaseHealth =
            $this->databaseConnectionHealth->snapshot();

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
                || $databaseHealth['status'] === 'attention'
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
                || $databaseHealth['status'] === 'watch'
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
            'database' => $databaseHealth,
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

    private function calendarHealth(
        ?GoogleCalendarConnection $calendar,
        CarbonImmutable $now,
    ): array {
        $connected = (bool) $calendar?->isConnected();

        if (! $connected) {
            return [
                'connected' => false,
                'degraded' => false,
                'status' => 'healthy',
                'status_label' => 'Google Calendar no conectado',
                'last_sync_at' => null,
                'last_error_at' => $calendar?->last_error_at,
                'sync_age_minutes' => null,
                'error_details_exposed' => false,
            ];
        }

        $degraded = (bool) (
            $calendar->last_error_at
            && (
                ! $calendar->last_sync_at
                || $calendar->last_error_at
                    ->greaterThanOrEqualTo(
                        $calendar->last_sync_at,
                    )
            )
        );

        $ageMinutes = $calendar->last_sync_at
            ? max(
                0,
                (int) floor(
                    $calendar->last_sync_at
                        ->diffInSeconds($now) / 60,
                ),
            )
            : null;

        $status = match (true) {
            $degraded => 'attention',
            is_null($ageMinutes) => 'watch',
            $ageMinutes > 60 => 'attention',
            $ageMinutes > 30 => 'watch',
            default => 'healthy',
        };

        return [
            'connected' => true,
            'degraded' => $degraded,
            'status' => $status,
            'status_label' => match ($status) {
                'attention' => $degraded
                    ? 'Google Calendar requiere revisión'
                    : 'Google Calendar sin sincronización reciente',
                'watch' => 'Google Calendar con sincronización retrasada',
                default => 'Google Calendar sincronizado',
            },
            'last_sync_at' => $calendar->last_sync_at,
            'last_error_at' => $calendar->last_error_at,
            'sync_age_minutes' => $ageMinutes,
            'error_details_exposed' => false,
        ];
    }

    private function automationHealth(
        array $organizationIds,
        CarbonImmutable $now,
    ): array {
        $activeRules = AutomationRule::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('is_active', true)
            ->count();

        $recent = AutomationRuleRun::query()
            ->whereIn('organization_id', $organizationIds)
            ->where(
                'evaluated_at',
                '>=',
                $now->subDay(),
            );

        $runs24h = (clone $recent)->count();
        $failed24h = (clone $recent)
            ->where('outcome', 'failed')
            ->count();
        $blocked24h = (clone $recent)
            ->where('outcome', 'blocked')
            ->count();
        $stale24h = (clone $recent)
            ->where('outcome', 'stale')
            ->count();

        $lastEvaluated = AutomationRuleRun::query()
            ->whereIn('organization_id', $organizationIds)
            ->whereNotNull('evaluated_at')
            ->max('evaluated_at');

        $status = match (true) {
            $failed24h > 0 => 'attention',
            $blocked24h > 0 || $stale24h > 0 => 'watch',
            default => 'healthy',
        };

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'attention' => 'Automatizaciones con fallas recientes',
                'watch' => 'Automatizaciones con bloqueos o stale',
                default => 'Automatizaciones sin fallas recientes',
            },
            'active_rules' => $activeRules,
            'runs_24h' => $runs24h,
            'failed_24h' => $failed24h,
            'blocked_24h' => $blocked24h,
            'stale_24h' => $stale24h,
            'last_evaluated_at' => $lastEvaluated
                ? CarbonImmutable::parse(
                    $lastEvaluated,
                    config('app.timezone', 'America/Lima'),
                )
                : null,
        ];
    }

    private function whatsappHealth(
        CarbonImmutable $now,
    ): array {
        $enabled = (bool) config(
            'whatsapp.outbound_enabled',
            false,
        );

        if (! Schema::hasTable(
            'whatsapp_outbound_attempts',
        )) {
            return [
                'enabled' => $enabled,
                'status' => $enabled ? 'attention' : 'healthy',
                'status_label' => $enabled
                    ? 'Diagnóstico WhatsApp no disponible'
                    : 'WhatsApp saliente deshabilitado',
                'attempts_24h' => 0,
                'sent_24h' => 0,
                'failed_24h' => 0,
                'last_attempt_at' => null,
                'last_attempt_status' => null,
                'last_attempt_purpose' => null,
                'sensitive_details_exposed' => false,
            ];
        }

        $recent = DB::table(
            'whatsapp_outbound_attempts',
        )->where(
            'created_at',
            '>=',
            $now->subDay(),
        );

        $attempts = (clone $recent)->count();
        $sent = (clone $recent)
            ->where('status', 'sent')
            ->count();
        $failed = (clone $recent)
            ->where('status', 'failed')
            ->count();

        $latest = DB::table(
            'whatsapp_outbound_attempts',
        )
            ->orderByDesc('id')
            ->first();

        $status = match (true) {
            ! $enabled => 'healthy',
            $latest?->status === 'failed' => 'attention',
            $failed > 0 => 'watch',
            default => 'healthy',
        };

        return [
            'enabled' => $enabled,
            'status' => $status,
            'status_label' => match ($status) {
                'attention' => 'Último envío WhatsApp falló',
                'watch' => 'WhatsApp tuvo fallas en 24 h',
                default => $enabled
                    ? 'WhatsApp sin falla activa'
                    : 'WhatsApp saliente deshabilitado',
            },
            'attempts_24h' => $attempts,
            'sent_24h' => $sent,
            'failed_24h' => $failed,
            'last_attempt_at' => $latest?->created_at
                ? CarbonImmutable::parse(
                    $latest->created_at,
                    config('app.timezone', 'America/Lima'),
                )
                : null,
            'last_attempt_status' =>
                $latest?->status,
            'last_attempt_purpose' =>
                $latest?->purpose,
            'sensitive_details_exposed' => false,
        ];
    }

    private function summaryHealth(
        User $user,
        CarbonImmutable $now,
    ): array {
        $emailEnabled = (bool) config(
            'central.summary_mail.enabled',
            true,
        );
        $whatsappEnabled = (bool) config(
            'central.summary_whatsapp.enabled',
            false,
        );

        $email = $this->latestDelivery(
            'summary_email_deliveries',
            $user->id,
            $now,
        );
        $whatsapp = $this->latestDelivery(
            'summary_whatsapp_deliveries',
            $user->id,
            $now,
        );

        $failedActiveChannel = (
            $emailEnabled
            && ($email['status'] ?? null) === 'failed'
        ) || (
            $whatsappEnabled
            && ($whatsapp['status'] ?? null) === 'failed'
        );

        return [
            'status' => $failedActiveChannel
                ? 'attention'
                : 'healthy',
            'status_label' => $failedActiveChannel
                ? 'Resumen ejecutivo con entrega fallida'
                : 'Entregas de resumen sin falla activa',
            'email_enabled' => $emailEnabled,
            'whatsapp_enabled' => $whatsappEnabled,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'sensitive_details_exposed' => false,
        ];
    }

    private function latestDelivery(
        string $table,
        int $userId,
        CarbonImmutable $now,
    ): ?array {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $row = DB::table($table)
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        if (! $row) {
            return null;
        }

        $updatedAt = $row->updated_at
            ?? $row->sent_at
            ?? $row->created_at
            ?? null;

        return [
            'status' => $row->status ?? null,
            'period' => $row->period ?? null,
            'summary_date' => $row->summary_date ?? null,
            'updated_at' => $updatedAt
                ? CarbonImmutable::parse(
                    $updatedAt,
                    config('app.timezone', 'America/Lima'),
                )
                : null,
            'is_today' => isset($row->summary_date)
                && CarbonImmutable::parse(
                    $row->summary_date,
                    config('app.timezone', 'America/Lima'),
                )->isSameDay($now),
        ];
    }

    private function externalMonitorHealth(
        array $organizationIds,
        CarbonImmutable $now,
    ): array {
        $enabled = (bool) config(
            'casa_andina_monitor.enabled',
            false,
        );

        $slug = (string) config(
            'casa_andina_monitor.organization_slug',
            'casa-andina',
        );

        $visible = Organization::query()
            ->whereIn('id', $organizationIds)
            ->where(function ($query) use ($slug): void {
                $query
                    ->where('slug', $slug)
                    ->orWhere('name', 'Casa Andina');
            })
            ->exists();

        if (! $visible) {
            return [
                'visible' => false,
                'enabled' => $enabled,
                'status' => 'healthy',
                'status_label' => 'Monitor externo fuera del ámbito visible',
                'last_sync_at' => null,
                'last_success_at' => null,
                'last_error_at' => null,
                'age_minutes' => null,
                'last_item_count' => null,
                'error_details_exposed' => false,
            ];
        }

        if (! $enabled) {
            return [
                'visible' => true,
                'enabled' => false,
                'status' => 'healthy',
                'status_label' => 'Monitor Casa Andina deshabilitado',
                'last_sync_at' => null,
                'last_success_at' => null,
                'last_error_at' => null,
                'age_minutes' => null,
                'last_item_count' => null,
                'error_details_exposed' => false,
            ];
        }

        $state = ExternalMonitorSyncState::query()
            ->where(
                'provider',
                'casa-andina-monitor',
            )
            ->first();

        if (! $state) {
            return [
                'visible' => true,
                'enabled' => true,
                'status' => 'watch',
                'status_label' => 'Monitor Casa Andina sin sincronización registrada',
                'last_sync_at' => null,
                'last_success_at' => null,
                'last_error_at' => null,
                'age_minutes' => null,
                'last_item_count' => null,
                'error_details_exposed' => false,
            ];
        }

        $degraded = (
            $state->last_error_at
            && (
                ! $state->last_success_at
                || $state->last_error_at
                    ->greaterThanOrEqualTo(
                        $state->last_success_at,
                    )
            )
        );

        $ageMinutes = $state->last_success_at
            ? max(
                0,
                (int) floor(
                    $state->last_success_at
                        ->diffInSeconds($now) / 60,
                ),
            )
            : null;

        $status = match (true) {
            $degraded => 'attention',
            is_null($ageMinutes) => 'watch',
            $ageMinutes > 30 => 'attention',
            $ageMinutes > 15 => 'watch',
            default => 'healthy',
        };

        return [
            'visible' => true,
            'enabled' => true,
            'status' => $status,
            'status_label' => match ($status) {
                'attention' => $degraded
                    ? 'Monitor Casa Andina con error activo'
                    : 'Monitor Casa Andina sin señal reciente',
                'watch' => 'Monitor Casa Andina con retraso',
                default => 'Monitor Casa Andina sincronizado',
            },
            'last_sync_at' => $state->last_sync_at,
            'last_success_at' => $state->last_success_at,
            'last_error_at' => $state->last_error_at,
            'age_minutes' => $ageMinutes,
            'last_item_count' => $state->last_item_count,
            'error_details_exposed' => false,
        ];
    }

    private function obligationHealth(
        User $user,
        CarbonImmutable $now,
    ): array {
        $obligations = RecurringObligation::query()
            ->with('organization')
            ->visibleTo($user)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $today = $now->startOfDay();
        $horizon = $today->addDays(120);
        $issues = collect();

        foreach ($obligations as $obligation) {
            if (! $obligation->anchor_date) {
                continue;
            }

            $cursor = CarbonImmutable::parse(
                $obligation->anchor_date->toDateString(),
                config('app.timezone', 'America/Lima'),
            )->startOfDay();

            $interval = match ($obligation->frequency) {
                'bimonthly' => 2,
                'quarterly' => 3,
                'semiannual' => 6,
                'annual' => 12,
                default => 1,
            };

            while ($cursor->lt($today)) {
                $cursor = $cursor->addMonthsNoOverflow(
                    $interval,
                );
            }

            $endDate = $obligation->end_date
                ? CarbonImmutable::parse(
                    $obligation->end_date->toDateString(),
                    config('app.timezone', 'America/Lima'),
                )->endOfDay()
                : null;

            if (
                $cursor->gt($horizon)
                || ($endDate && $cursor->gt($endDate))
            ) {
                continue;
            }

            $exists = $obligation
                ->occurrences()
                ->whereDate(
                    'due_date',
                    $cursor->toDateString(),
                )
                ->exists();

            if (! $exists) {
                $issues->push([
                    'obligation_id' => $obligation->id,
                    'title' => $obligation->name,
                    'organization' =>
                        $obligation->organization?->name
                        ?? 'Sin ámbito',
                    'due_date' => $cursor,
                ]);
            }
        }

        $lastGenerated = ObligationOccurrence::query()
            ->visibleTo($user)
            ->max('created_at');

        return [
            'active_rules' => $obligations->count(),
            'missing_rules' => $issues->count(),
            'issues' => $issues->take(20)->values(),
            'last_generated_at' => $lastGenerated
                ? CarbonImmutable::parse(
                    $lastGenerated,
                    config('app.timezone', 'America/Lima'),
                )
                : null,
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
