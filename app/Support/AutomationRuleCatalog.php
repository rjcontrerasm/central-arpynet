<?php

namespace App\Support;

use InvalidArgumentException;

class AutomationRuleCatalog
{
    public const MODES = ['preview', 'confirmation', 'automatic'];

    public function triggers(): array
    {
        return [
            'task.overdue' => [
                'label' => 'Tarea vencida',
                'subject' => 'task',
                'actions' => ['task.raise_attention'],
            ],
            'task.stagnant' => [
                'label' => 'Tarea estancada',
                'subject' => 'task',
                'actions' => ['task.create_followup'],
            ],
            'service.conformity_ready' => [
                'label' => 'Servicio con conformidad listo para facturar',
                'subject' => 'service_order',
                'actions' => ['service.create_billing_reminder'],
            ],
            'service.invoice_overdue' => [
                'label' => 'Factura vencida',
                'subject' => 'service_order',
                'actions' => ['service.create_collection_reminder'],
            ],
            'obligation.due_soon' => [
                'label' => 'Vencimiento próximo',
                'subject' => 'obligation_occurrence',
                'actions' => ['obligation.create_alert'],
            ],
            'waiting.followup_overdue' => [
                'label' => 'Seguimiento en espera vencido',
                'subject' => 'task',
                'actions' => ['waiting.return_to_daily'],
            ],
        ];
    }

    public function actions(): array
    {
        return [
            'task.raise_attention' => $this->action(
                'Elevar atención de tarea',
                ['preview', 'confirmation'],
            ),
            'task.create_followup' => $this->action(
                'Crear seguimiento de tarea',
                ['preview', 'confirmation'],
            ),
            'service.create_billing_reminder' => $this->action(
                'Crear recordatorio de facturación',
                self::MODES,
            ),
            'service.create_collection_reminder' => $this->action(
                'Crear recordatorio de cobranza',
                self::MODES,
            ),
            'obligation.create_alert' => $this->action(
                'Crear alerta interna de vencimiento',
                self::MODES,
            ),
            'waiting.return_to_daily' => $this->action(
                'Proponer retorno a Mi día',
                ['preview', 'confirmation'],
            ),
        ];
    }

    public function validate(string $triggerKey, string $actionKey, string $mode): void
    {
        $trigger = $this->triggers()[$triggerKey] ?? null;
        $action = $this->actions()[$actionKey] ?? null;

        if (! $trigger) {
            throw new InvalidArgumentException('Trigger de automatización no permitido.');
        }

        if (! $action) {
            throw new InvalidArgumentException('Acción de automatización no permitida.');
        }

        if (! in_array($actionKey, $trigger['actions'], true)) {
            throw new InvalidArgumentException('La acción no corresponde al trigger seleccionado.');
        }

        if (! in_array($mode, $action['allowed_modes'], true)) {
            throw new InvalidArgumentException('El modo solicitado no está permitido para esta acción.');
        }
    }

    public function contract(): array
    {
        return [
            'contract' => 'central-automation-contract-v1',
            'public_api' => false,
            'network_calls' => false,
            'delete_actions' => false,
            'bulk_execution' => false,
            'arbitrary_writes' => false,
            'scheduler_enabled' => false,
            'execution_enabled' => false,
            'preview_read_only' => true,
            'modes' => self::MODES,
            'triggers' => $this->triggers(),
            'actions' => $this->actions(),
        ];
    }

    private function action(string $label, array $modes): array
    {
        return [
            'label' => $label,
            'allowed_modes' => $modes,
            'destructive' => false,
            'network_calls' => false,
            'undo_required' => true,
            'execution_supported' => false,
        ];
    }
}
