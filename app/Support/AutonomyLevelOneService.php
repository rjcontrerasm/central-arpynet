<?php

namespace App\Support;

use App\Models\AgentActionProposal;
use App\Models\AutomationRule;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutonomyLevelOneService
{
    public function __construct(
        private readonly DecisionEngine $engine,
        private readonly AutonomyLevelOnePolicy $policy,
        private readonly CentralAgentGateway $gateway,
    ) {
    }

    /**
     * Autonomously prepare/reuse one pending proposal.
     * The task itself is never mutated here.
     *
     * @return array{
     *     proposal: AgentActionProposal,
     *     created: bool,
     *     decision: array<string, mixed>,
     *     autonomy: array<string, mixed>,
     *     subject_unchanged: bool,
     *     approval_performed: bool,
     *     execution_performed: bool
     * }
     */
    public function prepare(
        User $actor,
        Task $task,
        ?AutomationRule $rule = null,
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

            if (
                $rule
                && (
                    (int) $rule->organization_id
                        !== (int) $locked->organization_id
                    || (int) $rule->created_by
                        !== (int) $actor->id
                )
            ) {
                throw new AuthorizationException(
                    'La regla de autonomía no corresponde al actor y ámbito autorizados.',
                );
            }

            $locked->loadMissing('organization');
            $before = $locked->getAttributes();

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
                        'La tarea ya no produce una decisión activa. CENTRAL no preparó ninguna propuesta.',
                ]);
            }

            $autonomy = $this->policy->evaluate(
                $decision,
                (string) $locked->status,
            );

            if (! $autonomy['eligible']) {
                throw ValidationException::withMessages([
                    'autonomy' =>
                        'La decisión ya no cumple la política de Autonomía L1: '
                        .implode(' ', $autonomy['reasons']),
                ]);
            }

            $proposal = $this->gateway->proposeTaskAction(
                $actor,
                $locked,
                (string) $autonomy['action'],
                $this->rationale(
                    $decision,
                    $autonomy,
                    $rule,
                ),
            );

            $locked->refresh();

            return [
                'proposal' => $proposal,
                'created' => $proposal->wasRecentlyCreated,
                'decision' => $decision,
                'autonomy' => $autonomy,
                'subject_unchanged' =>
                    $before === $locked->getAttributes(),
                'approval_performed' => false,
                'execution_performed' => false,
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
                'Autonomía L1 exige que el creador exacto de la regla siga activo y tenga permiso de escritura en la organización.',
            );
        }
    }

    private function rationale(
        array $decision,
        array $autonomy,
        ?AutomationRule $rule,
    ): string {
        $ruleLabel = $rule
            ? ' Regla #'.$rule->id.' “'.$rule->name.'”.'
            : '';

        return implode(' ', [
            'Autonomía L1 '.$autonomy['policy_version'].' preparó esta propuesta pendiente de forma determinística.'.$ruleLabel,
            'Decision Engine: score '.((int) ($decision['decision_score'] ?? 0)).'/100,',
            'banda '.((string) ($decision['decision_band_label'] ?? 'sin banda')).',',
            ((string) ($decision['evidence_quality_label'] ?? 'Evidencia limitada')).'.',
            'Motivo: '.((string) ($decision['why_now'] ?? 'señal operativa vigente')).'.',
            'No se aprobó ni ejecutó ningún cambio sobre la tarea.',
        ]);
    }
}
