<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientOpsActionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $organizationIds = $this->organizationIds($validated);

        $this->assertWritableOrganizations($request, $organizationIds);

        $client = DB::transaction(function () use (
            $request,
            $validated,
            $organizationIds,
        ): Client {
            $attributes = $this->attributes($request, $validated);
            $attributes['organization_id'] = $organizationIds[0];
            $attributes['created_by'] = $request->user()->id;

            $client = Client::query()->create($attributes);

            $client->organizations()->sync(
                $this->pivotPayload(
                    $organizationIds,
                    $request->user()->id,
                ),
            );

            return $client;
        });

        $scope = $this->redirectScope($validated, $organizationIds);

        return redirect()
            ->route('client-ops.index', [
                'scope' => $scope,
                'client' => $client->id,
            ])
            ->with('client_success', 'Cliente compartido creado.');
    }

    public function update(
        Request $request,
        Client $client,
    ): RedirectResponse {
        $client->load('organizations:id');

        $existingOrganizationIds = $client->organizations
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($existingOrganizationIds === [] && $client->organization_id) {
            $existingOrganizationIds = [(int) $client->organization_id];
        }

        $this->assertWritableOrganizations(
            $request,
            $existingOrganizationIds,
        );

        $validated = $this->validatePayload($request);
        $organizationIds = $this->organizationIds($validated);

        $this->assertWritableOrganizations($request, $organizationIds);
        $this->guardOrganizationDetaches(
            $client,
            $existingOrganizationIds,
            $organizationIds,
        );

        DB::transaction(function () use (
            $request,
            $client,
            $validated,
            $organizationIds,
        ): void {
            $attributes = $this->attributes($request, $validated);

            // Compatibilidad transitoria para código legado. La relación real
            // queda en client_organization.
            $attributes['organization_id'] = $organizationIds[0];

            $client->forceFill($attributes)->save();

            $client->organizations()->sync(
                $this->pivotPayload(
                    $organizationIds,
                    $request->user()->id,
                ),
            );
        });

        $scope = $this->redirectScope($validated, $organizationIds);

        return redirect()
            ->route('client-ops.index', [
                'scope' => $scope,
                'client' => $client->id,
            ])
            ->with('client_success', 'Cliente compartido actualizado.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'organization_ids' => ['required', 'array', 'min:1'],
            'organization_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:organizations,id',
            ],
            'scope' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'drive_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function organizationIds(array $validated): array
    {
        return collect($validated['organization_ids'])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function assertWritableOrganizations(
        Request $request,
        array $organizationIds,
    ): void {
        abort_if($organizationIds === [], 403);

        foreach ($organizationIds as $organizationId) {
            abort_unless(
                $request->user()->canWriteToOrganization(
                    (int) $organizationId,
                ),
                403,
            );
        }
    }

    private function guardOrganizationDetaches(
        Client $client,
        array $existingOrganizationIds,
        array $organizationIds,
    ): void {
        $removed = array_values(array_diff(
            $existingOrganizationIds,
            $organizationIds,
        ));

        if ($removed === []) {
            return;
        }

        $usedOrganizationIds = $client->serviceOrders()
            ->whereIn('organization_id', $removed)
            ->distinct()
            ->pluck('organization_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($usedOrganizationIds !== []) {
            throw ValidationException::withMessages([
                'organization_ids' =>
                    'No se puede desvincular una empresa que ya tiene servicios asociados a este cliente.',
            ]);
        }
    }

    private function pivotPayload(
        array $organizationIds,
        int $userId,
    ): array {
        $payload = [];

        foreach ($organizationIds as $organizationId) {
            $payload[(int) $organizationId] = [
                'is_active' => true,
                'created_by' => $userId,
            ];
        }

        return $payload;
    }

    private function redirectScope(
        array $validated,
        array $organizationIds,
    ): int {
        $scope = isset($validated['scope'])
            ? (int) $validated['scope']
            : 0;

        return in_array($scope, $organizationIds, true)
            ? $scope
            : $organizationIds[0];
    }

    private function attributes(
        Request $request,
        array $validated,
    ): array {
        unset(
            $validated['organization_ids'],
            $validated['scope'],
        );

        foreach ([
            'legal_name',
            'tax_id',
            'contact_name',
            'email',
            'phone',
            'drive_url',
            'notes',
        ] as $field) {
            $value = trim((string) ($validated[$field] ?? ''));
            $validated[$field] = $value !== '' ? $value : null;
        }

        $validated['name'] = trim((string) $validated['name']);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
