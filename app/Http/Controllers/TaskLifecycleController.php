<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\GlobalUndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskLifecycleController extends Controller
{
    public function cancel(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $this->authorizeTask(
            $request,
            $task,
        );

        $before =
            $undo->captureTask(
                $task,
            );

        $task->forceFill([
            'status' => 'cancelled',
            'waiting_since' => null,
            'waiting_reason' => null,
            'waiting_until' => null,
        ])->save();

        $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            'Tarea cancelada',
            route(
                'daily-ops.show',
                [],
                false,
            ),
        );

        return redirect()
            ->route('daily-ops.show')
            ->with(
                'daily_action_success',
                'Tarea cancelada.',
            );
    }

    public function delete(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $this->authorizeTask(
            $request,
            $task,
        );

        $before =
            $undo->captureTask(
                $task,
            );

        $task->delete();

        $deleted = Task::withTrashed()
            ->findOrFail($task->id);

        $undo->rememberTaskMutation(
            $request->user(),
            $deleted,
            $before,
            'Tarea enviada a papelera',
            route(
                'daily-ops.show',
                [],
                false,
            ),
        );

        return redirect()
            ->route('daily-ops.show')
            ->with(
                'daily_action_success',
                'Tarea enviada a la papelera.',
            );
    }

    public function trash(
        Request $request,
    ): View {
        $organizationIds =
            DB::table(
                'organization_user',
            )
                ->where(
                    'user_id',
                    $request->user()->id,
                )
                ->where(
                    'is_active',
                    true,
                )
                ->pluck(
                    'organization_id',
                );

        $tasks = Task::onlyTrashed()
            ->with('organization')
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->latest('deleted_at')
            ->limit(100)
            ->get();

        return view(
            'task-trash',
            compact('tasks'),
        );
    }

    public function restore(
        Request $request,
        int $taskId,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $task = Task::onlyTrashed()
            ->findOrFail($taskId);

        $this->authorizeTask(
            $request,
            $task,
        );

        $before =
            $undo->captureTask(
                $task,
            );

        $task->restore();
        $task->refresh();

        $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            'Tarea restaurada',
            route(
                'task-lifecycle.trash',
                [],
                false,
            ),
        );

        return redirect()
            ->route(
                'task-lifecycle.trash',
            )
            ->with(
                'trash_success',
                'Tarea restaurada.',
            );
    }

    public function purge(
        Request $request,
        int $taskId,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $validated = $request->validate([
            'confirmation' => [
                'required',
                'in:ELIMINAR',
            ],
        ]);

        $task = Task::onlyTrashed()
            ->findOrFail($taskId);

        $this->authorizeTask(
            $request,
            $task,
        );

        /*
         * Purge is intentionally irreversible.
         * It replaces any previous undo option.
         */
        $undo->invalidateCurrent(
            $request->user(),
        );

        $task->forceDelete();

        return redirect()
            ->route(
                'task-lifecycle.trash',
            )
            ->with(
                'trash_success',
                'Tarea eliminada definitivamente.',
            );
    }

    private function authorizeTask(
        Request $request,
        Task $task,
    ): void {
        $allowed = DB::table(
            'organization_user',
        )
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
    }
}
