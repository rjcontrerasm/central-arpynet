<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClientOpsActionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        $client = Client::query()->create(
            $this->attributes($request, $validated),
        );

        return redirect()
            ->route('client-ops.index', [
                'scope' => $organizationId,
                'client' => $client->id,
            ])
            ->with('client_success', 'Cliente creado.');
    }

    public function update(
        Request $request,
        Client $client,
    ): RedirectResponse {
        abort_unless(
            $request->user()->canWriteToOrganization(
                (int) $client->organization_id,
            ),
            403,
        );

        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        if ($organizationId !== (int) $client->organization_id) {
            throw ValidationException::withMessages([
                'organization_id' =>
                    'El ámbito de un cliente existente no se cambia desde Clientes.',
            ]);
        }

        $client->forceFill(
            $this->attributes($request, $validated),
        )->save();

        return redirect()
            ->route('client-ops.index', [
                'scope' => $organizationId,
                'client' => $client->id,
            ])
            ->with('client_success', 'Cliente actualizado.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer'],
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

    private function attributes(
        Request $request,
        array $validated,
    ): array {
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
