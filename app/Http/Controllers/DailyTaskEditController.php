<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyTaskEditController extends Controller
{
    public function update(
        Request $request,
        Task $task,
    ): RedirectResponse {
        $validated = $request->validate([
            'organization_id' => [
                'required',
                'integer',
            ],
            'due_date' => [
                'nullable',
                'date',
            ],
            'urgency' => [
                'required',
                'in:low,medium,high',
            ],
            'impact' => [
                'required',
                'in:low,medium,high',
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

        $userId = $request->user()->id;

        $canEditCurrent = DB::table('organization_user')
            ->where('user_id', $userId)
            ->where(
                'organization_id',
                $task->organization_id,
            )
            ->where('is_active', true)
            ->exists();

        abort_unless($canEditCurrent, 403);

        $canUseTarget = DB::table('organization_user')
            ->where('user_id', $userId)
            ->where(
                'organization_id',
                $validated['organization_id'],
            )
            ->where('is_active', true)
            ->exists();

        abort_unless($canUseTarget, 403);

        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        $dueAt = empty($validated['due_date'])
            ? null
            : CarbonImmutable::parse(
                $validated['due_date'],
                $timezone,
            )->setTime(17, 0);

        $task->forceFill([
            'organization_id' =>
                $validated['organization_id'],
            'due_at' => $dueAt,
            'urgency' => $validated['urgency'],
            'impact' => $validated['impact'],
        ])->save();

        $scope = $validated['scope'] ?? null;

        if ($scope) {
            $scopeAllowed =
                DB::table('organization_user')
                    ->where('user_id', $userId)
                    ->where('organization_id', $scope)
                    ->where('is_active', true)
                    ->exists();

            abort_unless($scopeAllowed, 403);
        }

        $params = [];

        if ($scope) {
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

        return redirect()
            ->route(
                'daily-ops.show',
                $params,
            )
            ->with(
                'daily_action_success',
                'Tarea actualizada.',
            );
    }
}
