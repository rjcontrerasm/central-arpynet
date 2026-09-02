<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditHistoryController extends Controller
{
    private const TYPES = [
        'Task' => 'Tareas',
        'Project' => 'Proyectos',
        'ServiceOrder' => 'Servicios',
        'RecurringObligation' => 'Obligaciones',
        'ObligationOccurrence' => 'Vencimientos',
        'Incident' => 'Incidentes',
        'Client' => 'Clientes',
        'Organization' => 'Ámbitos',
    ];

    public function index(
        Request $request,
    ): View {
        $user = $request->user();

        $organizationIds = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $user->id,
            )
            ->where(
                'is_active',
                true,
            )
            ->pluck('organization_id');

        $validated = $request->validate([
            'scope' => [
                'nullable',
                'integer',
            ],
            'event' => [
                'nullable',
                'in:created,updated,deleted',
            ],
            'type' => [
                'nullable',
                'string',
            ],
            'q' => [
                'nullable',
                'string',
                'max:120',
            ],
            'days' => [
                'nullable',
                'in:7,30,90,all',
            ],
        ]);

        $scope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if (
            $scope !== null
            && ! $organizationIds->contains($scope)
        ) {
            abort(403);
        }

        $type = $validated['type'] ?? null;

        if (
            $type !== null
            && ! array_key_exists(
                $type,
                self::TYPES,
            )
        ) {
            abort(422);
        }

        $days = $validated['days'] ?? '30';

        $query = AuditLog::query()
            ->with([
                'organization:id,name',
                'user:id,name,email',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            );

        if ($scope !== null) {
            $query->where(
                'organization_id',
                $scope,
            );
        }

        if (! empty($validated['event'])) {
            $query->where(
                'event',
                $validated['event'],
            );
        }

        if ($type !== null) {
            $query->where(
                'subject_type',
                $type,
            );
        }

        if (! empty($validated['q'])) {
            $search = trim(
                $validated['q'],
            );

            $query->where(
                'subject_label',
                'like',
                '%'.$search.'%',
            );
        }

        if ($days !== 'all') {
            $query->where(
                'occurred_at',
                '>=',
                now()->subDays(
                    (int) $days,
                ),
            );
        }

        $total = (clone $query)->count();

        $today = (clone $query)
            ->whereDate(
                'occurred_at',
                today(),
            )
            ->count();

        $changes = $query
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        $organizations = DB::table(
            'organizations',
        )
            ->whereIn(
                'id',
                $organizationIds,
            )
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        return view(
            'audit-history',
            [
                'changes' => $changes,
                'organizations' =>
                    $organizations,
                'types' => self::TYPES,
                'filters' => [
                    'scope' => $scope,
                    'event' =>
                        $validated['event']
                            ?? null,
                    'type' => $type,
                    'q' =>
                        $validated['q']
                            ?? '',
                    'days' => $days,
                ],
                'total' => $total,
                'todayCount' => $today,
            ],
        );
    }
}
