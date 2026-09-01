<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Organization;
use App\Models\ObligationOccurrence;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyOpsController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $organizationIds = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');


        $organizations = Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $timezone = config('app.timezone', 'America/Lima');
        $now = CarbonImmutable::now($timezone);
        $todayStart = $now->startOfDay();
        $todayEnd = $now->endOfDay();
        $weekEnd = $now->addDays(7)->endOfDay();

        $baseTasks = Task::query()
            ->with('organization')
            ->whereIn('organization_id', $organizationIds)
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday'],
            );

        $overdueCount = (clone $baseTasks)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $todayStart)
            ->count();

        $todayCount = (clone $baseTasks)
            ->whereBetween('due_at', [$todayStart, $todayEnd])
            ->count();

        $weekCount = (clone $baseTasks)
            ->where('due_at', '>', $todayEnd)
            ->where('due_at', '<=', $weekEnd)
            ->count();

        $noDateCount = (clone $baseTasks)
            ->whereNull('due_at')
            ->count();

        $attentionTasks = (clone $baseTasks)
            ->orderByRaw(
                "CASE
                    WHEN due_at IS NOT NULL AND due_at < ? THEN 0
                    WHEN due_at IS NOT NULL AND due_at <= ? THEN 1
                    WHEN urgency = 'high' THEN 2
                    WHEN impact = 'high' THEN 3
                    WHEN due_at IS NULL THEN 5
                    ELSE 4
                END",
                [$todayStart, $todayEnd],
            )
            ->orderByRaw(
                "CASE WHEN due_at IS NULL THEN 1 ELSE 0 END",
            )
            ->orderBy('due_at')
            ->latest('id')
            ->limit(8)
            ->get();

        $upcomingObligations = ObligationOccurrence::query()
            ->with(['organization', 'obligation'])
            ->whereIn('organization_id', $organizationIds)
            ->where('status', 'pending')
            ->where(
                'due_date',
                '>=',
                $todayStart->toDateString(),
            )
            ->where(
                'due_date',
                '<=',
                $weekEnd->toDateString(),
            )
            ->orderBy('due_date')
            ->limit(6)
            ->get();

        $openIncidents = Incident::query()
            ->with('organization')
            ->whereIn('organization_id', $organizationIds)
            ->whereNotIn(
                'status',
                ['resolved', 'closed', 'cancelled'],
            )
            ->orderByRaw(
                "CASE severity
                    WHEN 'critical' THEN 0
                    WHEN 'high' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'low' THEN 3
                    ELSE 4
                END",
            )
            ->latest('id')
            ->limit(5)
            ->get();

        return view(
            'daily-ops',
            compact(
                'now',
                'overdueCount',
                'todayCount',
                'weekCount',
                'noDateCount',
                'attentionTasks',
                'upcomingObligations',
                'openIncidents',
                'organizations',
            ),
        );
    }
}
