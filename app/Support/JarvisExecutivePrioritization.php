<?php

namespace App\Support;

use Illuminate\Support\Collection;

class JarvisExecutivePrioritization
{
    public function __construct(
        private readonly JarvisOperationalIntelligence $intelligence,
    ) {
    }

    public function analyze(
        array $organizationContexts,
    ): array {
        $organizations = collect(
            $organizationContexts,
        )
            ->filter(
                fn (mixed $context): bool =>
                    is_array($context)
                    && isset(
                        $context['id'],
                        $context['name'],
                    ),
            )
            ->map(
                function (array $context): array {
                    $analysis =
                        $this->intelligence
                            ->analyze($context);

                    $priorities = collect(
                        $analysis['priorities']
                            ?? [],
                    )
                        ->map(
                            fn (array $priority): array =>
                                $priority + [
                                    'organization_id' =>
                                        (int) $context['id'],
                                    'organization' =>
                                        (string) $context['name'],
                                    'organization_pressure' =>
                                        (int) $analysis[
                                            'pressure_score'
                                        ],
                                    'scope_url' =>
                                        (string) (
                                            $context['url']
                                            ?? '#'
                                        ),
                                ],
                        )
                        ->values()
                        ->all();

                    $topPriority =
                        $priorities[0]
                        ?? null;

                    return [
                        'id' =>
                            (int) $context['id'],
                        'name' =>
                            (string) $context['name'],
                        'pressure_score' =>
                            (int) $analysis[
                                'pressure_score'
                            ],
                        'pressure_level' =>
                            (string) $analysis[
                                'pressure_level'
                            ],
                        'pressure_label' =>
                            (string) $analysis[
                                'pressure_label'
                            ],
                        'critical' =>
                            (int) $analysis[
                                'distribution'
                            ]['critical'],
                        'attention' =>
                            (int) $analysis[
                                'distribution'
                            ]['attention'],
                        'watch' =>
                            (int) $analysis[
                                'distribution'
                            ]['watch'],
                        'incidents_open' =>
                            (int) (
                                $context['counts']
                                    ['incidents_open']
                                ?? 0
                            ),
                        'attention_total' =>
                            (int) $analysis[
                                'attention_total'
                            ],
                        'top_rank' =>
                            (int) (
                                $topPriority['rank']
                                ?? 0
                            ),
                        'top_priority' =>
                            $topPriority,
                        'priorities' =>
                            $priorities,
                        'url' =>
                            (string) (
                                $context['url']
                                ?? '#'
                            ),
                    ];
                },
            )
            ->sort(
                function (
                    array $left,
                    array $right,
                ): int {
                    return [
                        $right['pressure_score'],
                        $right['top_rank'],
                        $right['critical'],
                        $left['name'],
                    ] <=> [
                        $left['pressure_score'],
                        $left['top_rank'],
                        $left['critical'],
                        $right['name'],
                    ];
                },
            )
            ->values();

        $topPriorities = $organizations
            ->flatMap(
                fn (array $organization): array =>
                    $organization['priorities'],
            )
            ->sort(
                function (
                    array $left,
                    array $right,
                ): int {
                    return [
                        $right['rank'],
                        $right[
                            'organization_pressure'
                        ],
                        $left['organization'],
                    ] <=> [
                        $left['rank'],
                        $left[
                            'organization_pressure'
                        ],
                        $right['organization'],
                    ];
                },
            )
            ->take(10)
            ->values();

        $scores = $organizations
            ->pluck('pressure_score')
            ->map(
                fn ($score): int =>
                    (int) $score,
            )
            ->sortDesc()
            ->values();

        $globalScore =
            $this->globalPressure(
                $scores,
            );

        $criticalTotal =
            $organizations
                ->sum('critical');

        $incidentTotal =
            $organizations
                ->sum('incidents_open');

        $topOrganization =
            $organizations->first();

        return [
            'pressure_score' =>
                $globalScore,
            'pressure_level' =>
                $this->pressureLevel(
                    $globalScore,
                ),
            'pressure_label' =>
                $this->pressureLabel(
                    $globalScore,
                ),
            'summary' =>
                $this->summary(
                    $organizations,
                    $topOrganization,
                    (int) $criticalTotal,
                    (int) $incidentTotal,
                ),
            'organization_count' =>
                $organizations->count(),
            'critical_total' =>
                (int) $criticalTotal,
            'incidents_total' =>
                (int) $incidentTotal,
            'organizations' =>
                $organizations
                    ->take(8)
                    ->values()
                    ->all(),
            'top_priorities' =>
                $topPriorities->all(),
            'read_only' => true,
            'generated_without_network' =>
                true,
        ];
    }

    private function globalPressure(
        Collection $scores,
    ): int {
        if ($scores->isEmpty()) {
            return 0;
        }

        $top = (int) $scores->first();

        $topThree =
            $scores->take(3);

        $average = (float)
            $topThree->average();

        return min(
            100,
            (int) round(
                ($top * 0.6)
                + ($average * 0.4),
            ),
        );
    }

    private function summary(
        Collection $organizations,
        ?array $topOrganization,
        int $criticalTotal,
        int $incidentTotal,
    ): string {
        if ($organizations->isEmpty()) {
            return 'No hay ámbitos activos disponibles para priorización transversal.';
        }

        if (
            ! $topOrganization
            || $topOrganization[
                'pressure_score'
            ] === 0
        ) {
            return 'Los ámbitos activos no muestran presión operativa relevante con las señales actuales.';
        }

        $summary =
            $topOrganization['name']
            .' concentra la mayor presión operativa con '
            .$topOrganization['pressure_score']
            .'/100.';

        if ($criticalTotal > 0) {
            $summary .=
                ' Hay '
                .$criticalTotal
                .' foco'
                .($criticalTotal === 1 ? '' : 's')
                .' crítico'
                .($criticalTotal === 1 ? '' : 's')
                .' en el conjunto de ámbitos.';
        }

        if ($incidentTotal > 0) {
            $summary .=
                ' Se registran '
                .$incidentTotal
                .' incidente'
                .($incidentTotal === 1 ? '' : 's')
                .' abierto'
                .($incidentTotal === 1 ? '' : 's')
                .'.';
        }

        return $summary;
    }

    private function pressureLevel(
        int $score,
    ): string {
        return match (true) {
            $score >= 75 => 'high',
            $score >= 50 => 'elevated',
            $score >= 25 => 'moderate',
            default => 'controlled',
        };
    }

    private function pressureLabel(
        int $score,
    ): string {
        return match (true) {
            $score >= 75 => 'Alta',
            $score >= 50 => 'Elevada',
            $score >= 25 => 'Moderada',
            default => 'Controlada',
        };
    }
}
