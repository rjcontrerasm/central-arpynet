<?php

namespace App\Support;

use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ClientHealthScoreBuilder
{
    public function build(
        Collection $organizationIds,
        ?int $selectedScope,
        CarbonImmutable $now,
    ): Collection {
        return ServiceOrder::query()
            ->with([
                'client',
                'organization',
            ])
            ->whereIn(
                'organization_id',
                $organizationIds,
            )
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->whereNotNull('client_id')
            ->whereNotIn(
                'stage',
                ['paid', 'closed', 'cancelled'],
            )
            ->get()
            ->map(function (ServiceOrder $order) use ($now): array {
                return [
                    'order' => $order,
                    'health' => ServiceHealthScore::evaluate(
                        $order,
                        $now,
                    ),
                ];
            })
            ->filter(
                fn (array $row): bool =>
                    $row['health']['applicable']
                    && $row['health']['score'] !== null,
            )
            ->groupBy(
                fn (array $row): string =>
                    (string) $row['order']->client_id,
            )
            ->map(
                fn (Collection $rows): array =>
                    $this->clientSummary($rows),
            )
            ->sortBy([
                ['score', 'asc'],
                ['client_name', 'asc'],
            ])
            ->values();
    }

    private function clientSummary(Collection $rows): array
    {
        $scores = $rows->pluck('health.score');
        $average = round((float) $scores->average(), 1);
        $worst = (int) $scores->min();
        $score = (int) round(
            ($average * 0.75)
            + ($worst * 0.25),
        );

        $status = ServiceHealthScore::status($score);

        $ordered = $rows
            ->sortBy('health.score')
            ->values();

        $first = $ordered->first();
        $critical = $rows->filter(
            fn (array $row): bool =>
                $row['health']['status'] === 'critical',
        );
        $risk = $rows->filter(
            fn (array $row): bool =>
                in_array(
                    $row['health']['status'],
                    ['risk', 'critical'],
                    true,
                ),
        );
        $overdue = $rows->filter(
            fn (array $row): bool =>
                ($row['health']['financial']['status'] ?? null)
                    === 'overdue',
        );

        $reasons = $ordered
            ->flatMap(
                fn (array $row): array =>
                    $row['health']['reasons'],
            )
            ->unique()
            ->take(4)
            ->values();

        return [
            'client_id' => $first['order']->client_id,
            'client_name' => $first['order']->client?->name
                ?: 'Sin cliente',
            'organization_id' => $first['order']->organization_id,
            'organization_name' => $first['order']->organization?->name
                ?: 'Sin ámbito',
            'score' => $score,
            'average_score' => $average,
            'worst_score' => $worst,
            'status' => $status,
            'label' => ServiceHealthScore::label($status),
            'css' => ServiceHealthScore::css($status),
            'services_count' => $rows->count(),
            'risk_count' => $risk->count(),
            'critical_count' => $critical->count(),
            'overdue_count' => $overdue->count(),
            'reasons' => $reasons,
            'worst_service' => [
                'id' => $first['order']->id,
                'title' => $first['order']->title,
                'score' => $first['health']['score'],
                'label' => $first['health']['label'],
            ],
            'services' => $ordered
                ->take(5)
                ->map(
                    fn (array $row): array => [
                        'id' => $row['order']->id,
                        'title' => $row['order']->title,
                        'stage' => $row['order']->stage,
                        'score' => $row['health']['score'],
                        'status' => $row['health']['status'],
                        'label' => $row['health']['label'],
                    ],
                )
                ->values(),
        ];
    }
}
