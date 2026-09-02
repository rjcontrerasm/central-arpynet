<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\TaskActionUndoSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailyTaskActionController extends Controller
{
    public function update(
        Request $request,
        Task $task,
        TaskActionUndoSnapshot $undo,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => [
                'required',
                'in:complete,start,today,tomorrow,next_week',
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

        $this->authorizeTask(
            $request,
            $task,
        );

        $filters = $this->filterParams(
            $request,
            $validated,
        );

        $undo->remember(
            $task,
            $filters,
        );

        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        match ($validated['action']) {
            'complete' =>
                $this->complete($task),
            'start' =>
                $this->start($task),
            'today' =>
                $this->move(
                    $task,
                    CarbonImmutable::now(
                        $timezone,
                    )->setTime(17, 0),
                ),
            'tomorrow' =>
                $this->move(
                    $task,
                    CarbonImmutable::now(
                        $timezone,
                    )
                        ->addDay()
                        ->setTime(17, 0),
                ),
            'next_week' =>
                $this->move(
                    $task,
                    CarbonImmutable::now(
                        $timezone,
                    )
                        ->addWeek()
                        ->setTime(17, 0),
                ),
        };

        $message = match (
            $validated['action']
        ) {
            'complete' =>
                'Tarea marcada como completada.',
            'start' =>
                'Tarea marcada en curso.',
            'today' =>
                'Tarea movida a hoy.',
            'tomorrow' =>
                'Tarea movida a mañana.',
            'next_week' =>
                'Tarea movida una semana.',
        };

        return redirect()
            ->route(
                'daily-ops.show',
                $filters,
            )
            ->with(
                'daily_action_success',
                $message,
            );
    }

    private function authorizeTask(
        Request $request,
        Task $task,
    ): void {
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
    }

    private function filterParams(
        Request $request,
        array $validated,
    ): array {
        $params = [];

        $scope =
            $validated['scope'] ?? null;

        if ($scope) {
            $allowed =
                DB::table(
                    'organization_user',
                )
                    ->where(
                        'user_id',
                        $request->user()->id,
                    )
                    ->where(
                        'organization_id',
                        $scope,
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

            $params['scope'] = $scope;
        }

        $q = trim(
            (string) (
                $validated['q']
                ?? ''
            ),
        );

        if ($q !== '') {
            $params['q'] = $q;
        }

        if (
            ! empty(
                $validated['priority']
            )
        ) {
            $params['priority'] =
                $validated['priority'];
        }

        return $params;
    }

    private function complete(
        Task $task,
    ): void {
        $data = [
            'status' => 'completed',
        ];

        if (
            Schema::hasColumn(
                'tasks',
                'completed_at',
            )
        ) {
            $data['completed_at'] =
                now();
        }

        $task
            ->forceFill($data)
            ->save();
    }

    private function start(
        Task $task,
    ): void {
        $data = [
            'status' => 'in_progress',
        ];

        if (
            Schema::hasColumn(
                'tasks',
                'completed_at',
            )
        ) {
            $data['completed_at'] =
                null;
        }

        $task
            ->forceFill($data)
            ->save();
    }

    private function move(
        Task $task,
        CarbonImmutable $dueAt,
    ): void {
        $data = [
            'due_at' => $dueAt,
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
            $data['status'] =
                'pending';
        }

        if (
            Schema::hasColumn(
                'tasks',
                'completed_at',
            )
        ) {
            $data['completed_at'] =
                null;
        }

        $task
            ->forceFill($data)
            ->save();
    }
}
