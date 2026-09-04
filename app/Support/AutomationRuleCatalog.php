<?php

namespace App\Support;

use InvalidArgumentException;

class AutomationRuleCatalog
{
    public const MODES = [
        'preview',
        'confirmation',
        'automatic',
    ];

    private const AUTOMATIC_INTERNAL_ACTIONS = [
        'service.create_billing_reminder',
        'service.create_collection_reminder',
        'obligation.create_alert',
    ];

    public function triggers(): array
    {
        return [
            'task.overdue' => [
                'label' => 'Tarea vencida',
                'subject' => 'task',
                'actions' => [
                    'task.raise_attention',
                ],
            ],
            'task.stagnant' => [
                'label' => 'Tarea estancada',
                'subject' => 'task',
                'actions' => [
                    'task.create_followup',
                ],
            ],
            'service.conformity_ready' => [
                'label' =>
                    'Servicio con conformidad listo para facturar',
                'subject' =>
                    'service_order',
                'actions' => [
                    'service.create_billing_reminder',
                ],
            ],
            'service.invoice_overdue' => [
                'label' =>
                    'Factura vencida',
                'subject' =>
                    'service_order',
                'actions' => [
                    'service.create_collection_reminder',
                ],
            ],
            'obligation.due_soon' => [
                'label' =>
                    'Vencimiento próximo',
                'subject' =>
                    'obligation_occurrence',
                'actions' => [
                    'obligation.create_alert',
                ],
            ],
            'waiting.followup_overdue' => [
                'label' =>
                    'Seguimiento en espera vencido',
                'subject' => 'task',
                'actions' => [
                    'waiting.return_to_daily',
                ],
            ],
        ];
    }

    public function actions(): array
    {
        return [
            'task.raise_attention' =>
                $this->buildAction(
                    'Elevar atención de tarea',
                    [
                        'preview',
                        'confirmation',
                    ],
                    false,
                    true,
                ),

            'task.create_followup' =>
                $this->buildAction(
                    'Crear seguimiento de tarea',
                    [
                        'preview',
                        'confirmation',
                    ],
                    false,
                    true,
                ),

            'service.create_billing_reminder' =>
                $this->buildAction(
                    'Crear recordatorio de facturación',
                    self::MODES,
                    true,
                    false,
                    'database_notification',
                ),

            'service.create_collection_reminder' =>
                $this->buildAction(
                    'Crear recordatorio de cobranza',
                    self::MODES,
                    true,
                    false,
                    'database_notification',
                ),

            'obligation.create_alert' =>
                $this->buildAction(
                    'Crear alerta interna de vencimiento',
                    self::MODES,
                    true,
                    false,
                    'database_notification',
                ),

            'waiting.return_to_daily' =>
                $this->buildAction(
                    'Proponer retorno a Mi día',
                    [
                        'preview',
                        'confirmation',
                    ],
                    false,
                    true,
                ),
        ];
    }

    public function validate(
        string $triggerKey,
        string $actionKey,
        string $mode,
    ): void {
        $trigger = $this->triggers()[
            $triggerKey
        ] ?? null;

        $action = $this->actions()[
            $actionKey
        ] ?? null;

        if (! $trigger) {
            throw new InvalidArgumentException(
                'Trigger de automatización no permitido.',
            );
        }

        if (! $action) {
            throw new InvalidArgumentException(
                'Acción de automatización no permitida.',
            );
        }

        if (! in_array(
            $actionKey,
            $trigger['actions'],
            true,
        )) {
            throw new InvalidArgumentException(
                'La acción no corresponde al trigger seleccionado.',
            );
        }

        if (! in_array(
            $mode,
            $action['allowed_modes'],
            true,
        )) {
            throw new InvalidArgumentException(
                'El modo solicitado no está permitido para esta acción.',
            );
        }
    }

    public function definition(
        string $actionKey,
    ): array {
        $action = $this->actions()[
            $actionKey
        ] ?? null;

        if (! $action) {
            throw new InvalidArgumentException(
                'Acción de automatización no permitida.',
            );
        }

        return $action;
    }

    public function isAutomaticInternal(
        string $actionKey,
    ): bool {
        return in_array(
            $actionKey,
            self::AUTOMATIC_INTERNAL_ACTIONS,
            true,
        );
    }

    public function contract(): array
    {
        return [
            'contract' =>
                'central-automation-contract-v2',
            'public_api' => false,
            'network_calls' => false,
            'external_channels' => false,
            'delete_actions' => false,
            'arbitrary_writes' => false,
            'bulk_execution' => false,
            'bounded_candidates_per_rule' =>
                100,
            'scheduler_enabled' => false,
            'execution_enabled' => true,
            'manual_execution_enabled' =>
                true,
            'automatic_execution_scope' =>
                'database_notifications_only',
            'subject_mutations_enabled' =>
                false,
            'preview_read_only' => true,
            'confirmation_execution_enabled' =>
                false,
            'modes' => self::MODES,
            'automatic_internal_actions' =>
                self::AUTOMATIC_INTERNAL_ACTIONS,
            'triggers' =>
                $this->triggers(),
            'actions' =>
                $this->actions(),
        ];
    }

    private function buildAction(
        string $label,
        array $modes,
        bool $executionSupported,
        bool $undoRequired,
        ?string $effect = null,
    ): array {
        return [
            'label' => $label,
            'allowed_modes' => $modes,
            'destructive' => false,
            'network_calls' => false,
            'external' => false,
            'undo_required' =>
                $undoRequired,
            'execution_supported' =>
                $executionSupported,
            'effect' => $effect,
        ];
    }
}
