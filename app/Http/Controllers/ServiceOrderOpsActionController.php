<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceOrderOpsActionController extends Controller
{
    public function update(
        Request $request,
        ServiceOrder $serviceOrder,
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

        $userId = $request->user()->id;

        $allowed = DB::table('organization_user')
            ->where('user_id', $userId)
            ->where(
                'organization_id',
                $serviceOrder->organization_id,
            )
            ->where('is_active', true)
            ->exists();

        abort_unless($allowed, 403);

        $stageOptions = ServiceOrder::stageOptions();

        abort_unless(
            array_key_exists(
                $validated['stage'],
                $stageOptions,
            ),
            422,
        );

        $serviceOrder->forceFill([
            'stage' => $validated['stage'],
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

        return redirect()
            ->route(
                'service-orders-ops.show',
                $this->filters(
                    $request,
                    $validated,
                ),
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

        $scope = $validated['scope'] ?? null;

        if ($scope) {
            $allowed = DB::table('organization_user')
                ->where(
                    'user_id',
                    $request->user()->id,
                )
                ->where('organization_id', $scope)
                ->where('is_active', true)
                ->exists();

            abort_unless($allowed, 403);
            $params['scope'] = $scope;
        }

        if (! empty($validated['filter_stage'])) {
            $params['stage'] =
                $validated['filter_stage'];
        }

        if (! empty($validated['focus'])) {
            $params['focus'] =
                $validated['focus'];
        }

        $q = trim(
            (string) ($validated['q'] ?? ''),
        );

        if ($q !== '') {
            $params['q'] = $q;
        }

        return $params;
    }
}
