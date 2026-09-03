<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CentralAgentGateway
{
    public function __construct(
        private readonly OperationalTaskActionService $actions,
    ) {
    }

    /**
     * Contract is intentionally read/preview only.
     * There is no execute method in this gateway.
     */
    public function contract(): array
    {
        return [
            'contract' => 'central-agent-contract-v1',
            'scope' => ['task'],
            'public_api' => false,
            'network_calls' => false,
            'write_execution' => false,
            'confirmation_required_for_future_writes' => true,
            'allowed_operations' => [
                'task.read',
                'task.action.preview',
            ],
            'blocked_operations' => [
                'task.action.execute',
                'task.delete',
                'task.bulk',
                'external.network',
            ],
            'action_catalog' => $this->actions->catalog(),
        ];
    }

    public function taskContext(
        User $actor,
        Task $task,
    ): array {
        $this->authorizeRead(
            $actor,
            $task,
        );

        return [
            'id' => $task->id,
            'organization_id' => $task->organization_id,
            'title' => $task->title,
            'status' => $task->status,
            'urgency' => $task->urgency,
            'impact' => $task->impact,
            'priority_score' => $task->priority_score,
            'priority_band' => $task->priority_band,
            'due_at' => $task->due_at?->toIso8601String(),
            'next_action' => $task->next_action,
            'waiting_until' => $task->waiting_until?->toIso8601String(),
            'source' => $task->source,
        ];
    }

    public function previewTaskAction(
        User $actor,
        Task $task,
        string $action,
    ): array {
        return $this->actions->preview(
            $actor,
            $task,
            $action,
        );
    }

    private function authorizeRead(
        User $actor,
        Task $task,
    ): void {
        $allowed = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $actor->id,
            )
            ->where(
                'organization_id',
                $task->organization_id,
            )
            ->where(
                'is_active',
                true,
            )
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para consultar esta tarea.',
            );
        }
    }
}
