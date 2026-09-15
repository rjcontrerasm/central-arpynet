<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceOrderFrontController extends Controller
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
            : (int) $writableOrganizations->first()->id;

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        return view('service-order-front-form', [
            'serviceOrder' => null,
            'writableOrganizations' => $writableOrganizations,
            'defaultOrganizationId' => $organizationId,
            'clientOptions' => $this->clientOptions($request),
            'assigneeOptions' => $this->assigneeOptions($request),
            'canWrite' => true,
        ]);
    }

    public function edit(
        Request $request,
        ServiceOrder $serviceOrder,
    ): View {
        abort_unless(
            $request->user()->canAccessOrganization(
                (int) $serviceOrder->organization_id,
            ),
            403,
        );

        return view('service-order-front-form', [
            'serviceOrder' => $serviceOrder->load(['client', 'assignee']),
            'writableOrganizations' => $this->writableOrganizations($request),
            'defaultOrganizationId' => (int) $serviceOrder->organization_id,
            'clientOptions' => $this->clientOptions($request),
            'assigneeOptions' => $this->assigneeOptions($request),
            'canWrite' => $request->user()->canWriteToOrganization(
                (int) $serviceOrder->organization_id,
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

    private function clientOptions(Request $request): array
    {
        return Client::query()
            ->visibleTo($request->user())
            ->where('is_active', true)
            ->with([
                'organizations' => fn ($query) => $query
                    ->where('organizations.is_active', true)
                    ->wherePivot('is_active', true)
                    ->orderBy('organizations.name'),
            ])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Client $client): array => [
                $client->id => [
                    'name' => $client->name,
                    'organization_ids' => $client->organizations
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all(),
                    'organization_names' => $client->organizations
                        ->pluck('name')
                        ->values()
                        ->all(),
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
