<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringTaskRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecurringTaskFrontController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $organizationIds = $user->activeOrganizationIds();
        $selectedScope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if (
            $selectedScope
            && ! in_array($selectedScope, $organizationIds, true)
        ) {
            abort(403);
        }

        $search = trim((string) ($validated['q'] ?? ''));

        $query = RecurringTaskRule::query()
            ->visibleTo($user)
            ->with(['organization:id,name', 'project:id,name'])
            ->orderByDesc('is_active')
            ->orderBy('title');

        if ($selectedScope) {
            $query->where('organization_id', $selectedScope);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($subQuery) use ($like): void {
                $subQuery
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('next_action', 'like', $like);
            });
        }

        return view('recurring-tasks-front', [
            'rules' => $query->get(),
            'organizations' => $this->accessibleOrganizations($request),
            'selectedScope' => $selectedScope,
            'search' => $search,
            'writableOrganizationIds' => $user->writableOrganizationIds(),
        ]);
    }

    public function create(Request $request): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
        ]);

        $organizations = $this->writableOrganizations($request);
        abort_if($organizations->isEmpty(), 403);

        $organizationId = isset($validated['scope'])
            ? (int) $validated['scope']
            : (int) $organizations->first()->id;

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        return view('recurring-task-front-form', [
            'rule' => null,
            'organizations' => $organizations,
            'defaultOrganizationId' => $organizationId,
            'projectOptions' => $this->projectOptions($request),
            'assigneeOptions' => $this->assigneeOptions($request),
            'canWrite' => true,
        ]);
    }

    public function edit(
        Request $request,
        RecurringTaskRule $recurringTaskRule,
    ): View {
        abort_unless(
            $request->user()->canAccessOrganization(
                (int) $recurringTaskRule->organization_id,
            ),
            403,
        );

        return view('recurring-task-front-form', [
            'rule' => $recurringTaskRule->load(['organization:id,name', 'project:id,name']),
            'organizations' => $this->accessibleOrganizations($request),
            'defaultOrganizationId' => (int) $recurringTaskRule->organization_id,
            'projectOptions' => $this->projectOptions($request),
            'assigneeOptions' => $this->assigneeOptions($request),
            'canWrite' => $request->user()->canWriteToOrganization(
                (int) $recurringTaskRule->organization_id,
            ),
        ]);
    }

    private function accessibleOrganizations(Request $request)
    {
        return Organization::query()
            ->whereIn('id', $request->user()->activeOrganizationIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function writableOrganizations(Request $request)
    {
        return Organization::query()
            ->whereIn('id', $request->user()->writableOrganizationIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function projectOptions(Request $request): array
    {
        return Project::query()
            ->visibleTo($request->user())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with('organization:id,name')
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name'])
            ->mapWithKeys(fn (Project $project): array => [
                $project->id => [
                    'name' => $project->name,
                    'organization_id' => (int) $project->organization_id,
                    'organization_name' => $project->organization?->name ?? 'Sin ámbito',
                ],
            ])
            ->all();
    }

    private function assigneeOptions(Request $request): array
    {
        $organizationIds = $request->user()->activeOrganizationIds();

        return User::query()
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn (Builder $query): Builder => $query
                    ->whereIn('organizations.id', $organizationIds)
                    ->where('organizations.is_active', true)
                    ->where('organization_user.is_active', true)
                    ->whereIn(
                        'organization_user.role',
                        ['owner', 'admin', 'member'],
                    ),
            )
            ->with([
                'organizations' => fn ($query) => $query
                    ->whereIn('organizations.id', $organizationIds)
                    ->wherePivot('is_active', true),
            ])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => [
                    'name' => $user->name,
                    'organization_ids' => $user->organizations
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all(),
                ],
            ])
            ->all();
    }
}
