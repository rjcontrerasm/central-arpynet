<?php

namespace App\Support;

class AutonomyLevelTwoPolicy
{
    public const DAILY_EXECUTION_LIMIT = 3;

    public function __construct(
        private readonly AutonomyLevelOnePolicy $levelOne,
    ) {
    }

    /**
     * Level 2 is the first autonomy tier allowed to mutate an operational
     * subject. It is intentionally restricted to one reversible transition:
     * pending task -> in_progress. Everything else remains manual.
     */
    public function evaluate(
        array $decision,
        ?string $subjectStatus = null,
    ): array {
        $base = $this->levelOne->evaluate(
            $decision,
            $subjectStatus,
        );

        $score = (int) ($decision['decision_score'] ?? 0);
        $reasons = [];

        if (! $base['eligible']) {
            $reasons[] =
                'La decisión no cumple la política base de Autonomía L1.';
        }

        if ($subjectStatus !== 'pending') {
            $reasons[] =
                'Autonomía L2 solo puede iniciar tareas ya procesadas y pendientes.';
        }

        if ($score < 92) {
            $reasons[] =
                'Autonomía L2 exige score de decisión mínimo 92.';
        }

        if (($decision['evidence_quality'] ?? null) !== 'high') {
            $reasons[] =
                'Autonomía L2 exige evidencia alta.';
        }

        if (($decision['decision_band'] ?? null) !== 'immediate') {
            $reasons[] =
                'Autonomía L2 exige banda inmediata.';
        }

        if (($decision['level'] ?? null) !== 'critical') {
            $reasons[] =
                'Autonomía L2 exige una señal crítica.';
        }

        $eligible = $reasons === [];

        return [
            'eligible' => $eligible,
            'status' => $eligible
                ? 'autonomous_execution_allowed'
                : 'manual_only',
            'status_label' => $eligible
                ? 'Autonomía L2 disponible'
                : 'Revisión humana',
            'action' => $eligible ? 'start' : null,
            'reasons' => $reasons,
            'requires_rule_opt_in' => true,
            'requires_exact_rule_owner' => true,
            'auto_approval' => true,
            'auto_execution' => true,
            'undo_required' => true,
            'daily_execution_limit' =>
                self::DAILY_EXECUTION_LIMIT,
            'allowed_subject_types' => ['task'],
            'allowed_subject_statuses' => ['pending'],
            'allowed_actions' => ['start'],
            'external_network' => false,
            'deletes' => false,
            'bulk_execution' => false,
            'cross_module_creation' => false,
            'policy_version' => '2.32.1',
        ];
    }

    public function eligible(
        array $decision,
        ?string $subjectStatus = null,
    ): bool {
        return (bool) $this->evaluate(
            $decision,
            $subjectStatus,
        )['eligible'];
    }
}
