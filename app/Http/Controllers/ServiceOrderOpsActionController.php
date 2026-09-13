<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Support\GlobalUndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceOrderOpsActionController extends Controller
{
    public function update(
        Request $request,
        ServiceOrder $serviceOrder,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $validated = $request->validate([
            'stage' => [
                'required',
                'string',
            ],
            'next_action' => [
                'nullable',
                'string',
                'max:255',
            ],
            'next_action_at' => [
                'nullable',
                'date',
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
            'q' => [
                'nullable',
                'string',
                'max:120',
            ],
        ]);

        abort_unless(
            $request->user()->canWriteToOrganization(
                (int) $serviceOrder->organization_id,
            ),
            403,
        );

        $stageOptions =
            ServiceOrder::stageOptions();

        abort_unless(
            array_key_exists(
                $validated['stage'],
                $stageOptions,
            ),
            422,
        );

        $filters = $this->filters(
            $request,
            $validated,
        );

        $before =
            $undo->captureServiceOrder(
                $serviceOrder,
            );

        $serviceOrder->forceFill([
            'stage' =>
                $validated['stage'],
            'next_action' => trim(
                (string) (
                    $validated['next_action']
                    ?? ''
                ),
            ) ?: null,
            'next_action_at' =>
                $validated['next_action_at']
                ?? null,
            'last_activity_at' => now(),
        ])->save();

        $undo->rememberServiceOrderMutation(
            $request->user(),
            $serviceOrder,
            $before,
            'Servicio actualizado',
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
                'Orden actualizada.',
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
            abort_unless(
                $request->user()->canAccessOrganization((int) $scope),
                403,
            );

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
