<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkTeam;
use App\Support\GlobalUndoService;
use App\Support\SmartTaskCaptureParser;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuickCaptureController extends Controller
{
    public function show(Request $request): View
    {
        $validated = $request->validate([
            'work_team' => ['nullable', 'integer'],
            'organization_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $organizations = $this->organizationsFor($user->id);
        $projects = $this->projectsFor($user);
        $workTeams = $this->workTeamsFor($user);
        $assignees = $this->assigneesFor(
            $user,
            $workTeams,
        );
        $organizationIds = $organizations->pluck('id');
        $accessibleTeamIds = $workTeams
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $contextWorkTeamId = isset($validated['work_team'])
            ? (int) $validated['work_team']
            : null;

        if (
            $contextWorkTeamId
            && ! $accessibleTeamIds->contains(
                $contextWorkTeamId,
            )
        ) {
            abort(403);
        }

        $contextWorkTeam = $contextWorkTeamId
            ? $workTeams->firstWhere(
                'id',
                $contextWorkTeamId,
            )
            : null;

        $contextOrganizationId = isset(
            $validated['organization_id'],
        )
            ? (int) $validated['organization_id']
            : null;

        if (
            $contextOrganizationId
            && ! $organizationIds->contains(
                $contextOrganizationId,
            )
        ) {
            abort(403);
        }

        $defaultTeamByAssignee = $assignees
            ->mapWithKeys(
                function (User $assignee) use (
                    $accessibleTeamIds,
                ): array {
                    $teamId = (int) (
                        $assignee->default_work_team_id
                        ?? 0
                    );

                    if (
                        $teamId < 1
                        || ! $accessibleTeamIds->contains(
                            $teamId,
                        )
                    ) {
                        return [$assignee->id => null];
                    }

                    $isActiveMember = DB::table(
                        'work_team_user',
                    )
                        ->where(
                            'work_team_id',
                            $teamId,
                        )
                        ->where(
                            'user_id',
                            $assignee->id,
                        )
                        ->where('is_active', true)
                        ->whereIn(
                            'role',
                            ['lead', 'member'],
                        )
                        ->exists();

                    return [
                        $assignee->id =>
                            $isActiveMember
                                ? $teamId
                                : null,
                    ];
                },
            );

        $defaultWorkTeamId = $contextWorkTeamId
            ?? (
                $defaultTeamByAssignee[
                    $user->id
                ] ?? null
            );

        $defaultVisibilityScope =
            $defaultWorkTeamId
                ? 'teams'
                : 'organization';

        $recentTasks = Task::query()
            ->with('organization')
            ->where('created_by', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $defaultOrganizationId =
            $contextOrganizationId
            ?? $user->current_organization_id;

        if (
            ! $defaultOrganizationId
            || ! $organizationIds->contains($defaultOrganizationId)
        ) {
            $defaultOrganizationId = $organizations->first()?->id;
        }

        $assigneeIds = $assignees->pluck('id');

        $assigneeOrganizationIds = DB::table(
            'organization_user',
        )
            ->whereIn('user_id', $assigneeIds)
            ->where('is_active', true)
            ->whereIn(
                'role',
                ['owner', 'admin', 'member'],
            )
            ->get([
                'user_id',
                'organization_id',
            ])
            ->groupBy('user_id')
            ->map(
                fn ($rows) => $rows
                    ->pluck('organization_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
            );

        $assigneeWorkTeamIds = DB::table(
            'work_team_user',
        )
            ->whereIn('user_id', $assigneeIds)
            ->whereIn(
                'work_team_id',
                $accessibleTeamIds,
            )
            ->where('is_active', true)
            ->whereIn(
                'role',
                ['lead', 'member'],
            )
            ->get([
                'user_id',
                'work_team_id',
            ])
            ->groupBy('user_id')
            ->map(
                fn ($rows) => $rows
                    ->pluck('work_team_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
            );

        return view('quick-capture', compact(
            'organizations',
            'projects',
            'recentTasks',
            'defaultOrganizationId',
            'workTeams',
            'assignees',
            'contextWorkTeamId',
            'contextWorkTeam',
            'contextOrganizationId',
            'defaultWorkTeamId',
            'defaultVisibilityScope',
            'defaultTeamByAssignee',
            'assigneeOrganizationIds',
            'assigneeWorkTeamIds',
        ));
    }

    public function store(
        Request $request,
        SmartTaskCaptureParser $parser,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $user = $request->user();

        $validated = $request->validate([
            'organization_id' => ['required', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'due_mode' => ['required', 'in:today,tomorrow,next_week,custom,none'],
            'due_date' => ['nullable', 'date', 'required_if:due_mode,custom'],
            'urgency' => ['required', 'in:low,normal,medium,high,critical'],
            'impact' => ['required', 'in:low,normal,medium,high,critical'],
            'assigned_to' => ['nullable', 'integer'],
            'visibility_scope' => [
                'nullable',
                'in:organization,teams',
            ],
            'work_team_ids' => [
                'nullable',
                'array',
                'required_if:visibility_scope,teams',
            ],
            'work_team_ids.*' => [
                'integer',
                'distinct',
            ],
            'capture_context_work_team_id' => [
                'nullable',
                'integer',
            ],
            'capture_context_organization_id' => [
                'nullable',
                'integer',
            ],
        ]);

        foreach (['urgency', 'impact'] as $field) {
            if (($validated[$field] ?? null) === 'medium') {
                $validated[$field] = 'normal';
            }
        }

        $visibilityScope =
            $validated['visibility_scope']
            ?? 'organization';

        $assigneeId = (int) (
            $validated['assigned_to']
            ?? $user->id
        );

        $organizations = $this->organizationsFor($user->id);
        $projects = $this->projectsFor($user);
        $workTeams = $this->workTeamsFor($user);

        $parsed = $parser->parse(
            $validated['title'],
            $organizations,
            $projects,
        );

        $title = trim((string) $parsed['title']);

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Escribe una tarea después de los atajos.',
            ]);
        }

        $organizationId = $parsed['organization_id']
            ?? (int) $validated['organization_id'];

        abort_unless(
            $organizations->contains('id', $organizationId),
            403,
        );

        $selectedTeamIds = collect(
            $validated['work_team_ids'] ?? [],
        )
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $accessibleTeamIds = $workTeams
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        abort_unless(
            $selectedTeamIds
                ->diff($accessibleTeamIds)
                ->isEmpty(),
            403,
        );

        // A selected team always means team visibility.
        // This also protects against stale or contradictory
        // browser state such as organization + team selected.
        if ($selectedTeamIds->isNotEmpty()) {
            $visibilityScope = 'teams';
        }

        if (
            $visibilityScope
            === 'teams'
            && $selectedTeamIds->isEmpty()
        ) {
            throw ValidationException::withMessages([
                'work_team_ids' =>
                    'Selecciona al menos un equipo para una tarea compartida.',
            ]);
        }

        if ($visibilityScope === 'organization') {
            $assigneeAllowed = User::query()
                ->whereKey($assigneeId)
                ->where('is_active', true)
                ->whereHas(
                    'organizations',
                    fn ($query) => $query
                        ->where(
                            'organizations.id',
                            $organizationId,
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
            throw ValidationException::withMessages([
                'assigned_to' =>
                    'El responsable debe tener acceso operativo válido para la visibilidad seleccionada.',
            ]);
        }

        $projectId = $parsed['project_id']
            ?? ($validated['project_id'] ?? null);

        if ($projectId) {
            $project = $projects->firstWhere('id', (int) $projectId);
            abort_unless($project, 403);

            if ((int) $project->organization_id !== $organizationId) {
                throw ValidationException::withMessages([
                    'project_id' => 'El proyecto no pertenece al ámbito seleccionado.',
                ]);
            }

            $projectId = (int) $project->id;
        }

        $urgency = $parsed['urgency'] ?? $validated['urgency'];
        $impact = $parsed['impact'] ?? $validated['impact'];
        $waiting = (bool) $parsed['waiting'];
        $explicitDate = $parsed['due_date'] ?? null;

        $dueMode = $waiting
            ? ($parsed['due_mode'] ?? ($explicitDate ? 'custom' : 'none'))
            : ($parsed['due_mode'] ?? $validated['due_mode']);

        $customDate = $explicitDate
            ?? (
                ! $waiting && $dueMode === 'custom'
                    ? ($validated['due_date'] ?? null)
                    : null
            );

        $dueAt = $this->dueAt($dueMode, $customDate);
        $timezone = config('app.timezone', 'America/Lima');

        $task = Task::query()->create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'title' => $title,
            'next_action' => $parsed['next_action'],
            'status' => $waiting ? 'waiting' : 'pending',
            'urgency' => $urgency,
            'impact' => $impact,
            'due_at' => $waiting ? null : $dueAt,
            'waiting_since' => $waiting
                ? CarbonImmutable::now($timezone)
                : null,
            'waiting_until' => $waiting ? $dueAt : null,
            'waiting_reason' => $waiting
                ? 'Seguimiento pendiente'
                : null,
            'source' => 'manual',
            'assigned_to' => $assigneeId,
            'created_by' => $user->id,
            'visibility_scope' =>
                $visibilityScope,
        ]);

        if (
            $visibilityScope
            === 'teams'
        ) {
            $task->workTeams()->sync(
                $selectedTeamIds->all(),
            );
        }

        $undo->rememberTaskCreated(
            $user,
            $task,
            'Tarea creada',
            route(
                'quick-capture.show',
                [],
                false,
            ),
        );

        $message = 'Tarea registrada correctamente.';

        if ($parsed['interpretations'] !== []) {
            $message .= ' Detectado: '
                .implode(' · ', $parsed['interpretations'])
                .'.';
        }

        $contextWorkTeamId = (int) (
            $validated[
                'capture_context_work_team_id'
            ] ?? 0
        );

        $redirectParams = [];

        if (
            $contextWorkTeamId > 0
            && $accessibleTeamIds->contains(
                $contextWorkTeamId,
            )
        ) {
            $redirectParams['work_team'] =
                $contextWorkTeamId;
            $redirectParams['organization_id'] =
                $organizationId;
        } elseif (
            (int) (
                $validated[
                    'capture_context_organization_id'
                ] ?? 0
            ) > 0
        ) {
            $contextOrganizationId = (int) $validated[
                'capture_context_organization_id'
            ];

            abort_unless(
                $organizationIds->contains(
                    $contextOrganizationId,
                ),
                403,
            );

            $redirectParams['organization_id'] =
                $organizationId;
        }

        return redirect()
            ->route(
                'quick-capture.show',
                $redirectParams,
            )
            ->with('quick_capture_success', $message);
    }

    private function organizationsFor(int $userId): Collection
    {
        $organizationIds = DB::table('organization_user')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereIn(
                'role',
                ['owner', 'admin', 'member'],
            )
            ->pluck('organization_id');

        return Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function projectsFor(User $user): Collection
    {
        return Project::query()
            ->whereIn(
                'organization_id',
                $user->writableOrganizationIds(),
            )
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name']);
    }

    private function workTeamsFor(User $user): Collection
    {
        $manageableIds =
            $user->manageableOrganizationIds();

        return WorkTeam::query()
            ->where('is_active', true)
            ->where(
                function ($query) use (
                    $user,
                    $manageableIds,
                ): void {
                    $query->whereHas(
                        'users',
                        fn ($membership) => $membership
                            ->where(
                                'users.id',
                                $user->id,
                            )
                            ->where(
                                'work_team_user.is_active',
                                true,
                            ),
                    );

                    if ($manageableIds !== []) {
                        $query->orWhereIn(
                            'home_organization_id',
                            $manageableIds,
                        );
                    }
                },
            )
            ->with([
                'users' => fn ($query) => $query
                    ->where('users.is_active', true)
                    ->where(
                        'work_team_user.is_active',
                        true,
                    )
                    ->orderBy('users.name'),
            ])
            ->orderBy('name')
            ->get();
    }

    private function assigneesFor(
        User $user,
        Collection $workTeams,
    ): Collection {
        $organizationUserIds = DB::table(
            'organization_user',
        )
            ->whereIn(
                'organization_id',
                $user->writableOrganizationIds(),
            )
            ->where('is_active', true)
            ->whereIn(
                'role',
                ['owner', 'admin', 'member'],
            )
            ->pluck('user_id');

        $teamUserIds = $workTeams
            ->flatMap(
                fn (WorkTeam $team) =>
                    $team->users
                        ->filter(
                            fn (User $member): bool =>
                                in_array(
                                    $member->pivot->role,
                                    ['lead', 'member'],
                                    true,
                                ),
                        )
                        ->pluck('id'),
            );

        return User::query()
            ->whereIn(
                'id',
                $organizationUserIds
                    ->merge($teamUserIds)
                    ->push($user->id)
                    ->unique()
                    ->values(),
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'default_work_team_id',
            ]);
    }

    private function dueAt(string $mode, ?string $customDate): ?CarbonImmutable
    {
        $timezone = config('app.timezone', 'America/Lima');
        $now = CarbonImmutable::now($timezone);

        return match ($mode) {
            'today' => $now->setTime(17, 0),
            'tomorrow' => $now->addDay()->setTime(17, 0),
            'next_week' => $now->addWeek()->setTime(17, 0),
            'custom' => $customDate
                ? CarbonImmutable::parse($customDate, $timezone)->setTime(17, 0)
                : null,
            default => null,
        };
    }
}
