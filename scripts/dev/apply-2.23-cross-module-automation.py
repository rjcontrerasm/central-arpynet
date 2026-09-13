#!/usr/bin/env python3
from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: esperado 1 match, encontrados {count}")
    return text.replace(old, new, 1)


catalog_path = Path('app/Support/AutomationRuleCatalog.php')
confirmation_path = Path('app/Support/AutomationConfirmationService.php')
view_path = Path('resources/views/automation-center.blade.php')

catalog = catalog_path.read_text()

catalog = replace_once(
    catalog,
    """            'project.no_next_action' => [
                'label' => 'Proyecto sin siguiente acción',
                'subject' => 'project',
                'actions' => [
                    'project.create_alert',
                ],
            ],
""",
    """            'project.no_next_action' => [
                'label' => 'Proyecto sin siguiente acción',
                'subject' => 'project',
                'actions' => [
                    'project.create_alert',
                    'project.create_task',
                ],
            ],
""",
    'trigger project.no_next_action',
)

catalog = replace_once(
    catalog,
    """            'project.blocked' => [
                'label' => 'Proyecto con bloqueos',
                'subject' => 'project',
                'actions' => [
                    'project.create_alert',
                ],
            ],""",
    """            'project.blocked' => [
                'label' => 'Proyecto con bloqueos',
                'subject' => 'project',
                'actions' => [
                    'project.create_alert',
                    'project.create_task',
                ],
            ],""",
    'trigger project.blocked',
)

catalog = replace_once(
    catalog,
    """                'actions' => [
                    'service.create_billing_reminder',
                ],
            ],

            'service.invoice_overdue'""",
    """                'actions' => [
                    'service.create_billing_reminder',
                    'service.create_task',
                ],
            ],

            'service.invoice_overdue'""",
    'trigger service.conformity_ready',
)

catalog = replace_once(
    catalog,
    """                'actions' => [
                    'service.create_collection_reminder',
                ],
            ],

            'obligation.due_soon'""",
    """                'actions' => [
                    'service.create_collection_reminder',
                    'service.create_task',
                ],
            ],

            'obligation.due_soon'""",
    'trigger service.invoice_overdue',
)

catalog = replace_once(
    catalog,
    """                'actions' => [
                    'obligation.create_alert',
                ],
            ],

            'waiting.followup_overdue'""",
    """                'actions' => [
                    'obligation.create_alert',
                    'obligation.create_task',
                ],
            ],

            'waiting.followup_overdue'""",
    'trigger obligation.due_soon',
)

catalog = replace_once(
    catalog,
    """            'project.create_alert' =>
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
            'service.create_billing_reminder' =>""",
    """            'project.create_alert' =>
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

            'service.create_billing_reminder' =>""",
    'action project.create_task',
)

catalog = replace_once(
    catalog,
    """            'service.create_collection_reminder' =>
                $this->buildAction(
                    'Crear recordatorio de cobranza',
                    self::MODES,
                    true,
                    false,
                    'database_notification',
                ),

            'obligation.create_alert' =>""",
    """            'service.create_collection_reminder' =>
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

            'obligation.create_alert' =>""",
    'action service.create_task',
)

catalog = replace_once(
    catalog,
    """            'obligation.create_alert' =>
                $this->buildAction(
                    'Crear alerta interna de vencimiento',
                    self::MODES,
                    true,
                    false,
                    'database_notification',
                ),

            'waiting.return_to_daily' =>""",
    """            'obligation.create_alert' =>
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

            'waiting.return_to_daily' =>""",
    'action obligation.create_task',
)

catalog = replace_once(
    catalog,
    """            'confirmed_subject_mutations_enabled' =>
                true,
            'preview_read_only' => true,""",
    """            'confirmed_subject_mutations_enabled' =>
                true,
            'confirmed_cross_module_task_creation_enabled' =>
                true,
            'automatic_cross_module_task_creation_enabled' =>
                false,
            'preview_read_only' => true,""",
    'contract flags',
)

catalog_path.write_text(catalog)

confirmation = confirmation_path.read_text()

confirmation = replace_once(
    confirmation,
    """use App\\Models\\AutomationRuleRun;
use App\\Models\\Task;
use App\\Models\\User;""",
    """use App\\Models\\AutomationRuleRun;
use App\\Models\\ObligationOccurrence;
use App\\Models\\Project;
use App\\Models\\ServiceOrder;
use App\\Models\\Task;
use App\\Models\\User;""",
    'confirmation imports',
)

confirmation = replace_once(
    confirmation,
    """            'waiting.return_to_daily' =>
                $this->confirmWaitingReturn(
                    $actor,
                    $run,
                    $now,
                ),

            default =>""",
    """            'waiting.return_to_daily' =>
                $this->confirmWaitingReturn(
                    $actor,
                    $run,
                    $now,
                ),

            'project.create_task',
            'service.create_task',
            'obligation.create_task' =>
                $this->confirmCrossModuleTask(
                    $actor,
                    $run,
                    $now,
                ),

            default =>""",
    'confirmation dispatch',
)

