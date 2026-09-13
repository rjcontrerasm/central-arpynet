<?php

namespace App\Support;

use App\Models\AutomationRuleRun;
use App\Models\ObligationOccurrence;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomationConfirmationService
{
    public function __construct(
        private readonly GlobalUndoService $undo,
    ) {
    }

    public function confirm(
        User $actor,
        AutomationRuleRun $run,
        ?CarbonImmutable $now = null,
    ): array {
        $run->loadMissing('rule');

        if (! $run->rule) {
            throw ValidationException::withMessages([
                'automation' => 'La regla ya no está disponible.',
            ]);
        }

        $this->authorize(
            $actor,
            (int) $run->organization_id,
        );

        if ($run->outcome !== 'pending_confirmation') {
            throw ValidationException::withMessages([
                'automation' =>
                    'Esta ejecución ya fue atendida o no requiere confirmación.',
            ]);
        }

        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        return match ($run->rule->action_key) {
            'waiting.return_to_daily' =>
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

            default =>
                throw ValidationException::withMessages([
                    'automation' =>
                        'Esta acción aún no tiene ejecución confirmada segura.',
                ]),
        };
    }

    public function reject(
        User $actor,
        AutomationRuleRun $run,
        ?CarbonImmutable $now = null,
    ): array {
        $this->authorize(
            $actor,
            (int) $run->organization_id,
        );

        if ($run->outcome !== 'pending_confirmation') {
            throw ValidationException::withMessages([
                'automation' =>
                    'Esta ejecución ya fue atendida o no requiere confirmación.',
            ]);
        }

        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $payload = is_array($run->payload)
            ? $run->payload
            : [];

        $payload['confirmation'] = [
            'decision' => 'rejected',
            'actor_id' => $actor->id,
            'decided_at' => $now->toIso8601String(),
        ];

        $run->forceFill([
            'outcome' => 'rejected',
            'payload' => $payload,
            'executed_at' => null,
            'error' => null,
        ])->save();

        return [
            'ok' => true,
            'outcome' => 'rejected',
            'message' => 'Automatización rechazada.',
        ];
    }

    private function confirmWaitingReturn(
        User $actor,
        AutomationRuleRun $run,
        CarbonImmutable $now,
    ): array {
        if ($run->subject_type !== 'task') {
            throw ValidationException::withMessages([
                'automation' => 'El sujeto de la automatización no es válido.',
            ]);
        }

        $task = Task::query()->find(
            (int) $run->subject_id,
        );

        if (! $task) {
            throw ValidationException::withMessages([
                'automation' => 'La tarea ya no está disponible.',
            ]);
        }

        $this->authorize(
            $actor,
            (int) $task->organization_id,
        );

        $before = $this->undo->captureTask(
            $task,
        );

        DB::transaction(function () use (
            $actor,
            $run,
            $task,
            $before,
            $now,
        ): void {
            $task->forceFill([
                'status' => 'pending',
                'waiting_since' => null,
                'waiting_reason' => null,
                'waiting_until' => null,
                'completed_at' => null,
            ])->save();

            $payload = is_array($run->payload)
                ? $run->payload
                : [];

            $payload['confirmation'] = [
                'decision' => 'confirmed',
                'actor_id' => $actor->id,
                'decided_at' => $now->toIso8601String(),
            ];

            $run->forceFill([
                'outcome' => 'executed',
                'payload' => $payload,
                'executed_at' => $now,
                'error' => null,
            ])->save();
        });

        $this->undo->rememberTaskMutation(
            $actor,
            $task,
            $before,
            'Automatización: retorno de tarea a Mi día',
            route(
                'automation-center.index',
                [],
                false,
            ),
        );

        return [
            'ok' => true,
            'outcome' => 'executed',
            'message' =>
                'Automatización confirmada. La tarea volvió a pendientes.',
        ];
    }

    private function confirmCrossModuleTask(
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

    private function authorize(
        User $actor,
        int $organizationId,
    ): void {
        if (! $actor->canWriteToOrganization($organizationId)) {
            throw new AuthorizationException(
                'No autorizado para modificar esta organización.',
            );
        }
    }
}
