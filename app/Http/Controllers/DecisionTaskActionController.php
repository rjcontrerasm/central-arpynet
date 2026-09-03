<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\OperationalTaskActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DecisionTaskActionController extends Controller
{
    public function update(
        Request $request,
        Task $task,
        OperationalTaskActionService $actions,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => ['required', 'in:complete,start,today,tomorrow,next_week'],
            'scope' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:all,task,project,service,obligation'],
        ]);

        $actions->execute(
            $request->user(),
            $task,
            $validated['action'],
            confirmed: true,
        );

        return redirect()
            ->route('decision-inbox.index', array_filter([
                'scope' => $validated['scope'] ?? null,
                'type' => $validated['type'] ?? 'all',
            ]))
            ->with('decision_success', 'Decisión aplicada.');
    }
}
