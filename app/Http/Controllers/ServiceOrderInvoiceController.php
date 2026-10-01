<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceOrderInvoiceController extends Controller
{
    public function store(
        Request $request,
        ServiceOrder $serviceOrder,
    ): RedirectResponse {
        $this->authorizeOrder($request, $serviceOrder);

        $validated = $this->validatePayload($request);
        $this->validateExecutionOrder($serviceOrder, $validated);

        $attributes = $this->attributes($validated);
        $attributes['created_by'] = $request->user()->id;

        $serviceOrder->invoices()->create($attributes);

        return redirect()
            ->route('service-order-front.edit', $serviceOrder)
            ->withFragment('facturacion')
            ->with('service_front_success', 'Factura agregada.');
    }

    public function update(
        Request $request,
        ServiceOrder $serviceOrder,
        ServiceOrderInvoice $invoice,
    ): RedirectResponse {
        $this->authorizeOrder($request, $serviceOrder);
        abort_unless(
            (int) $invoice->service_order_id === (int) $serviceOrder->id,
            404,
        );

        $validated = $this->validatePayload($request);
        $this->validateExecutionOrder($serviceOrder, $validated);

        $invoice->fill(
            $this->attributes($validated),
        )->save();

        return redirect()
            ->route('service-order-front.edit', $serviceOrder)
            ->withFragment('facturacion')
            ->with('service_front_success', 'Factura actualizada.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'execution_order_id' => ['nullable', 'integer'],
            'number' => ['nullable', 'string', 'max:120'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'paid_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status' => [
                'required',
                Rule::in(array_keys(
                    ServiceOrderInvoice::statusOptions(),
                )),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function validateExecutionOrder(
        ServiceOrder $serviceOrder,
        array $validated,
    ): void {
        $executionOrderId = isset($validated['execution_order_id'])
            ? (int) $validated['execution_order_id']
            : null;

        if (! $executionOrderId) {
            return;
        }

        if (
            ! $serviceOrder->executionOrders()
                ->whereKey($executionOrderId)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'execution_order_id' =>
                    'La orden seleccionada no pertenece a este servicio.',
            ]);
        }
    }

    private function attributes(array $validated): array {
        foreach (['number', 'notes'] as $field) {
            $value = trim((string) ($validated[$field] ?? ''));
            $validated[$field] = $value !== '' ? $value : null;
        }

        if (
            ($validated['status'] ?? null) === 'paid'
            && empty($validated['paid_date'])
        ) {
            $validated['paid_date'] = now()->toDateString();
        }

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
