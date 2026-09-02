<?php

namespace App\Support;

use App\Models\Task;
use Carbon\CarbonImmutable;

class TaskActionUndoSnapshot
{
    public const SESSION_KEY =
        'daily_action_undo';

    public function remember(
        Task $task,
        array $filters = [],
    ): void {
        session()->put(
            self::SESSION_KEY,
            [
                'task_id' => $task->id,
                'organization_id' =>
                    $task->organization_id,
                'expires_at' =>
                    CarbonImmutable::now()
                        ->addMinutes(10)
                        ->timestamp,
                'state' => [
                    'status' =>
                        $task->status,
                    'due_at' =>
                        $task->due_at
                            ?->toIso8601String(),
                    'waiting_since' =>
                        $task->waiting_since
                            ?->toIso8601String(),
                    'waiting_reason' =>
                        $task->waiting_reason,
                    'waiting_until' =>
                        $task->waiting_until
                            ?->toIso8601String(),
                    'completed_at' =>
                        $task->completed_at
                            ?->toIso8601String(),
                ],
                'filters' =>
                    array_filter(
                        [
                            'scope' =>
                                $filters['scope']
                                ?? null,
                            'q' =>
                                trim(
                                    (string) (
                                        $filters['q']
                                        ?? ''
                                    ),
                                )
                                ?: null,
                            'priority' =>
                                $filters[
                                    'priority'
                                ]
                                ?? null,
                        ],
                        fn (mixed $value): bool =>
                            $value !== null
                            && $value !== '',
                    ),
            ],
        );
    }

    public function pull(): ?array
    {
        $snapshot = session()->pull(
            self::SESSION_KEY,
        );

        return is_array($snapshot)
            ? $snapshot
            : null;
    }
}
