<?php

namespace App\Support;

use App\Models\AgentActionProposal;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\UndoAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CentralAgentProposalExecutor
{
    public function __construct(
        private readonly GlobalUndoService $undo,
    ) {
    }

    public function execute(
        User $actor,
        AgentActionProposal $proposal,
        bool $confirmed = false,
    ): array {
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_execution' =>
                    'La ejecución requiere una segunda confirmación humana explícita.',
            ]);
        }

        $this->authorize(
            $actor,
            (int) $proposal->organization_id,
        );

        return DB::transaction(
            function () use ($actor, $proposal): array {
                $locked = AgentActionProposal::query()
                    ->whereKey($proposal->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status !== 'approved') {
                    throw ValidationException::withMessages([
                        'proposal' =>
                            'Solo una propuesta aprobada puede ejecutarse.',
                    ]);
                }

                if ($locked->executed_at) {
                    throw ValidationException::withMessages([
                        'proposal' =>
                            'La propuesta ya fue ejecutada.',
                    ]);
                }

                $result = match ($locked->subject_type) {
                    'task' => $this->executeTask($actor, $locked),
                    'project' => $this->executeProject($actor, $locked),
                    'service_order' => $this->executeService($actor, $locked),
                    default => throw ValidationException::withMessages([
                        'proposal' =>
                            'El tipo de entidad no admite ejecución.',
                    ]),
                };

                if (($result['stale'] ?? false) === true) {
                    $this->markStale(
                        $actor,
                        $locked,
                        $result['message'],
                    );

                    return $result;
                }

                /** @var ?UndoAction $undoAction */
                $undoAction = $result['undo_action'] ?? null;

                $locked->forceFill([
                    'status' => 'executed',
                    'executed_by' => $actor->id,
                    'executed_at' => now(),
                    'execution_before' => $result['before'],
                    'execution_after' => $result['after'],
                    'undo_action_id' => $undoAction?->id,
                ])->save();

                AuditLog::query()->create([
                    'organization_id' => $locked->organization_id,
                    'user_id' => $actor->id,
                    'event' => 'agent_proposal.executed',
                    'subject_type' => 'agent_action_proposal',
                    'subject_id' => $locked->id,
                    'subject_label' => $locked->subject_title,
                    'source' => 'central_agent_execution',
                    'changes' => [
                        'proposal_id' => $locked->id,
                        'action_key' => $locked->action_key,
                        'entity_type' => $locked->subject_type,
                        'entity_id' => $locked->subject_id,
                        'before' => $result['before'],
                        'after' => $result['after'],
                        'undo_action_id' => $undoAction?->id,
                    ],
                    'occurred_at' => now(),
                ]);

                return [
                    'ok' => true,
                    'stale' => false,
                    'message' =>
                        'Propuesta ejecutada. Puedes deshacerla durante la ventana de seguridad.',
                    'undo_action_id' => $undoAction?->id,
                ];
            },
        );
    }

    private function executeTask(
        User $actor,
        AgentActionProposal $proposal,
    ): array {
        $task = Task::query()
            ->whereKey($proposal->subject_id)
            ->lockForUpdate()
            ->first();

        if (! $task) {
            return $this->stale(
                'La tarea ya no está disponible.',
            );
        }

        $this->assertScope($task, $proposal);

        if (! $this->versionMatches(
            $task,
            $proposal->subject_version,
        )) {
            return $this->stale(
                'La tarea cambió desde que Jarvis creó la propuesta. Genera una nueva propuesta antes de ejecutar.',
            );
        }

        $changes = $this->validatedTaskChanges(
            $proposal,
        );

        $before = $this->undo->captureTask($task);
        $task->forceFill($changes)->save();
        $task->refresh();
        $after = $this->undo->captureTask($task);

        $undoAction = $this->undo->rememberTaskMutation(
            $actor,
            $task,
            $before,
            'Jarvis: '.$proposal->action_label,
            route(
                'agent-proposals.index',
                ['status' => 'executed'],
                false,
            ),
        );

        return [
            'stale' => false,
            'before' => $before,
            'after' => $after,
            'undo_action' => $undoAction,
        ];
    }

    private function executeProject(
        User $actor,
        AgentActionProposal $proposal,
    ): array {
        $project = Project::query()
            ->whereKey($proposal->subject_id)
            ->lockForUpdate()
            ->first();

        if (! $project) {
            return $this->stale(
                'El proyecto ya no está disponible.',
            );
        }

        $this->assertScope($project, $proposal);

        if (! $this->versionMatches(
            $project,
            $proposal->subject_version,
        )) {
            return $this->stale(
                'El proyecto cambió desde que Jarvis creó la propuesta. Genera una nueva propuesta antes de ejecutar.',
            );
        }

        if (
            $proposal->action_key
            === 'project.task.create'
        ) {
            return $this->createProjectTask(
                $actor,
                $proposal,
                $project,
            );
        }

        $changes = $this->validatedProjectChanges(
            $proposal,
        );

        $before = $this->undo->captureProject(
            $project,
        );

        $project->forceFill($changes)->save();
        $project->refresh();

        $after = $this->undo->captureProject(
            $project,
        );

        $undoAction = $this->undo->rememberProjectMutation(
            $actor,
            $project,
            $before,
            'Jarvis: '.$proposal->action_label,
            route(
                'agent-proposals.index',
                ['status' => 'executed'],
                false,
            ),
        );

        return [
            'stale' => false,
            'before' => $before,
            'after' => $after,
            'undo_action' => $undoAction,
        ];
    }

    private function createProjectTask(
        User $actor,
        AgentActionProposal $proposal,
        Project $project,
    ): array {
        if (
            in_array(
                $project->status,
                ['completed', 'cancelled'],
                true,
            )
        ) {
            return $this->stale(
                'El proyecto ya no admite nuevas tareas.',
            );
        }

        $payload = $proposal->proposed_changes['task'] ?? null;

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La propuesta no contiene una tarea válida.',
            ]);
        }

        $this->assertExactKeys(
            $payload,
            [
                'organization_id',
                'project_id',
                'title',
                'status',
                'urgency',
                'impact',
                'due_at',
                'source',
            ],
        );

        if (
            (int) ($payload['organization_id'] ?? 0)
                !== (int) $project->organization_id
            || (int) ($payload['project_id'] ?? 0)
                !== (int) $project->id
        ) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La tarea propuesta no corresponde al proyecto.',
            ]);
        }

        $title = trim(
            (string) ($payload['title'] ?? ''),
        );

        if (
            $title === ''
            || mb_strlen($title) > 255
        ) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'El título de la tarea propuesta no es válido.',
            ]);
        }

        $urgency = trim(
            (string) ($payload['urgency'] ?? ''),
        );

        if (! in_array(
            $urgency,
            ['low', 'normal', 'high', 'critical'],
            true,
        )) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La urgencia de la tarea propuesta no es válida.',
            ]);
        }

        $dueAt = null;

        if (! empty($payload['due_at'])) {
            try {
                $dueAt = CarbonImmutable::parse(
                    (string) $payload['due_at'],
                    config(
                        'app.timezone',
                        'America/Lima',
                    ),
                );
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'proposal' =>
                        'La fecha de la tarea propuesta no es válida.',
                ]);
            }
        }

        $task = Task::query()->create([
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'title' => $title,
            'status' => 'pending',
            'urgency' => $urgency,
            'impact' => 'normal',
            'due_at' => $dueAt,
            'source' => 'central_agent',
            'assigned_to' => $actor->id,
            'created_by' => $actor->id,
        ]);

        $after = [
            'task' => $this->undo->captureTask(
                $task,
            ),
        ];

        $undoAction = $this->undo->rememberTaskCreated(
            $actor,
            $task,
            'Jarvis: tarea creada en proyecto',
            route(
                'agent-proposals.index',
                ['status' => 'executed'],
                false,
            ),
        );

        return [
            'stale' => false,
            'before' => ['task' => null],
            'after' => $after,
            'undo_action' => $undoAction,
        ];
    }

    private function executeService(
        User $actor,
        AgentActionProposal $proposal,
    ): array {
        $service = ServiceOrder::query()
            ->whereKey($proposal->subject_id)
            ->lockForUpdate()
            ->first();

        if (! $service) {
            return $this->stale(
                'El servicio ya no está disponible.',
            );
        }

        $this->assertScope($service, $proposal);

        if (! $this->versionMatches(
            $service,
            $proposal->subject_version,
        )) {
            return $this->stale(
                'El servicio cambió desde que Jarvis creó la propuesta. Genera una nueva propuesta antes de ejecutar.',
            );
        }

        $changes = $this->validatedServiceChanges(
            $proposal,
        );

        $before = $this->undo->captureServiceOrder(
            $service,
        );

        $service->forceFill($changes)->save();
        $service->refresh();

        $after = $this->undo->captureServiceOrder(
            $service,
        );

        $undoAction = $this->undo->rememberServiceOrderMutation(
            $actor,
            $service,
            $before,
            'Jarvis: '.$proposal->action_label,
            route(
                'agent-proposals.index',
                ['status' => 'executed'],
                false,
            ),
        );

        return [
            'stale' => false,
            'before' => $before,
            'after' => $after,
            'undo_action' => $undoAction,
        ];
    }

    private function validatedTaskChanges(
        AgentActionProposal $proposal,
    ): array {
        $changes = $proposal->proposed_changes;

        if (! is_array($changes)) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'Los cambios propuestos no son válidos.',
            ]);
        }

        if (! in_array(
            $proposal->action_key,
            ['complete', 'start', 'today', 'tomorrow', 'next_week'],
            true,
        )) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La acción de tarea no está permitida.',
            ]);
        }

        $this->assertAllowedKeys(
            $changes,
            ['status', 'completed_at', 'due_at'],
        );

        if (
            $proposal->action_key === 'complete'
            && ($changes['status'] ?? null)
                !== 'completed'
        ) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La propuesta de completar tarea no es coherente.',
            ]);
        }

        if (
            $proposal->action_key === 'start'
            && ($changes['status'] ?? null)
                !== 'in_progress'
        ) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La propuesta de iniciar tarea no es coherente.',
            ]);
        }

        if (
            isset($changes['status'])
            && ! in_array(
                $changes['status'],
                ['pending', 'in_progress', 'completed'],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'El estado propuesto para la tarea no es válido.',
            ]);
        }

        return $changes;
    }

    private function validatedProjectChanges(
        AgentActionProposal $proposal,
    ): array {
        $changes = $proposal->proposed_changes;

        if (! is_array($changes)) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'Los cambios propuestos no son válidos.',
            ]);
        }

        return match ($proposal->action_key) {
            'project.status.set' =>
                $this->validateProjectStatus($changes),
            'project.next_action.set' =>
                $this->validateProjectNextAction($changes),
            'project.blockers.clear' =>
                $this->validateProjectBlockers($changes),
            default => throw ValidationException::withMessages([
                'proposal' =>
                    'La acción de proyecto no está permitida.',
            ]),
        };
    }

    private function validateProjectStatus(
        array $changes,
    ): array {
        $this->assertExactKeys(
            $changes,
            ['status'],
        );

        if (! array_key_exists(
            (string) ($changes['status'] ?? ''),
            Project::statusOptions(),
        )) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'El estado propuesto del proyecto no es válido.',
            ]);
        }

        return $changes;
    }

    private function validateProjectNextAction(
        array $changes,
    ): array {
        $this->assertExactKeys(
            $changes,
            ['next_action'],
        );

        $value = trim(
            (string) ($changes['next_action'] ?? ''),
        );

        if (
            $value === ''
            || mb_strlen($value) > 255
        ) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La siguiente acción propuesta no es válida.',
            ]);
        }

        return ['next_action' => $value];
    }

    private function validateProjectBlockers(
        array $changes,
    ): array {
        $this->assertExactKeys(
            $changes,
            ['blockers'],
        );

        if (($changes['blockers'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La propuesta para limpiar bloqueos no es válida.',
            ]);
        }

        return ['blockers' => null];
    }

    private function validatedServiceChanges(
        AgentActionProposal $proposal,
    ): array {
        $changes = $proposal->proposed_changes;

        if (! is_array($changes)) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'Los cambios propuestos no son válidos.',
            ]);
        }

        if (
            $proposal->action_key
            === 'service_order.stage.set'
        ) {
            $this->assertExactKeys(
                $changes,
                ['stage'],
            );

            if (! array_key_exists(
                (string) ($changes['stage'] ?? ''),
                ServiceOrder::stageOptions(),
            )) {
                throw ValidationException::withMessages([
                    'proposal' =>
                        'La etapa propuesta del servicio no es válida.',
                ]);
            }

            return $changes;
        }

        if (
            $proposal->action_key
            === 'service_order.next_action.set'
        ) {
            $this->assertExactKeys(
                $changes,
                ['next_action', 'next_action_at'],
            );

            $nextAction = trim(
                (string) ($changes['next_action'] ?? ''),
            );

            if (
                $nextAction === ''
                || mb_strlen($nextAction) > 255
            ) {
                throw ValidationException::withMessages([
                    'proposal' =>
                        'La siguiente acción del servicio no es válida.',
                ]);
            }

            if (! empty($changes['next_action_at'])) {
                try {
                    CarbonImmutable::parse(
                        (string) $changes['next_action_at'],
                    );
                } catch (\Throwable) {
                    throw ValidationException::withMessages([
                        'proposal' =>
                            'La fecha de la siguiente acción no es válida.',
                    ]);
                }
            }

            return [
                'next_action' => $nextAction,
                'next_action_at' =>
                    $changes['next_action_at'] ?? null,
            ];
        }

        throw ValidationException::withMessages([
            'proposal' =>
                'La acción de servicio no está permitida.',
        ]);
    }

    private function versionMatches(
        Model $model,
        ?string $expected,
    ): bool {
        $expected = trim((string) $expected);

        if ($expected === '') {
            return false;
        }

        $attributes = $model->getAttributes();
        ksort($attributes);

        $current = hash(
            'sha256',
            json_encode(
                $attributes,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
        );

        return hash_equals(
            $expected,
            $current,
        );
    }

    private function assertScope(
        Model $model,
        AgentActionProposal $proposal,
    ): void {
        if (
            (int) $model->organization_id
            !== (int) $proposal->organization_id
        ) {
            throw new AuthorizationException(
                'La entidad ya no pertenece al ámbito autorizado.',
            );
        }
    }

    private function assertAllowedKeys(
        array $value,
        array $allowed,
    ): void {
        $extra = array_diff(
            array_keys($value),
            $allowed,
        );

        if ($extra !== []) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La propuesta contiene campos no permitidos.',
            ]);
        }
    }

    private function assertExactKeys(
        array $value,
        array $expected,
    ): void {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);

        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'proposal' =>
                    'La propuesta no coincide con el contrato de ejecución permitido.',
            ]);
        }
    }

    private function markStale(
        User $actor,
        AgentActionProposal $proposal,
        string $message,
    ): void {
        $proposal->forceFill([
            'status' => 'stale',
        ])->save();

        AuditLog::query()->create([
            'organization_id' => $proposal->organization_id,
            'user_id' => $actor->id,
            'event' => 'agent_proposal.stale',
            'subject_type' => 'agent_action_proposal',
            'subject_id' => $proposal->id,
            'subject_label' => $proposal->subject_title,
            'source' => 'central_agent_execution',
            'changes' => [
                'status' => [
                    'before' => 'approved',
                    'after' => 'stale',
                ],
                'reason' => $message,
            ],
            'occurred_at' => now(),
        ]);
    }

    private function stale(
        string $message,
    ): array {
        return [
            'ok' => false,
            'stale' => true,
            'message' => $message,
        ];
    }

    private function authorize(
        User $actor,
        int $organizationId,
    ): void {
        $allowed = DB::table(
            'organization_user',
        )
            ->where('user_id', $actor->id)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para ejecutar propuestas en este ámbito.',
            );
        }
    }
}
