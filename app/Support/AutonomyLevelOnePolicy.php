<?php

namespace App\Support;

class AutonomyLevelOnePolicy
{
    public function __construct(
        private readonly ControlledDelegationPolicy $delegation,
    ) {
    }

    /**
     * Level 1 is intentionally narrow: CENTRAL may autonomously prepare a
     * proposal, but it may not approve or execute any subject mutation.
     */
    public function evaluate(
        array $decision,
        ?string $subjectStatus = null,
    ): array {
        $type = (string) ($decision['type'] ?? 'unknown');
        $score = (int) ($decision['decision_score'] ?? 0);
        $level = (string) ($decision['level'] ?? 'normal');
        $band = (string) ($decision['decision_band'] ?? 'monitor');
        $evidence = (string) ($decision['evidence_quality'] ?? 'limited');

        $reasons = [];

        if ($type !== 'task') {
            $reasons[] = 'Autonomía L1 solo prepara propuestas para tareas.';
        }

        if (! in_array($subjectStatus, ['inbox', 'pending'], true)) {
            $reasons[] = 'La tarea debe estar en bandeja de entrada o pendiente.';
        }

        if ($level !== 'critical') {
            $reasons[] = 'La señal debe ser crítica.';
        }

        if ($band !== 'immediate' || $score < 85) {
            $reasons[] = 'La decisión debe estar en banda inmediata con score mínimo 85.';
        }

        if ($evidence !== 'high') {
            $reasons[] = 'Autonomía L1 exige evidencia alta.';
        }

        if (! $this->delegation->allows($decision, 'start')) {
            $reasons[] = 'La política de delegación controlada no permite preparar “En curso”.';
        }

        $eligible = $reasons === [];

        return [
            'eligible' => $eligible,
            'status' => $eligible ? 'eligible' : 'manual_only',
            'status_label' => $eligible
                ? 'Autonomía L1 disponible'
                : 'Revisión humana',
            'action' => $eligible ? 'start' : null,
            'reasons' => $reasons,
            'prepares_proposal_only' => true,
            'requires_rule_opt_in' => true,
            'auto_approval' => false,
            'auto_execution' => false,
            'external_network' => false,
            'policy_version' => '2.31.1',
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
