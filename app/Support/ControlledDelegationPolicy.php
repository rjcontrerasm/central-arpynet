<?php

namespace App\Support;

class ControlledDelegationPolicy
{
    /**
     * Determine which proposal-only actions may be delegated from a current
     * Decision Engine result. This policy never executes or approves writes.
     */
    public function evaluate(array $decision): array
    {
        $type = (string) ($decision['type'] ?? 'unknown');
        $codes = collect($decision['reason_codes'] ?? [])
            ->filter(fn (mixed $code): bool => is_string($code))
            ->values();

        $actions = match ($type) {
            'task' => $this->taskActions($decision, $codes->all()),
            'project' => $this->projectActions($codes->all()),
            'service', 'service_order' => $this->serviceActions($codes->all()),
            default => [],
        };

        $canDelegate = $actions !== [];

        return [
            'status' => $canDelegate ? 'delegable' : 'manual_only',
            'status_label' => $canDelegate
                ? 'Delegación disponible'
                : 'Decisión manual',
            'can_delegate' => $canDelegate,
            'actions' => $actions,
            'reason' => $canDelegate
                ? 'CENTRAL puede preparar una propuesta pendiente sin ejecutar cambios.'
                : $this->manualReason($type, $codes->all()),
            'requires_human_review' => true,
            'requires_execution_confirmation' => true,
            'autonomous_execution' => false,
            'policy_version' => '2.30.1',
        ];
    }

    public function allows(
        array $decision,
        string $action,
    ): bool {
        return collect(
            $this->evaluate($decision)['actions'],
        )->contains(
            fn (array $candidate): bool =>
                ($candidate['key'] ?? null) === $action,
        );
    }

    private function taskActions(
        array $decision,
        array $codes,
    ): array {
        $delegable =
            in_array('overdue', $codes, true)
            || in_array('critical', $codes, true)
            || in_array('stagnant', $codes, true)
            || ($decision['level'] ?? null) === 'critical';

        if (! $delegable) {
            return [];
        }

        return [
            [
                'key' => 'start',
                'label' => 'Preparar: En curso',
                'requires' => [],
            ],
            [
                'key' => 'today',
                'label' => 'Preparar: Hoy',
                'requires' => [],
            ],
            [
                'key' => 'tomorrow',
                'label' => 'Preparar: Mañana',
                'requires' => [],
            ],
            [
                'key' => 'next_week',
                'label' => 'Preparar: +1 semana',
                'requires' => [],
            ],
        ];
    }

    private function projectActions(array $codes): array
    {
        if (! in_array(
            'missing_next_action',
            $codes,
            true,
        )) {
            return [];
        }

        return [[
            'key' => 'project.next_action.set',
            'label' => 'Preparar siguiente acción',
            'requires' => ['next_action'],
        ]];
    }

    private function serviceActions(array $codes): array
    {
        if (! in_array(
            'missing_next_action',
            $codes,
            true,
        )) {
            return [];
        }

        return [[
            'key' => 'service_order.next_action.set',
            'label' => 'Preparar siguiente acción',
            'requires' => ['next_action'],
            'optional' => ['next_action_at'],
        ]];
    }

    private function manualReason(
        string $type,
        array $codes,
    ): string {
        if ($type === 'obligation') {
            return 'Los vencimientos permanecen bajo decisión humana en 2.30.';
        }

        if (in_array('collection_risk', $codes, true)) {
            return 'La cobranza requiere criterio humano; 2.30 no prepara una acción financiera automática.';
        }

        if (in_array('blocked', $codes, true)) {
            return 'Resolver o limpiar bloqueos requiere revisión humana del contexto.';
        }

        if (in_array('missing_next_action', $codes, true) && $type === 'task') {
            return 'El catálogo seguro actual no permite delegar la próxima acción de una tarea.';
        }

        return 'No existe una propuesta inequívoca y segura para delegar desde esta decisión.';
    }
}