marker = """    private function authorize(
        User $actor,
        int $organizationId,
    ): void {"""

methods = r'''    private function confirmCrossModuleTask(
        User $actor,
        AutomationRuleRun $run,
        CarbonImmutable $now,
    ): array {
        $actionKey = (string) $run->rule->action_key;

        $expectedSubject = match ($actionKey) {
            'project.create_task' => 'project',
            'service.create_task' => 'service_order',
            'obligation.create_task' => 'obligation_occurrence',
            default => null,
        };

        if (! $expectedSubject || $run->subject_type !== $expectedSubject) {
            throw ValidationException::withMessages([
                'automation' =>
                    'El sujeto no corresponde a la acción cross-module.',
            ]);
        }

        $source = match ($expectedSubject) {
            'project' => Project::query()->find((int) $run->subject_id),
            'service_order' => ServiceOrder::query()->find((int) $run->subject_id),
            'obligation_occurrence' => ObligationOccurrence::query()
                ->with('obligation')
                ->find((int) $run->subject_id),
        };

        if (! $source) {
            return $this->markCrossModuleStale(
                $actor,
                $run,
                $now,
                'El origen de la automatización ya no está disponible.',
            );
        }

        if (
            (int) $source->organization_id
            !== (int) $run->organization_id
        ) {
            throw ValidationException::withMessages([
                'automation' =>
                    'La automatización no puede crear tareas entre organizaciones.',
            ]);
        }

        $this->authorize(
            $actor,
            (int) $source->organization_id,
        );

        if (! $this->crossModuleConditionStillApplies($run, $source, $now)) {
            return $this->markCrossModuleStale(
                $actor,
                $run,
                $now,
                'La señal cambió antes de la confirmación. No se creó ninguna tarea.',
            );
        }

        $taskData = $this->crossModuleTaskPayload(
            $run,
            $source,
            $actor,
            $now,
        );

        $task = DB::transaction(function () use (
            $actor,
            $run,
            $now,
            $taskData,
        ): Task {
            $task = Task::query()->create($taskData);

            $payload = is_array($run->payload)
                ? $run->payload
                : [];

            $payload['confirmation'] = [
                'decision' => 'confirmed',
                'actor_id' => $actor->id,
                'decided_at' => $now->toIso8601String(),
            ];

            $payload['cross_module_task'] = [
                'task_id' => $task->id,
                'organization_id' => $task->organization_id,
                'project_id' => $task->project_id,
                'source' => $task->source,
                'external_system' => $task->external_system,
                'external_id' => $task->external_id,
            ];

            $run->forceFill([
                'outcome' => 'executed',
                'payload' => $payload,
                'executed_at' => $now,
                'error' => null,
            ])->save();

            return $task;
        });

        $undoAction = $this->undo->rememberTaskCreated(
            $actor,
            $task,
            'Automatización: tarea creada desde '
                .$run->subject_type,
            route(
                'automation-center.index',
                [],
                false,
            ),
        );

        if ($undoAction) {
            $payload = is_array($run->fresh()->payload)
                ? $run->fresh()->payload
                : [];

            $payload['cross_module_task']['undo_action_id'] =
                $undoAction->id;

            $run->forceFill([
                'payload' => $payload,
            ])->save();
        }

        return [
            'ok' => true,
            'outcome' => 'executed',
            'message' =>
                'Automatización confirmada. Se creó una tarea operativa y puede deshacerse durante la ventana de seguridad.',
            'task_id' => $task->id,
            'undo_action_id' => $undoAction?->id,
        ];
    }

    private function crossModuleConditionStillApplies(
        AutomationRuleRun $run,
        Project|ServiceOrder|ObligationOccurrence $source,
        CarbonImmutable $now,
    ): bool {
        return match ((string) $run->rule->trigger_key) {
            'project.no_next_action' =>
                $source instanceof Project
                && ! in_array($source->status, ['completed', 'cancelled'], true)
                && blank($source->next_action),

            'project.blocked' =>
                $source instanceof Project
                && ! in_array($source->status, ['completed', 'cancelled'], true)
                && filled($source->blockers),

            'service.conformity_ready' =>
                $source instanceof ServiceOrder
                && $source->stage === 'conformity'
                && blank($source->invoice_number),

            'service.invoice_overdue' =>
                $source instanceof ServiceOrder
                && ! $source->paid_date
                && $source->invoice_due_date
                && $source->invoice_due_date->startOfDay()->lt($now->startOfDay())
                && (
                    filled($source->invoice_number)
                    || $source->invoice_date
                    || (float) ($source->invoice_amount ?? 0) > 0
                ),

            'obligation.due_soon' =>
                $source instanceof ObligationOccurrence
                && $source->status === 'pending'
                && $source->due_date
                && ! $source->due_date->startOfDay()->lt($now->startOfDay())
                && ! $source->due_date->startOfDay()->gt(
                    $now->startOfDay()->addDays(
                        max(
                            0,
                            min(
                                30,
                                (int) (($run->rule->trigger_config ?? [])['days'] ?? 7),
                            ),
                        ),
                    ),
                ),

            default => false,
        };
    }

    private function crossModuleTaskPayload(
        AutomationRuleRun $run,
        Project|ServiceOrder|ObligationOccurrence $source,
        User $actor,
        CarbonImmutable $now,
    ): array {
        $triggerKey = (string) $run->rule->trigger_key;
        $payload = is_array($run->payload)
            ? $run->payload
            : [];

        $subjectTitle = trim((string) ($payload['title'] ?? ''));

        if ($source instanceof ObligationOccurrence) {
            $subjectTitle = $source->obligation?->name
                ?: ($subjectTitle ?: 'Vencimiento');
        }

        $title = match ($triggerKey) {
            'project.no_next_action' =>
                'Definir siguiente acción: '.$subjectTitle,
            'project.blocked' =>
                'Resolver bloqueo: '.$subjectTitle,
            'service.conformity_ready' =>
                'Facturar servicio: '.$subjectTitle,
            'service.invoice_overdue' =>
                'Cobrar factura vencida: '.$subjectTitle,
            'obligation.due_soon' =>
                'Atender vencimiento: '.$subjectTitle,
            default =>
                'Atender señal operativa: '.$subjectTitle,
        };

        $highPriority = in_array(
            $triggerKey,
            [
                'project.blocked',
                'service.conformity_ready',
                'service.invoice_overdue',
                'obligation.due_soon',
            ],
            true,
        );

        $dueAt = match ($triggerKey) {
            'service.invoice_overdue' => $now->endOfDay(),
            'obligation.due_soon' =>
                $source instanceof ObligationOccurrence
                    ? $source->due_date->endOfDay()
                    : $now->addDay()->setTime(17, 0),
            default => $now->addDay()->setTime(17, 0),
        };

        return [
            'organization_id' => (int) $run->organization_id,
            'project_id' => $source instanceof Project
                ? $source->id
                : null,
            'title' => mb_substr($title, 0, 255),
            'description' => mb_substr(
                'Automatización confirmada: '
                    .trim((string) ($payload['reason'] ?? 'Señal operativa detectada.')),
                0,
                2000,
            ),
            'status' => 'pending',
            'urgency' => $highPriority ? 'high' : 'normal',
            'impact' => $highPriority ? 'high' : 'normal',
            'due_at' => $dueAt,
            'source' => 'automation',
            'external_system' => 'automation:'.$run->subject_type,
            'external_id' => (string) $run->subject_id,
            'assigned_to' => $actor->id,
            'created_by' => $actor->id,
        ];
    }

    private function markCrossModuleStale(
        User $actor,
        AutomationRuleRun $run,
        CarbonImmutable $now,
        string $message,
    ): array {
        $payload = is_array($run->payload)
            ? $run->payload
            : [];

        $payload['confirmation'] = [
            'decision' => 'stale',
            'actor_id' => $actor->id,
            'decided_at' => $now->toIso8601String(),
        ];

        $run->forceFill([
            'outcome' => 'stale',
            'payload' => $payload,
            'executed_at' => null,
            'error' => null,
        ])->save();

        return [
            'ok' => false,
            'outcome' => 'stale',
            'message' => $message,
        ];
    }

'''

