<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\GlobalUndoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyTaskEditController extends Controller
{
    public function update(
        Request $request,
        Task $task,
        GlobalUndoService $undo,
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
                'in:low,normal,medium,high,critical',
            ],
            'impact' => [
                'required',
                'in:low,normal,medium,high,critical',
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
                'in:overdue,critical,today,week,planned',
            ],
            'view' => [
                'nullable',
                'in:mine,team,unassigned',
            ],
            'recurring_rule' => [
                'nullable',
                'integer',
            ],
            'work_team' => [
                'nullable',
                'integer',
            ],
        ]);

        foreach (['urgency', 'impact'] as $field) {
            if (($validated[$field] ?? null) === 'medium') {
                $validated[$field] = 'normal';
            }
        }

        $userId = $request->user()->id;

        abort_unless(
            $task->canBeUpdatedBy($request->user()),
            403,
        );

        $targetOrganizationId =
            (int) $validated['organization_id'];

        if (
            $targetOrganizationId
            !== (int) $task->organization_id
        ) {
            abort_unless(
                $request->user()
                    ->canWriteToOrganization(
                        $targetOrganizationId,
                    ),
                403,
            );
        }

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

        $before = $undo->captureTask(
            $task,
        );

        $task->forceFill([
            'organization_id' =>
                $validated['organization_id'],
            'due_at' => $dueAt,
            'urgency' => $validated['urgency'],
            'impact' => $validated['impact'],
        ])->save();

        $scope = $validated['scope'] ?? null;

        if ($scope) {
            abort_unless(
                $request->user()
                    ->canAccessTaskScopeOrganization(
                        (int) $scope,
                    ),
                403,
            );
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

        if (! empty($validated['view'])) {
            $params['view'] = $validated['view'];
        }

        if (! empty($validated['recurring_rule'])) {
            $params['recurring_rule'] =
                (int) $validated['recurring_rule'];
        }

        if (! empty($validated['work_team'])) {
            $workTeamId =
                (int) $validated['work_team'];

            abort_unless(
                $request->user()
                    ->canAccessWorkTeam($workTeamId),
                403,
            );

            $params['work_team'] = $workTeamId;
            $params['view'] = 'team';
        }

        $undo->rememberTaskMutation(
            $request->user(),
            $task,
            $before,
            'Tarea actualizada',
            route(
                'daily-ops.show',
                $params,
                false,
            ),
        );

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
