<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\GlobalUndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceOrderFrontActionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        $this->validateRelations(
            $organizationId,
            (int) $validated['client_id'],
            isset($validated['assigned_to'])
                ? (int) $validated['assigned_to']
                : $request->user()->id,
        );

        $attributes = $this->attributes($request, $validated);
        $attributes['created_by'] = $request->user()->id;
        $attributes['assigned_to'] ??= $request->user()->id;

        $serviceOrder = ServiceOrder::query()->create($attributes);

        return redirect()
            ->route('service-order-front.edit', $serviceOrder)
            ->with('service_front_success', 'Servicio creado.');
    }

    public function update(
        Request $request,
        ServiceOrder $serviceOrder,
        GlobalUndoService $undo,
    ): RedirectResponse {
        abort_unless(
            $request->user()->canWriteToOrganization(
                (int) $serviceOrder->organization_id,
            ),
            403,
        );

        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        if ($organizationId !== (int) $serviceOrder->organization_id) {
            throw ValidationException::withMessages([
                'organization_id' =>
                    'El ámbito de un servicio existente no se cambia desde esta ficha.',
            ]);
        }

        $this->validateRelations(
            $organizationId,
            (int) $validated['client_id'],
            isset($validated['assigned_to'])
                ? (int) $validated['assigned_to']
                : null,
        );

        $before = $undo->captureServiceOrder($serviceOrder);

        $serviceOrder->fill(
            $this->attributes($request, $validated),
        )->save();

        $undo->rememberServiceOrderMutation(
            $request->user(),
            $serviceOrder,
            $before,
            'Servicio actualizado',
            route('service-order-front.edit', $serviceOrder, false),
        );

        return redirect()
            ->route('service-order-front.edit', $serviceOrder)
            ->with('service_front_success', 'Servicio actualizado.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer'],
            'client_id' => ['required', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'stage' => [
                'required',
                Rule::in(array_keys(ServiceOrder::stageOptions())),
            ],
            'quotation_number' => ['nullable', 'string', 'max:80'],
            'quotation_date' => ['nullable', 'date'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'order_received_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'report_submitted_date' => ['nullable', 'date'],
            'conformity_date' => ['nullable', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'invoice_due_date' => ['nullable', 'date'],
            'paid_date' => ['nullable', 'date'],
            'closed_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'invoice_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'in:PEN,USD,EUR'],
            'includes_tax' => ['nullable', 'boolean'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_at' => ['nullable', 'date'],
            'drive_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function validateRelations(
        int $organizationId,
        int $clientId,
        ?int $assignedTo,
    ): void {
        $clientIsValid = Client::query()
            ->whereKey($clientId)
            ->forOrganization($organizationId)
            ->where('is_active', true)
            ->exists();

        if (! $clientIsValid) {
            throw ValidationException::withMessages([
                'client_id' =>
                    'El cliente debe estar activo y asociado al ámbito del servicio.',
            ]);
        }

        if ($assignedTo === null) {
            return;
        }

        $assigneeIsValid = User::query()
            ->whereKey($assignedTo)
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn ($query) => $query
                    ->where('organizations.id', $organizationId)
                    ->where('organizations.is_active', true)
                    ->where('organization_user.is_active', true)
                    ->whereIn(
                        'organization_user.role',
                        ['owner', 'admin', 'member'],
                    ),
            )
            ->exists();

        if (! $assigneeIsValid) {
            throw ValidationException::withMessages([
                'assigned_to' =>
                    'El responsable debe tener acceso operativo al mismo ámbito.',
            ]);
        }
    }

    private function attributes(
        Request $request,
        array $validated,
    ): array {
        foreach ([
            'title', 'description', 'quotation_number', 'order_number',
            'invoice_number', 'next_action', 'drive_url', 'notes',
        ] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = trim((string) $validated[$field]);
            $validated[$field] = $value !== '' ? $value : null;
        }

        $validated['title'] = trim((string) $validated['title']);
        $validated['currency'] = strtoupper($validated['currency']);
        $validated['includes_tax'] = $request->boolean('includes_tax');

        return $validated;
    }
}
