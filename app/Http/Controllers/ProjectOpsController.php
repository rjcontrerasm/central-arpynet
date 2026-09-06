<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Support\GlobalTrackingItemFactory;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectOpsController extends Controller
{
    public function show(Request $request): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
            'focus' => ['nullable', 'in:all,attention,stagnant,no_next_action,active'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();

        $organizationIds = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        $organizations = Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedScope = isset($validated['scope']) ? (int) $validated['scope'] : null;

        if ($selectedScope && ! $organizationIds->contains($selectedScope)) {
            abort(403);
        }

        $focus = $validated['focus'] ?? 'attention';
        $search = trim((string) ($validated['q'] ?? ''));

        $query = Project::query()
            ->visibleTo($user)
            ->with('organization')
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' =>
                    fn ($taskQuery) => $taskQuery->where('status', 'completed'),
            ])
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($selectedScope) {
            $query->where('organization_id', $selectedScope);
        }

        if ($search !== '') {
            $query->where(function ($projectQuery) use ($search): void {
                $like = '%'.$search.'%';
                $projectQuery
                    ->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('next_action', 'like', $like)
                    ->orWhere('blockers', 'like', $like);
            });
        }

        $now = CarbonImmutable::now(config('app.timezone', 'America/Lima'));

        $allRows = $query->limit(250)->get()->map(
            fn (Project $project): array => [
                'project' => $project,
                'signal' => GlobalTrackingItemFactory::project($project, $now),
            ],
        );

        $summary = [
            'total' => $allRows->count(),
            'critical' => $allRows->where('signal.level', 'critical')->count(),
            'attention' => $allRows->filter(
                fn (array $row): bool => in_array(
                    $row['signal']['level'],
                    ['attention', 'watch'],
                    true,
                ),
            )->count(),
            'stagnant' => $allRows->filter(
                fn (array $row): bool => (bool) $row['signal']['stagnant'],
            )->count(),
            'no_next_action' => $allRows->filter(
                fn (array $row): bool => (bool) $row['signal']['no_next_action'],
            )->count(),
        ];

        $rows = match ($focus) {
            'attention' => $allRows->filter(
                fn (array $row): bool => in_array(
                    $row['signal']['level'],
                    ['critical', 'attention', 'watch'],
                    true,
                ),
            ),
            'stagnant' => $allRows->filter(
                fn (array $row): bool => (bool) $row['signal']['stagnant'],
            ),
            'no_next_action' => $allRows->filter(
                fn (array $row): bool => (bool) $row['signal']['no_next_action'],
            ),
            'active' => $allRows->filter(
                fn (array $row): bool => $row['project']->status === 'active',
            ),
            default => $allRows,
        };

        $rows = $rows
            ->sortByDesc(fn (array $row): int => (int) $row['signal']['rank'])
            ->values();

        return view('projects-ops', [
            'rows' => $rows,
            'summary' => $summary,
            'organizations' => $organizations,
            'selectedScope' => $selectedScope,
            'focus' => $focus,
            'search' => $search,
            'statusOptions' => Project::statusOptions(),
            'typeOptions' => Project::typeOptions(),
            'horizonOptions' => Project::horizonOptions(),
        ]);
    }
}