confirmation = replace_once(
    confirmation,
    marker,
    methods + marker,
    'cross-module methods insertion',
)

old_authorize = """        $allowed = DB::table('organization_user')
            ->where('user_id', $actor->id)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para esta automatización.',
            );
        }"""

new_authorize = """        if (! $actor->canWriteToOrganization($organizationId)) {
            throw new AuthorizationException(
                'No autorizado para modificar esta organización.',
            );
        }"""

confirmation = replace_once(
    confirmation,
    old_authorize,
    new_authorize,
    'write authorization',
)

confirmation_path.write_text(confirmation)

view = view_path.read_text()

view = replace_once(
    view,
    """                    @if($run->rule?->action_key === 'waiting.return_to_daily')
                        <form method=\"POST\" action=\"{{ route('automation-center.confirm',$run) }}\">""",
    """                    @if(in_array($run->rule?->action_key, [
                        'waiting.return_to_daily',
                        'project.create_task',
                        'service.create_task',
                        'obligation.create_task',
                    ], true))
                        <form method=\"POST\" action=\"{{ route('automation-center.confirm',$run) }}\">""",
    'confirmation UI actions',
)

view = replace_once(
    view,
    """                Las reglas nuevas nacen inactivas. El modo automático solo está permitido para notificaciones internas seguras de facturación, cobranza, vencimientos y proyectos.""",
    """                Las reglas nuevas nacen inactivas. El modo automático solo está permitido para notificaciones internas seguras. La creación de tareas entre módulos exige siempre confirmación humana.""",
    'automation safety copy',
)

view_path.write_text(view)

print('2.23 patch aplicado correctamente')
