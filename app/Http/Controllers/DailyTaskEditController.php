<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\WorkTeam;
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
            'assigned_to' => [
                'nullable',
                'integer',
            ],
            'visibility_scope' => [
                'nullable',
                'in:organization,teams',
            ],
            'work_team_ids' => [
                'nullable',
                'array',
            ],
            'work_team_ids.*' => [
                'integer',
                'distinct',
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

        $visibilityScope =
            $visibilityScope
            ?? ($task->visibility_scope ?: 'organization');

        $selectedTeamIds = array_key_exists(
            'work_team_ids',
            $validated,
        )
            ? collect($validated['work_team_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
            : $task->workTeams()
                ->pluck('work_teams.id')
                ->map(fn ($id): int => (int) $id)
                ->values();

        foreach ($selectedTeamIds as $workTeamId) {
            abort_unless(
                $request->user()
                    ->canAccessWorkTeam($workTeamId),
                403,
            );
        }

        if (
            $visibilityScope === 'teams'
            && $selectedTeamIds->isEmpty()
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'work_team_ids' =>
                    'Selecciona al menos un equipo para una tarea compartida.',
            ]);
        }

        $assigneeId = (int) (
            $validated['assigned_to']
            ?? $task->assigned_to
            ?? $request->user()->id
        );

        if ($visibilityScope === 'organization') {
            $assigneeAllowed = User::query()
                ->whereKey($assigneeId)
                ->where('is_active', true)
                ->whereHas(
                    'organizations',
                    fn ($query) => $query
                        ->where(
                            'organizations.id',
                            $targetOrganizationId,
                        )
                        ->where(
                            'organization_user.is_active',
                            true,
                        )
                        ->whereIn(
                            'organization_user.role',
                            ['owner', 'admin', 'member'],
                        ),
                )
                ->exists();
        } else {
            $assigneeAllowed = User::query()
                ->whereKey($assigneeId)
                ->where('is_active', true)
                ->whereHas(
                    'workTeams',
                    fn ($query) => $query
                        ->whereIn(
                            'work_teams.id',
                            $selectedTeamIds,
                        )
                        ->where(
                            'work_team_user.is_active',
                            true,
                        )
                        ->whereIn(
                            'work_team_user.role',
                            ['lead', 'member'],
                        ),
                )
                ->exists();
        }

        if (! $assigneeAllowed) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assigned_to' =>
                    'El responsable debe tener acceso operativo válido para la visibilidad seleccionada.',
            ]);
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
            'assigned_to' => $assigneeId,
            'visibility_scope' =>
                $visibilityScope,
        ])->save();

        if (
            $visibilityScope
            === 'teams'
        ) {
            $task->workTeams()->sync(
                $selectedTeamIds->all(),
            );
        } else {
            $task->workTeams()->detach();
        }

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
