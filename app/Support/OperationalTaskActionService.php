<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OperationalTaskActionService
{
    private const ACTIONS = [
        'complete' => [
            'label' => 'Marcar como hecha',
            'risk' => 'state_change',
        ],
        'start' => [
            'label' => 'Marcar en curso',
            'risk' => 'state_change',
        ],
        'today' => [
            'label' => 'Mover a hoy',
            'risk' => 'schedule_change',
        ],
        'tomorrow' => [
            'label' => 'Mover a mañana',
            'risk' => 'schedule_change',
        ],
        'next_week' => [
            'label' => 'Mover una semana',
            'risk' => 'schedule_change',
        ],
    ];

    /**
     * @return array<string, array{
     *     label: string,
     *     risk: string,
     *     confirmation_required: bool
     * }>
     */
    public function catalog(): array
    {
        return collect(self::ACTIONS)
            ->map(
                fn (array $definition): array =>
                    $definition + [
                        'confirmation_required' =>
                            true,
                    ],
            )
            ->all();
    }

    /**
     * Preview is intentionally read-only.
     *
     * @return array{
     *     action: string,
     *     label: string,
     *     risk: string,
     *     confirmation_required: bool,
     *     task_id: int,
     *     task_title: string,
     *     organization_id: int,
     *     changes: array<string, mixed>
     * }
     */
    public function preview(
        User $actor,
        Task $task,
        string $action,
        ?CarbonImmutable $now = null,
    ): array {
        $definition =
            $this->definition($action);

        $this->authorize(
            $actor,
            $task,
        );

        $now ??= CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        return [
            'action' => $action,
            'label' =>
                $definition['label'],
            'risk' =>
                $definition['risk'],
            'confirmation_required' =>
                true,
            'task_id' => $task->id,
            'task_title' => $task->title,
            'organization_id' =>
                $task->organization_id,
            'changes' =>
                $this->changesFor(
                    $task,
                    $action,
                    $now,
                ),
        ];
    }

    /**
     * The future agent path must opt in with
     * confirmed=true. Human UI buttons count as
     * explicit confirmation at the controller.
     *
     * @return array{
     *     action: string,
     *     label: string,
     *     task_id: int,
     *     changed: bool
     * }
     */
    public function execute(
        User $actor,
        Task $task,
        string $action,
        bool $confirmed = false,
        ?CarbonImmutable $now = null,
    ): array {
        $preview = $this->preview(
            $actor,
            $task,
            $action,
            $now,
        );

        if (! $confirmed) {
            throw ValidationException::
                withMessages([
                    'confirmation' =>
                        'Esta acción requiere confirmación explícita.',
                ]);
        }

        $before = $task->only(
            array_keys(
                $preview['changes'],
            ),
        );

        $task->forceFill(
            $preview['changes'],
        )->save();

        $task->refresh();

        $after = $task->only(
            array_keys(
                $preview['changes'],
            ),
        );

        return [
            'action' => $action,
            'label' =>
                $preview['label'],
            'task_id' => $task->id,
            'changed' =>
                $before !== $after,
        ];
    }

    private function definition(
        string $action,
    ): array {
        if (
            ! array_key_exists(
                $action,
                self::ACTIONS,
            )
        ) {
            throw new InvalidArgumentException(
                'Acción operativa no permitida.',
            );
        }

        return self::ACTIONS[$action];
    }

    private function authorize(
        User $actor,
        Task $task,
    ): void {
        $allowed =
            DB::table(
                'organization_user',
            )
                ->where(
                    'user_id',
                    $actor->id,
                )
                ->where(
                    'organization_id',
                    $task
                        ->organization_id,
                )
                ->where(
                    'is_active',
                    true,
                )
                ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para esta tarea.',
            );
        }
    }

    private function changesFor(
        Task $task,
        string $action,
        CarbonImmutable $now,
    ): array {
        return match ($action) {
            'complete' => [
                'status' => 'completed',
                'completed_at' => $now,
            ],

            'start' => [
                'status' => 'in_progress',
                'completed_at' => null,
            ],

            'today' =>
                $this->scheduleChanges(
                    $task,
                    $now->setTime(
                        17,
                        0,
                    ),
                ),

            'tomorrow' =>
                $this->scheduleChanges(
                    $task,
                    $now
                        ->addDay()
                        ->setTime(
                            17,
                            0,
                        ),
                ),

            'next_week' =>
                $this->scheduleChanges(
                    $task,
                    $now
                        ->addWeek()
                        ->setTime(
                            17,
                            0,
                        ),
                ),
        };
    }

    private function scheduleChanges(
        Task $task,
        CarbonImmutable $dueAt,
    ): array {
        $changes = [
            'due_at' => $dueAt,
            'completed_at' => null,
        ];

        if (
            in_array(
                $task->status,
                [
                    'completed',
                    'cancelled',
                ],
                true,
            )
        ) {
            $changes['status'] =
                'pending';
        }

        return $changes;
    }
}
