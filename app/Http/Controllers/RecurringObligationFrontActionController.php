<?php

namespace App\Http\Controllers;

use App\Models\RecurringObligation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecurringObligationFrontActionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        $attributes = $this->attributes($request, $validated);
        $attributes['created_by'] = $request->user()->id;

        $obligation = RecurringObligation::query()->create($attributes);

        return redirect()
            ->route('recurring-obligation-front.edit', $obligation)
            ->with(
                'recurring_obligation_success',
                'Obligación recurrente creada.',
            );
    }

    public function update(
        Request $request,
        RecurringObligation $recurringObligation,
    ): RedirectResponse {
        abort_unless(
            $request->user()->canWriteToOrganization(
                (int) $recurringObligation->organization_id,
            ),
            403,
        );

        $validated = $this->validatePayload($request);
        $organizationId = (int) $validated['organization_id'];

        if ($organizationId !== (int) $recurringObligation->organization_id) {
            throw ValidationException::withMessages([
                'organization_id' =>
                    'El ámbito de una obligación existente no se cambia desde esta ficha.',
            ]);
        }

        $recurringObligation->fill(
            $this->attributes($request, $validated),
        )->save();

        return redirect()
            ->route('recurring-obligation-front.edit', $recurringObligation)
            ->with(
                'recurring_obligation_success',
                'Obligación recurrente actualizada.',
            );
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'organization_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'category' => [
                'required',
                Rule::in(array_keys(RecurringObligation::categoryOptions())),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'frequency' => [
                'required',
                Rule::in(array_keys(RecurringObligation::frequencyOptions())),
            ],
            'anchor_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:anchor_date'],
            'expected_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'in:PEN,USD,EUR'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'is_critical' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'provider' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'drive_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function attributes(Request $request, array $validated): array
    {
        foreach ([
            'name',
            'description',
            'provider',
            'reference',
            'drive_url',
            'notes',
        ] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = trim((string) $validated[$field]);
            $validated[$field] = $value !== '' ? $value : null;
        }

        $validated['name'] = trim((string) $validated['name']);
        $validated['currency'] = strtoupper($validated['currency']);
        $validated['is_critical'] = $request->boolean('is_critical');
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
