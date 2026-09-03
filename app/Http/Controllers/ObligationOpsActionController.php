<?php

namespace App\Http\Controllers;

use App\Models\ObligationOccurrence;
use App\Support\GlobalUndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ObligationOpsActionController extends Controller
{
    public function update(
        Request $request,
        ObligationOccurrence $obligationOccurrence,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => [
                'required',
                'in:paid,skipped,pending',
            ],
            'actual_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'paid_date' => [
                'nullable',
                'date',
            ],
            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'receipt_url' => [
                'nullable',
                'url',
                'max:2048',
            ],
            'scope' => [
                'nullable',
                'integer',
            ],
            'focus' => [
                'nullable',
                'in:attention,all,overdue,today,upcoming,pending,paid,skipped',
            ],
            'q' => [
                'nullable',
                'string',
                'max:120',
            ],
        ]);

        $userId =
            $request->user()->id;

        $allowed = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $userId,
            )
            ->where(
                'organization_id',
                $obligationOccurrence
                    ->organization_id,
            )
            ->where(
                'is_active',
                true,
            )
            ->exists();

        abort_unless($allowed, 403);

        $filters = $this->filters(
            $request,
            $validated,
        );

        $before =
            $undo->captureObligationOccurrence(
                $obligationOccurrence,
            );

        match ($validated['action']) {
            'paid' => $this->markPaid(
                $obligationOccurrence,
                $validated,
            ),
            'skipped' => $this->markSkipped(
                $obligationOccurrence,
            ),
            'pending' => $this->reopen(
                $obligationOccurrence,
            ),
        };

        $label = match (
            $validated['action']
        ) {
            'paid' =>
                'Pago registrado',
            'skipped' =>
                'Vencimiento omitido',
            'pending' =>
                'Vencimiento reabierto',
        };

        $undo->rememberObligationMutation(
            $request->user(),
            $obligationOccurrence,
            $before,
            $label,
            route(
                'obligation-ops.show',
                $filters,
                false,
            ),
        );

        return redirect()
            ->route(
                'obligation-ops.show',
                $filters,
            )
            ->with(
                'obligation_success',
                $label.'.',
            );
    }

    private function markPaid(
        ObligationOccurrence $occurrence,
        array $validated,
    ): void {
        $actual =
            $validated['actual_amount']
            ?? $occurrence
                ->expected_amount;

        $occurrence->forceFill([
            'status' => 'paid',
            'actual_amount' => $actual,
            'paid_date' =>
                $validated['paid_date']
                ?? now()->toDateString(),
            'payment_reference' => trim(
                (string) (
                    $validated['payment_reference']
                    ?? ''
                ),
            ) ?: null,
            'receipt_url' => trim(
                (string) (
                    $validated['receipt_url']
                    ?? ''
                ),
            ) ?: null,
        ])->save();
    }

    private function markSkipped(
        ObligationOccurrence $occurrence,
    ): void {
        $occurrence->forceFill([
            'status' => 'skipped',
            'actual_amount' => null,
            'paid_date' => null,
            'payment_reference' => null,
            'receipt_url' => null,
        ])->save();
    }

    private function reopen(
        ObligationOccurrence $occurrence,
    ): void {
        $occurrence->forceFill([
            'status' => 'pending',
            'actual_amount' => null,
            'paid_date' => null,
            'payment_reference' => null,
            'receipt_url' => null,
        ])->save();
    }

    private function filters(
        Request $request,
        array $validated,
    ): array {
        $params = [];
        $scope =
            $validated['scope']
            ?? null;

        if ($scope) {
            $allowed = DB::table(
                'organization_user',
            )
                ->where(
                    'user_id',
                    $request->user()->id,
                )
                ->where(
                    'organization_id',
                    $scope,
                )
                ->where(
                    'is_active',
                    true,
                )
                ->exists();

            abort_unless($allowed, 403);

            $params['scope'] = $scope;
        }

        if (! empty(
            $validated['focus']
                ?? null
        )) {
            $params['focus'] =
                $validated['focus'];
        }

        $q = trim(
            (string) (
                $validated['q']
                ?? ''
            ),
        );

        if ($q !== '') {
            $params['q'] = $q;
        }

        return $params;
    }
}
