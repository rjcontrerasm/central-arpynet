<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\GlobalUndoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyTaskWaitingController extends Controller
{
    public function wait(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $validated = $request->validate([
            'waiting_until' => [
                'required',
                'date',
            ],
            'waiting_reason' => [
                'required',
                'string',
                'max:255',
            ],
            'scope' => [
                'nullable',
                'integer',
            ],
            'q' => [
                'nullable',
                'string',
                'max:120',
            ],
            'priority' => [
                'nullable',
                'in:critical,today,week,planned',
            ],
        ]);

        $this->authorizeTask($request, $task);

        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        $before = $undo->captureTask(
            $task,
        );

        $task->forceFill([
            'waiting_since' => CarbonImmutable::now(
                $timezone,
            ),
            'waiting_until' =>
                $validated['waiting_until'],
            'waiting_reason' =>
                trim($validated['waiting_reason']),
        ])->save();

        $filters = $this->filters(
            $request,
            $validated,
        );

        $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            'Tarea puesta en espera',
            route(
                'daily-ops.show',
                $filters,
                false,
            ),
        );

        return redirect()
            ->route(
                'daily-ops.show',
                $filters,
            )
            ->with(
                'daily_action_success',
                'Tarea puesta en espera.',
            );
    }

    public function resume(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $validated = $request->validate([
            'scope' => [
                'nullable',
                'integer',
            ],
            'q' => [
                'nullable',
                'string',
                'max:120',
            ],
            'priority' => [
                'nullable',
                'in:critical,today,week,planned',
            ],
        ]);

        $this->authorizeTask($request, $task);

        $before = $undo->captureTask(
            $task,
        );

        $task->forceFill([
            'waiting_since' => null,
            'waiting_until' => null,
            'waiting_reason' => null,
        ])->save();

        $filters = $this->filters(
            $request,
            $validated,
        );

        $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            'Tarea reactivada',
            route(
                'daily-ops.show',
                $filters,
                false,
            ),
        );

        return redirect()
            ->route(
                'daily-ops.show',
                $filters,
            )
            ->with(
                'daily_action_success',
                'Tarea reactivada.',
            );
    }

    private function authorizeTask(
        Request $request,
        Task $task,
    ): void {
        $allowed = DB::table('organization_user')
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->where(
                'organization_id',
                $task->organization_id,
            )
            ->where('is_active', true)
            ->exists();

        abort_unless($allowed, 403);
    }

    private function filters(
        Request $request,
        array $validated,
    ): array {
        $params = [];

        $scope = $validated['scope'] ?? null;

        if ($scope) {
            $allowed = DB::table('organization_user')
                ->where(
                    'user_id',
                    $request->user()->id,
                )
                ->where('organization_id', $scope)
                ->where('is_active', true)
                ->exists();

            abort_unless($allowed, 403);

            $params['scope'] = $scope;
        }

        $q = trim(
            (string) ($validated['q'] ?? ''),
        );

        if ($q !== '') {
            $params['q'] = $q;
        }

        if (! empty($validated['priority'])) {
            $params['priority'] =
                $validated['priority'];
        }

        return $params;
    }
}
