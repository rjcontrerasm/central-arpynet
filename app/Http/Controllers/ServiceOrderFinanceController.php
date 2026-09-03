<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Support\GlobalUndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceOrderFinanceController extends Controller
{
    public function update(
        Request $request,
        ServiceOrder $serviceOrder,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'invoice_number' => [
                'nullable',
                'string',
                'max:100',
            ],
            'invoice_date' => [
                'nullable',
                'date',
            ],
            'invoice_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'invoice_due_date' => [
                'nullable',
                'date',
            ],
            'paid_date' => [
                'nullable',
                'date',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
            'includes_tax' => [
                'nullable',
                'boolean',
            ],
            'scope' => [
                'nullable',
                'integer',
            ],
            'filter_stage' => [
                'nullable',
                'string',
                'max:40',
            ],
            'focus' => [
                'nullable',
                'in:attention,all',
            ],
            'finance' => [
                'nullable',
                'in:all,pending_invoice,receivable,overdue,paid',
            ],
            'q' => [
                'nullable',
                'string',
                'max:120',
            ],
        ]);

        $userId = $request->user()->id;

        $allowed = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $userId,
            )
            ->where(
                'organization_id',
                $serviceOrder->organization_id,
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
            $undo->captureServiceOrder(
                $serviceOrder,
            );

        $data = [
            'amount' =>
                $validated['amount']
                ?? null,
            'invoice_number' => trim(
                (string) (
                    $validated['invoice_number']
                    ?? ''
                ),
            ) ?: null,
            'invoice_date' =>
                $validated['invoice_date']
                ?? null,
            'invoice_amount' =>
                $validated['invoice_amount']
                ?? null,
            'invoice_due_date' =>
                $validated['invoice_due_date']
                ?? null,
            'paid_date' =>
                $validated['paid_date']
                ?? null,
            'currency' => strtoupper(
                $validated['currency'],
            ),
            'includes_tax' =>
                (bool) (
                    $validated['includes_tax']
                    ?? false
                ),
            'last_activity_at' => now(),
        ];

        if (
            $data['paid_date']
            && ! in_array(
                $serviceOrder->stage,
                ['paid', 'closed'],
                true,
            )
        ) {
            $data['stage'] = 'paid';
        } elseif (
            ! $data['paid_date']
            && (
                $data['invoice_number']
                || $data['invoice_date']
                || (float) (
                    $data['invoice_amount']
                    ?? 0
                ) > 0
            )
            && ! in_array(
                $serviceOrder->stage,
                ['paid', 'closed'],
                true,
            )
        ) {
            $data['stage'] =
                'invoiced';
        }

        $serviceOrder
            ->forceFill($data)
            ->save();

        $undo->rememberServiceOrderMutation(
            $request->user(),
            $serviceOrder,
            $before,
            'Datos financieros actualizados',
            route(
                'service-orders-ops.show',
                $filters,
                false,
            ),
        );

        return redirect()
            ->route(
                'service-orders-ops.show',
                $filters,
            )
            ->with(
                'ops_success',
                'Datos financieros actualizados.',
            );
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
            $validated['filter_stage']
                ?? null
        )) {
            $params['stage'] =
                $validated[
                    'filter_stage'
                ];
        }

        if (! empty(
            $validated['focus']
                ?? null
        )) {
            $params['focus'] =
                $validated['focus'];
        }

        if (! empty(
            $validated['finance']
                ?? null
        )) {
            $params['finance'] =
                $validated['finance'];
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
