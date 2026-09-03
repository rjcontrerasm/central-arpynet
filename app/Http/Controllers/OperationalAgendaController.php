<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\OperationalAgendaBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperationalAgendaController extends Controller
{
    public function show(Request $request, OperationalAgendaBuilder $agenda): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'scope' => ['nullable', 'integer'],
        ]);

        $timezone = config('app.timezone', 'America/Lima');
        $date = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();

        $ids = DB::table('organization_user')
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        $organizations = Organization::query()->whereIn('id', $ids)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name']);

        $scope = isset($validated['scope']) ? (int) $validated['scope'] : null;
        if ($scope && ! $organizations->contains('id', $scope)) {
            abort(403);
        }

        return view('operational-agenda', [
            ...$agenda->build($request->user(), $date, $scope),
            'organizations' => $organizations,
            'scope' => $scope,
            'previousDate' => $date->subDay()->toDateString(),
            'nextDate' => $date->addDay()->toDateString(),
            'todayDate' => CarbonImmutable::now($timezone)->toDateString(),
        ]);
    }
}
