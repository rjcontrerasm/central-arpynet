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

        return redirect()
            ->route('daily-ops.show')
            ->with(
                'daily_action_success',
                'Tarea actualizada.',
            );
    }
}
