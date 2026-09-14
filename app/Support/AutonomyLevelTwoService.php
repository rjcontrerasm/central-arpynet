<?php

namespace App\Support;

use App\Models\AgentActionProposal;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutonomyLevelTwoService
{
    public function __construct(
        private readonly DecisionEngine $engine,
        private readonly AutonomyLevelTwoPolicy $policy,
        private readonly CentralAgentGateway $gateway,
        private readonly CentralAgentProposalExecutor $executor,
    ) {
    }

    /**
     * Execute the only L2 autonomous subject mutation:
     * a high-confidence critical pending task may transition to in_progress.
     * The path still uses AgentActionProposal, stale/version checks, audit and
     * GlobalUndoService through CentralAgentProposalExecutor.
     */
    public function execute(
        User $actor,
        Task $task,
        AutomationRule $rule,
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        return DB::transaction(function () use (
            $actor,
            $task,
            $rule,
            $now,
        ): array {
            $locked = Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeActor(
                $actor,
                (int) $locked->organization_id,
            );

            $this->assertRuleContract(
                $actor,
                $locked,
                $rule,
            );

            $this->assertDailyLimit(
                (int) $locked->organization_id,
                $now,
            );

            $locked->loadMissing('organization');

            $item = GlobalTrackingItemFactory::task(
                $locked,
                $now,
            );

            $decision = collect(
                $this->engine->evaluate([$item])['decisions'] ?? [],
            )->first();

            if (! is_array($decision)) {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'La tarea ya no produce una decisión activa. CENTRAL no ejecutó ningún cambio.',
                ]);
            }

            $autonomy = $this->policy->evaluate(
                $decision,
                (string) $locked->status,
            );

            if (! $autonomy['eligible']) {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'La decisión ya no cumple la política de Autonomía L2: '
                        .implode(' ', $autonomy['reasons']),
                ]);
            }

            $proposal = $this->gateway->proposeTaskAction(
                $actor,
                $locked,
                'start',
                $this->rationale(
                    $decision,
                    $autonomy,
                    $rule,
                ),
            );

            $proposal->refresh();

            if ((int) $proposal->created_by !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'Existe una propuesta equivalente creada por otro actor. Autonomía L2 requiere revisión humana.',
                ]);
            }

            $this->assertProposalContract(
                $proposal,
                $locked,
            );

            if ($proposal->status === 'pending') {
                $proposal->forceFill([
                    'status' => 'approved',
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => $now,
                ])->save();

                AuditLog::query()->create([
                    'organization_id' =>
                        $proposal->organization_id,
                    'user_id' => $actor->id,
                    'event' =>
                        'agent_proposal.autonomy_approved',
                    'subject_type' =>
                        'agent_action_proposal',
                    'subject_id' => $proposal->id,
                    'subject_label' =>
                        $proposal->subject_title,
                    'source' =>
                        'central_autonomy_level_two',
                    'changes' => [
                        'status' => [
                            'before' => 'pending',
                            'after' => 'approved',
                        ],
                        'policy_version' =>
                            $autonomy['policy_version'],
                        'rule_id' => $rule->id,
                    ],
                    'occurred_at' => $now,
                ]);
            }

            if ($proposal->status !== 'approved') {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'La propuesta ya no está disponible para ejecución autónoma.',
                ]);
            }

            $result = $this->executor->execute(
                $actor,
                $proposal,
                true,
            );

            if (($result['stale'] ?? false) === true) {
                return [
                    'ok' => false,
                    'stale' => true,
                    'executed' => false,
                    'proposal' => $proposal->fresh(),
                    'decision' => $decision,
                    'autonomy' => $autonomy,
                    'message' => $result['message'],
                ];
            }

            $locked->refresh();
            $proposal->refresh();

            if ($locked->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'La ejecución L2 no produjo el estado permitido. La transacción fue cancelada.',
                ]);
            }

            AuditLog::query()->create([
                'organization_id' =>
                    $locked->organization_id,
                'user_id' => $actor->id,
                'event' => 'autonomy.level2.executed',
                'subject_type' => 'task',
                'subject_id' => $locked->id,
                'subject_label' => $locked->title,
                'source' =>
                    'central_autonomy_level_two',
                'changes' => [
                    'rule_id' => $rule->id,
                    'proposal_id' => $proposal->id,
                    'action' => 'start',
                    'status' => [
                        'before' => 'pending',
                        'after' => 'in_progress',
                    ],
                    'decision_score' =>
                        (int) ($decision['decision_score'] ?? 0),
                    'policy_version' =>
                        $autonomy['policy_version'],
                    'undo_action_id' =>
                        $result['undo_action_id'] ?? null,
                ],
                'occurred_at' => $now,
            ]);

            return [
                'ok' => true,
                'stale' => false,
                'executed' => true,
                'proposal' => $proposal,
                'decision' => $decision,
                'autonomy' => $autonomy,
                'undo_action_id' =>
                    $result['undo_action_id'] ?? null,
                'message' =>
                    'Autonomía L2 inició la tarea de forma reversible y auditada.',
            ];
        });
    }

    private function authorizeActor(
        User $actor,
        int $organizationId,
    ): void {
        if (
            ! $actor->is_active
            || ! $actor->canWriteToOrganization(
                $organizationId,
            )
        ) {
            throw new AuthorizationException(
                'Autonomía L2 exige que el propietario exacto de la regla siga activo y tenga permiso de escritura.',
            );
        }
    }

    private function assertRuleContract(
        User $actor,
        Task $task,
        AutomationRule $rule,
    ): void {
        $valid =
            $rule->is_active
            && $rule->mode === 'automatic'
            && $rule->trigger_key
                === 'decision.level2_task_start'
            && $rule->action_key
                === 'decision.execute_task_start'
            && (int) $rule->organization_id
                === (int) $task->organization_id
            && (int) $rule->created_by
                === (int) $actor->id;

        if (! $valid) {
            throw new AuthorizationException(
                'La regla no constituye un opt-in válido de Autonomía L2 para este actor y ámbito.',
            );
        }
    }

    private function assertDailyLimit(
        int $organizationId,
        CarbonImmutable $now,
    ): void {
        $used = AuditLog::query()
            ->where('organization_id', $organizationId)
            ->where('event', 'autonomy.level2.executed')
            ->where(
                'occurred_at',
                '>=',
                $now->startOfDay(),
            )
            ->count();

        if ($used >= AutonomyLevelTwoPolicy::DAILY_EXECUTION_LIMIT) {
            throw ValidationException::withMessages([
                'autonomy' =>
                    'Autonomía L2 alcanzó el límite diario de '
                    .AutonomyLevelTwoPolicy::DAILY_EXECUTION_LIMIT
                    .' ejecuciones para esta organización.',
            ]);
        }
    }

    private function assertProposalContract(
        AgentActionProposal $proposal,
        Task $task,
    ): void {
        $changes = $proposal->proposed_changes;
        $changeKeys = is_array($changes)
            ? array_keys($changes)
            : [];
        sort($changeKeys);

        $valid =
            $proposal->subject_type === 'task'
            && (int) $proposal->subject_id === (int) $task->id
            && (int) $proposal->organization_id
                === (int) $task->organization_id
            && $proposal->action_key === 'start'
            && $proposal->risk === 'state_change'
            && is_array($changes)
            && $changeKeys === ['completed_at', 'status']
            && array_key_exists('status', $changes)
            && array_key_exists('completed_at', $changes)
            && $changes['status'] === 'in_progress'
            && $changes['completed_at'] === null;

        if (! $valid) {
            throw ValidationException::withMessages([
                'autonomy' =>
                    'La propuesta no coincide con el contrato reversible permitido para Autonomía L2.',
            ]);
        }
    }

    private function rationale(
        array $decision,
        array $autonomy,
        AutomationRule $rule,
    ): string {
        return implode(' ', [
            'Autonomía L2 '.$autonomy['policy_version'].' autorizó únicamente la transición reversible pending → in_progress.',
            'Regla #'.$rule->id.' “'.$rule->name.'”.',
            'Decision Engine: score '.((int) ($decision['decision_score'] ?? 0)).'/100,',
            'banda '.((string) ($decision['decision_band_label'] ?? 'sin banda')).',',
            ((string) ($decision['evidence_quality_label'] ?? 'Evidencia limitada')).'.',
            'Motivo: '.((string) ($decision['why_now'] ?? 'señal crítica vigente')).'.',
            'No se permiten otras mutaciones, red externa ni creación cross-module en L2.',
        ]);
    }
}
