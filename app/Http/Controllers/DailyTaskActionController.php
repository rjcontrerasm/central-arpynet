<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\GlobalUndoService;
use App\Support\OperationalTaskActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyTaskActionController extends Controller
{
    public function update(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
        OperationalTaskActionService $actions,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => [
                'required',
                'in:complete,start,today,tomorrow,next_week',
            ],
            'scope' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:120'],
            'priority' => [
                'nullable',
                'in:critical,today,week,planned',
            ],
        ]);

        $actions->preview(
            $request->user(),
            $task,
            $validated['action'],
        );

        $filters = $this->filterParams(
            $request,
            $validated,
        );

        $before = $undo->captureTask(
            $task,
        );

        $result = $actions->execute(
            $request->user(),
            $task,
            $validated['action'],
            confirmed: true,
        );

        $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            $result['label'],
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
                $result['label'].'.',
            );
    }

    private function filterParams(
        Request $request,
        array $validated,
    ): array {
        $params = [];
        $scope = $validated['scope'] ?? null;

        if ($scope) {
            $allowed = DB::table(
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

        if (! empty(
            $validated['priority']
                ?? null
        )) {
            $params['priority'] =
                $validated['priority'];
        }

        return $params;
    }
}
