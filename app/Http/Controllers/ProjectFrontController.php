<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectFrontController extends Controller
{
    public function create(Request $request): View
    {
        $scope = $request->validate([
            'scope' => ['nullable', 'integer'],
        ])['scope'] ?? null;

        $writableOrganizations = $this->writableOrganizations($request);

        abort_if($writableOrganizations->isEmpty(), 403);

        $organizationId = $scope
            ? (int) $scope
            : (int) (
                $request->user()->current_organization_id
                ?: $writableOrganizations->first()->id
            );

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        return view('project-front-form', [
            'project' => null,
            'writableOrganizations' => $writableOrganizations,
            'defaultOrganizationId' => $organizationId,
            'participantOptions' => $this->participantOptions(
                $organizationId,
            ),
            'canWrite' => true,
        ]);
    }

    public function edit(
        Request $request,
        Project $project,
    ): View {
        abort_unless(
            $request->user()->canAccessOrganization(
                (int) $project->organization_id,
            ),
            403,
        );

        return view('project-front-form', [
            'project' => $project->load('participants'),
            'writableOrganizations' => $this->writableOrganizations($request),
            'defaultOrganizationId' => (int) $project->organization_id,
            'participantOptions' => $this->participantOptions(
                (int) $project->organization_id,
            ),
            'canWrite' => $request->user()->canWriteToOrganization(
                (int) $project->organization_id,
            ),
        ]);
    }

    private function writableOrganizations(Request $request)
    {
        return $request->user()
            ->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->whereIn(
                'organization_user.role',
                ['owner', 'admin', 'member'],
            )
            ->orderBy('organizations.name')
            ->get(['organizations.id', 'organizations.name']);
    }

    private function participantOptions(int $organizationId): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn (Builder $query): Builder => $query
                    ->where('organizations.id', $organizationId)
                    ->where('organizations.is_active', true)
                    ->where('organization_user.is_active', true)
                    ->whereIn(
                        'organization_user.role',
                        ['owner', 'admin', 'member'],
                    ),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
