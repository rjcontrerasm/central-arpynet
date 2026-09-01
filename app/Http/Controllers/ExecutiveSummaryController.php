<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\ExecutiveSummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExecutiveSummaryController extends Controller
{
    public function show(
        Request $request,
        ExecutiveSummaryBuilder $builder,
    ): View {
        $validated = $request->validate([
            'scope' => [
                'nullable',
                'integer',
            ],
            'period' => [
                'nullable',
                'in:today,week',
            ],
        ]);

        $organizationIds = DB::table('organization_user')
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->where('is_active', true)
            ->pluck('organization_id');

        $organizations = Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedScope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if (
            $selectedScope
            && ! $organizationIds->contains(
                $selectedScope,
            )
        ) {
            abort(403);
        }

        $period = $validated['period']
            ?? 'today';

        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $summary = $builder->build(
            $organizationIds,
            $selectedScope,
            $period,
            $now,
        );

        return view(
            'executive-summary',
            compact(
                'now',
                'organizations',
                'selectedScope',
                'period',
                'summary',
            ),
        );
    }
}
