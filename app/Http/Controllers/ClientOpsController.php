<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientOpsController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:120'],
            'client' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $organizations = $user->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->orderBy('organizations.name')
            ->get(['organizations.id', 'organizations.name']);

        $organizationIds = $organizations
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $writableIds = collect($user->writableOrganizationIds());
        $writableOrganizations = $organizations
            ->filter(
                fn ($organization): bool => $writableIds->contains(
                    (int) $organization->id,
                ),
            )
            ->values();

        $selectedScope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if (
            $selectedScope
            && ! $organizationIds->contains($selectedScope)
        ) {
            abort(403);
        }

        $search = trim((string) ($validated['q'] ?? ''));

        $clients = Client::query()
            ->visibleTo($user)
            ->with([
                'organizations' => fn ($query) => $query
                    ->where('organizations.is_active', true)
                    ->wherePivot('is_active', true)
                    ->orderBy('organizations.name'),
            ])
            ->when(
                $selectedScope,
                fn ($query) => $query->forOrganization(
                    (int) $selectedScope,
                ),
            )
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function ($nested) use ($like): void {
                    $nested
                        ->where('name', 'like', $like)
                        ->orWhere('legal_name', 'like', $like)
                        ->orWhere('tax_id', 'like', $like)
                        ->orWhere('contact_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $selectedId = isset($validated['client'])
            ? (int) $validated['client']
            : null;

        $selectedClient = $selectedId
            ? $clients->first(
                fn (Client $client): bool =>
                    (int) $client->id === $selectedId,
            )
            : null;

        if ($selectedId && ! $selectedClient) {
            abort(404);
        }

        return view('clients-ops', [
            'organizations' => $organizations,
            'writableOrganizations' => $writableOrganizations,
            'selectedScope' => $selectedScope,
            'search' => $search,
            'clients' => $clients,
            'selectedClient' => $selectedClient,
            'writableIds' => $writableIds,
        ]);
    }
}
