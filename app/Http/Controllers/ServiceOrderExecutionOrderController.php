<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderExecutionOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceOrderExecutionOrderController extends Controller
{
    public function store(
        Request $request,
        ServiceOrder $serviceOrder,
    ): RedirectResponse {
        $this->authorizeOrder($request, $serviceOrder);

        $validated = $this->validatePayload($request);

        $serviceOrder->executionOrders()->create(
            $this->attributes($request, $validated),
        );

        return redirect()
            ->route('service-order-front.edit', $serviceOrder)
            ->withFragment('ejecucion-contractual')
            ->with('service_front_success', 'Orden de ejecución agregada.');
    }

    public function update(
        Request $request,
        ServiceOrder $serviceOrder,
        ServiceOrderExecutionOrder $executionOrder,
    ): RedirectResponse {
        $this->authorizeOrder($request, $serviceOrder);
        abort_unless(
            (int) $executionOrder->service_order_id === (int) $serviceOrder->id,
            404,
        );

        $validated = $this->validatePayload($request);

        $executionOrder->fill(
            $this->attributes($request, $validated),
        )->save();

        return redirect()
            ->route('service-order-front.edit', $serviceOrder)
            ->withFragment('ejecucion-contractual')
            ->with('service_front_success', 'Orden de ejecución actualizada.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'document_type' => [
                'required',
                Rule::in(array_keys(
                    ServiceOrderExecutionOrder::documentTypeOptions(),
                )),
            ],
            'document_number' => ['nullable', 'string', 'max:120'],
            'issued_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status' => [
                'required',
                Rule::in(array_keys(
                    ServiceOrderExecutionOrder::statusOptions(),
                )),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function attributes(
        Request $request,
        array $validated,
    ): array {
        foreach (['document_number', 'notes'] as $field) {
            $value = trim((string) ($validated[$field] ?? ''));
            $validated[$field] = $value !== '' ? $value : null;
        }

        $validated['created_by'] ??= $request->user()->id;

        return $validated;
    }

    private function authorizeOrder(
        Request $request,
        ServiceOrder $serviceOrder,
    ): void {
        abort_unless(
            $request->user()->canWriteToOrganization(
                (int) $serviceOrder->organization_id,
            ),
            403,
        );
    }
}
