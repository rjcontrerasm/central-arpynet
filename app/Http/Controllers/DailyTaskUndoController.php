<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\TaskActionUndoSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyTaskUndoController extends Controller
{
    public function restore(
        Request $request,
        TaskActionUndoSnapshot $undo,
    ): RedirectResponse {
        $snapshot = $undo->pull();

        if (! $snapshot) {
            return redirect()
                ->route('daily-ops.show')
                ->with(
                    'daily_action_success',
                    'Ya no hay una acción para deshacer.',
                );
        }

        $filters =
            is_array(
                $snapshot['filters']
                ?? null,
            )
                ? $snapshot['filters']
                : [];

        if (
            (int) (
                $snapshot['expires_at']
                ?? 0
            ) < CarbonImmutable::now()
                ->timestamp
        ) {
            return redirect()
                ->route(
                    'daily-ops.show',
                    $filters,
                )
                ->with(
                    'daily_action_success',
                    'La opción de deshacer expiró.',
                );
        }

        $task = Task::query()->find(
            (int) (
                $snapshot['task_id']
                ?? 0
            ),
        );

        if (! $task) {
            return redirect()
                ->route(
                    'daily-ops.show',
                    $filters,
                )
                ->with(
                    'daily_action_success',
                    'La tarea ya no está disponible.',
                );
        }

        $allowed =
            DB::table('organization_user')
                ->where(
                    'user_id',
                    $request->user()->id,
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

        abort_unless(
            $allowed,
            403,
        );

        $state =
            is_array(
                $snapshot['state']
                ?? null,
            )
                ? $snapshot['state']
                : [];

        $task->forceFill([
            'status' =>
                $state['status']
                ?? 'pending',
            'due_at' =>
                $state['due_at']
                ?? null,
            'waiting_since' =>
                $state['waiting_since']
                ?? null,
            'waiting_reason' =>
                $state['waiting_reason']
                ?? null,
            'waiting_until' =>
                $state['waiting_until']
                ?? null,
            'completed_at' =>
                $state['completed_at']
                ?? null,
        ])->save();

        return redirect()
            ->route(
                'daily-ops.show',
                $filters,
            )
            ->with(
                'daily_action_success',
                'Acción deshecha.',
            );
    }
}
