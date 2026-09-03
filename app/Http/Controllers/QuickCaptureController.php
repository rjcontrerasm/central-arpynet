<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
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
        $user = $request->user();
        $organizations = $this->organizationsFor($user->id);
        $projects = $this->projectsFor($user->id);
        $organizationIds = $organizations->pluck('id');

        $recentTasks = Task::query()
            ->with('organization')
            ->where('created_by', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $defaultOrganizationId = $user->current_organization_id;

        if (
            ! $defaultOrganizationId
            || ! $organizationIds->contains($defaultOrganizationId)
        ) {
            $defaultOrganizationId = $organizations->first()?->id;
        }

        return view('quick-capture', compact(
            'organizations',
            'projects',
            'recentTasks',
            'defaultOrganizationId',
        ));
    }

    public function store(
        Request $request,
        SmartTaskCaptureParser $parser,
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
        ]);

        foreach (['urgency', 'impact'] as $field) {
            if (($validated[$field] ?? null) === 'medium') {
                $validated[$field] = 'normal';
            }
        }

        $organizations = $this->organizationsFor($user->id);
        $projects = $this->projectsFor($user->id);

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

        Task::query()->create([
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
            'created_by' => $user->id,
        ]);

        $message = 'Tarea registrada correctamente.';

        if ($parsed['interpretations'] !== []) {
            $message .= ' Detectado: '
                .implode(' · ', $parsed['interpretations'])
                .'.';
        }

        return redirect()
            ->route('quick-capture.show')
            ->with('quick_capture_success', $message);
    }

    private function organizationsFor(int $userId): Collection
    {
        $organizationIds = DB::table('organization_user')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('organization_id');

        return Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function projectsFor(int $userId): Collection
    {
        $organizationIds = DB::table('organization_user')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('organization_id');

        return Project::query()
            ->whereIn('organization_id', $organizationIds)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name']);
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
