<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\GlobalUndoService;
use App\Support\OperationalTaskActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DecisionTaskActionController extends Controller
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
            'type' => [
                'nullable',
                'in:all,task,project,service,obligation',
            ],
        ]);

        $actions->preview(
            $request->user(),
            $task,
            $validated['action'],
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

        $params = array_filter([
            'scope' =>
                $validated['scope']
                ?? null,
            'type' =>
                $validated['type']
                ?? 'all',
        ]);

        $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            $result['label'],
            route(
                'decision-inbox.index',
                $params,
                false,
            ),
        );

        return redirect()
            ->route(
                'decision-inbox.index',
                $params,
            )
            ->with(
                'decision_success',
                'Decisión aplicada.',
            );
    }
}
