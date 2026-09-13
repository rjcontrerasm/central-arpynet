<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Support\Incident360State;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class Incident360Controller extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
            'severity' => [
                'nullable',
                'string',
                Rule::in(array_keys(Incident::severityOptions())),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(Incident::statusOptions())),
            ],
            'focus' => [
                'nullable',
                'string',
                Rule::in(['open', 'attention', 'resolved', 'all']),
            ],
            'q' => ['nullable', 'string', 'max:120'],
            'incident' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $organizations = $user->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->orderBy('organizations.name')
            ->get(['organizations.id', 'organizations.name']);

        $organizationIds = $organizations
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $selectedScope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if (
            $selectedScope
            && ! $organizationIds->contains($selectedScope)
        ) {
            abort(403);
        }

        $now = CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $all = Incident::query()
            ->visibleTo($user)
            ->with([
                'organization',
                'client',
                'serviceOrder',
                'project',
                'assignee',
            ])
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->orderByDesc('detected_at')
            ->limit(500)
            ->get()
            ->map(fn (Incident $incident): array => [
                'incident' => $incident,
                'state' => Incident360State::evaluate(
                    $incident,
                    $now,
                ),
            ]);

        $focus = $validated['focus'] ?? 'open';
        $severity = $validated['severity'] ?? null;
        $status = $validated['status'] ?? null;
        $search = trim((string) ($validated['q'] ?? ''));

        $rows = $all
            ->filter(function (array $row) use (
                $focus,
                $severity,
                $status,
                $search,
            ): bool {
                $incident = $row['incident'];
                $state = $row['state'];

                if ($severity && $incident->severity !== $severity) {
                    return false;
                }

                if ($status && $incident->status !== $status) {
                    return false;
                }

                if (
                    $focus === 'open'
                    && ! $state['active']
                ) {
                    return false;
                }

                if (
                    $focus === 'attention'
                    && ! $state['needs_attention']
                ) {
                    return false;
                }

                if (
                    $focus === 'resolved'
                    && ! in_array(
                        $incident->status,
                        ['resolved', 'closed'],
                        true,
                    )
                ) {
                    return false;
                }

                if ($search !== '') {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $incident->title,
                        $incident->affected_service,
                        $incident->external_id,
                        $incident->client?->name,
                        $incident->organization?->name,
                        $incident->serviceOrder?->title,
                        $incident->project?->name,
                    ])));

                    if (! str_contains(
                        $haystack,
                        mb_strtolower($search),
                    )) {
                        return false;
                    }
                }

                return true;
            })
            ->sort(function (array $a, array $b): int {
                $rank = $b['state']['rank'] <=> $a['state']['rank'];

                if ($rank !== 0) {
                    return $rank;
                }

                return ($b['incident']->detected_at?->getTimestamp() ?? 0)
                    <=> ($a['incident']->detected_at?->getTimestamp() ?? 0);
            })
            ->values();

        $selectedId = isset($validated['incident'])
            ? (int) $validated['incident']
            : null;

        $selected = $selectedId
            ? $all->first(
                fn (array $row): bool =>
                    (int) $row['incident']->id === $selectedId,
            )
            : $rows->first();

        if ($selectedId && ! $selected) {
            abort(404);
        }

        return view('incident-360', [
            'organizations' => $organizations,
            'selectedScope' => $selectedScope,
            'focus' => $focus,
            'selectedSeverity' => $severity,
            'selectedStatus' => $status,
            'search' => $search,
            'rows' => $rows,
            'selected' => $selected,
            'severityOptions' => Incident::severityOptions(),
            'statusOptions' => Incident::statusOptions(),
            'summary' => $this->summary($all, $now),
        ]);
    }

    private function summary(
        Collection $rows,
        CarbonImmutable $now,
    ): array {
        $active = $rows->filter(
            fn (array $row): bool => $row['state']['active'],
        );

        return [
            'open' => $active->count(),
            'critical' => $active->filter(
                fn (array $row): bool =>
                    $row['incident']->severity === 'critical',
            )->count(),
            'response_breached' => $active->filter(
                fn (array $row): bool =>
                    $row['state']['response_sla']['status'] === 'breached',
            )->count(),
            'resolution_breached' => $active->filter(
                fn (array $row): bool =>
                    $row['state']['resolution_sla']['status'] === 'breached',
            )->count(),
            'monitoring' => $active->filter(
                fn (array $row): bool =>
                    $row['incident']->status === 'monitoring',
            )->count(),
            'resolved_7d' => $rows->filter(
                fn (array $row): bool =>
                    $row['incident']->resolved_at
                    && $row['incident']->resolved_at->gte(
                        $now->subDays(7),
                    ),
            )->count(),
        ];
    }
}
