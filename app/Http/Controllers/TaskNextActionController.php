<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskNextActionController extends Controller
{
    public function update(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'next_action' => ['nullable', 'string', 'max:255'],
            'return_to' => ['required', 'in:daily,tracking,decisions'],
            'scope' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', 'in:critical,today,week,planned'],
            'focus' => ['nullable', 'in:attention,stagnant,no_next_action,all'],
            'type' => ['nullable', 'in:all,task,project,service,obligation'],
        ]);

        $userId = $request->user()->id;

        $allowed = DB::table('organization_user')
            ->where('user_id', $userId)
            ->where('organization_id', $task->organization_id)
            ->where('is_active', true)
            ->exists();

        abort_unless($allowed, 403);

        $scope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if ($scope) {
            $scopeAllowed = DB::table('organization_user')
                ->where('user_id', $userId)
                ->where('organization_id', $scope)
                ->where('is_active', true)
                ->exists();

            abort_unless($scopeAllowed, 403);
        }

        $nextAction = trim((string) ($validated['next_action'] ?? ''));

        $task->forceFill([
            'next_action' => $nextAction !== '' ? $nextAction : null,
        ])->save();

        $url = match ($validated['return_to']) {
            'tracking' => route('global-tracking.show', array_filter([
                'scope' => $scope,
                'type' => $validated['type'] ?? 'all',
                'focus' => $validated['focus'] ?? 'attention',
                'q' => trim((string) ($validated['q'] ?? '')) ?: null,
            ])),
            'decisions' => route('decision-inbox.index', array_filter([
                'scope' => $scope,
                'type' => $validated['type'] ?? 'all',
            ])),
            default => route('daily-ops.show', array_filter([
                'scope' => $scope,
                'q' => trim((string) ($validated['q'] ?? '')) ?: null,
                'priority' => $validated['priority'] ?? null,
            ])),
        };

        return redirect()
            ->to($url)
            ->with('daily_action_success', 'Próxima acción actualizada.');
    }
}
