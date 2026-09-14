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
        'project.create_alert',
        'decision.prepare_task_start_proposal',
        'decision.execute_task_start',
        'decision.create_collection_task',
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
            'project.stagnant' => [
                'label' => 'Proyecto sin movimiento',
                'subject' => 'project',
                'actions' => [
                    'project.create_alert',
                ],
            ],
            'project.target_due_soon' => [
                'label' => 'Fecha objetivo de proyecto próxima',
                'subject' => 'project',
                'actions' => [
                    'project.create_alert',
                ],
            ],
            'project.target_overdue' => [
                'label' => 'Fecha objetivo de proyecto vencida',
                'subject' => 'project',
                'actions' => [
                    'project.create_alert',
                ],
            ],
            'project.no_next_action' => [
                'label' => 'Proyecto sin siguiente acción',
                'subject' => 'project',
                'actions' => [
                    'project.create_alert',
                    'project.create_task',
                ],
            ],
            'project.blocked' => [
                'label' => 'Proyecto con bloqueos',
                'subject' => 'project',
                'actions' => [
                    'project.create_alert',
                    'project.create_task',
                ],
            ],
            'service.conformity_ready' => [
                'label' =>
                    'Servicio con conformidad listo para facturar',
                'subject' =>
                    'service_order',
                'actions' => [
                    'service.create_billing_reminder',
                    'service.create_task',
                ],
            ],

            'service.invoice_overdue' => [
                'label' =>
                    'Factura vencida',
                'subject' =>
                    'service_order',
                'actions' => [
                    'service.create_collection_reminder',
                    'service.create_task',
                ],
            ],

            'obligation.due_soon' => [
                'label' =>
                    'Vencimiento próximo',
                'subject' =>
                    'obligation_occurrence',
                'actions' => [
                    'obligation.create_alert',
                    'obligation.create_task',
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

            'decision.level1_task' => [
                'label' =>
                    'Autonomía L1: decisión crítica de tarea',
                'subject' => 'task',
                'actions' => [
                    'decision.prepare_task_start_proposal',
                ],
            ],

            'decision.level2_task_start' => [
                'label' =>
                    'Autonomía L2: iniciar tarea crítica pendiente',
                'subject' => 'task',
                'actions' => [
                    'decision.execute_task_start',
                ],
            ],

            'decision.level3_invoice_collection' => [
                'label' =>
                    'Autonomía L3: factura vencida sin siguiente acción',
                'subject' => 'service_order',
                'actions' => [
                    'decision.create_collection_task',
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

            'project.create_alert' =>
                $this->buildAction(
                    'Crear alerta interna de proyecto',
                    [
                        'preview',
                        'automatic',
                    ],
                    true,
                    false,
                    'database_notification',
                ),

            'project.create_task' =>
                $this->buildAction(
                    'Crear tarea operativa desde proyecto',
                    ['confirmation'],
                    true,
                    true,
                    'confirmed_cross_module_task_create',
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

            'service.create_task' =>
                $this->buildAction(
                    'Crear tarea operativa desde servicio',
                    ['confirmation'],
                    true,
                    true,
                    'confirmed_cross_module_task_create',
                ),

            'obligation.create_alert' =>
                $this->buildAction(
                    'Crear alerta interna de vencimiento',
                    self::MODES,
                    true,
                    false,
                    'database_notification',
                ),

            'obligation.create_task' =>
                $this->buildAction(
                    'Crear tarea operativa desde vencimiento',
                    ['confirmation'],
                    true,
                    true,
                    'confirmed_cross_module_task_create',
                ),

            'waiting.return_to_daily' =>
                $this->buildAction(
                    'Proponer retorno a Mi día',
                    [
                        'preview',
                        'confirmation',
                    ],
                    true,
                    true,
                    'confirmed_task_mutation',
                ),

            'decision.prepare_task_start_proposal' =>
                $this->buildAction(
                    'Autonomía L1: preparar propuesta “En curso”',
                    [
                        'preview',
                        'automatic',
                    ],
                    true,
                    false,
                    'pending_agent_proposal',
                ),

            'decision.execute_task_start' =>
                $this->buildAction(
                    'Autonomía L2: iniciar tarea crítica',
                    [
                        'preview',
                        'automatic',
                    ],
                    true,
                    true,
                    'bounded_reversible_task_start',
                ),

            'decision.create_collection_task' =>
                $this->buildAction(
                    'Autonomía L3: crear tarea interna de cobranza',
                    [
                        'preview',
                        'automatic',
                    ],
                    true,
                    true,
                    'bounded_reversible_cross_module_task_create',
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
                'central-automation-contract-v4',
            'public_api' => false,
            'network_calls' => false,
            'external_channels' => false,
            'delete_actions' => false,
            'arbitrary_writes' => false,
            'bulk_execution' => false,
            'bounded_candidates_per_rule' =>
                100,
            'scheduler_enabled' => true,
            'execution_enabled' => true,
            'manual_execution_enabled' =>
                true,
            'automatic_execution_scope' =>
                'internal_notifications_pending_proposals_bounded_task_start_and_bounded_collection_task_create',
            'automatic_pending_proposals_enabled' =>
                true,
            'autonomy_level_one_enabled' =>
                true,
            'autonomy_level_two_enabled' =>
                true,
            'autonomy_level_three_enabled' =>
                true,
            'bounded_autonomous_cross_module_task_creation_enabled' =>
                true,
            'bounded_autonomous_cross_module_scope' => [
                'service_invoice.collection_task_create',
            ],
            'bounded_autonomous_cross_module_daily_limit' =>
                AutonomyLevelThreePolicy::DAILY_EXECUTION_LIMIT,
            'autonomous_subject_mutations_enabled' =>
                true,
            'autonomous_subject_mutation_scope' => [
                'task.start',
            ],
            'autonomous_subject_mutation_daily_limit' =>
                AutonomyLevelTwoPolicy::DAILY_EXECUTION_LIMIT,
            'subject_mutations_enabled' =>
                false,
            'confirmed_subject_mutations_enabled' =>
                true,
            'confirmed_cross_module_task_creation_enabled' =>
                true,
            'automatic_cross_module_task_creation_enabled' =>
                false,
            'preview_read_only' => true,
            'confirmation_execution_enabled' =>
                true,
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
