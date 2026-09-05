<?php

namespace App\Support;

use App\Models\AutomationRuleRun;
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

    private function authorize(
        User $actor,
        int $organizationId,
    ): void {
        $allowed = DB::table('organization_user')
            ->where('user_id', $actor->id)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException(
                'No autorizado para esta automatización.',
            );
        }
    }
}
