<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class JarvisDailyPlan
{
    public function build(
        array $executivePrioritization,
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $priorities = collect(
            $executivePrioritization[
                'top_priorities'
            ] ?? [],
        )
            ->filter(
                fn (mixed $item): bool =>
                    is_array($item),
            )
            ->values();

        $sections = [
            'immediate' => [
                'label' =>
                    'Prioridad inmediata',
                'hint' =>
                    'Resolver o encaminar primero.',
                'items' => [],
            ],
            'today' => [
                'label' =>
                    'Atender hoy',
                'hint' =>
                    'Trabajo importante del día.',
                'items' => [],
            ],
            'follow_up' => [
                'label' =>
                    'Seguimientos',
                'hint' =>
                    'No dejar perder dependencias o vencimientos.',
                'items' => [],
            ],
            'capacity' => [
                'label' =>
                    'Si queda capacidad',
                'hint' =>
                    'Avanzar después de lo prioritario.',
                'items' => [],
            ],
        ];

        foreach ($priorities as $priority) {
            $section =
                $this->sectionFor(
                    $priority,
                );

            $sections[$section]['items'][] =
                $this->planItem(
                    $priority,
                );
        }

        $sequence = 1;

        foreach ($sections as &$section) {
            foreach (
                $section['items']
                as &$item
            ) {
                $item['sequence'] =
                    $sequence++;
            }

            unset($item);
        }

        unset($section);

        $total = $sequence - 1;

        $activeSections = collect(
            $sections,
        )
            ->filter(
                fn (array $section): bool =>
                    $section['items'] !== [],
            )
            ->count();

        return [
            'date' =>
                $now->toDateString(),
            'date_label' =>
                $now->locale('es')
                    ->translatedFormat(
                        'l d \d\e F',
                    ),
            'summary' =>
                $this->summary(
                    $sections,
                    $total,
                    (int) (
                        $executivePrioritization[
                            'pressure_score'
                        ] ?? 0
                    ),
                ),
            'pressure_score' =>
                (int) (
                    $executivePrioritization[
                        'pressure_score'
                    ] ?? 0
                ),
            'sections' => $sections,
            'total_items' => $total,
            'active_sections' =>
                $activeSections,
            'focus_organizations' =>
                collect(
                    $executivePrioritization[
                        'organizations'
                    ] ?? [],
                )
                    ->filter(
                        fn (mixed $item): bool =>
                            is_array($item)
                            && (
                                (int) (
                                    $item[
                                        'pressure_score'
                                    ] ?? 0
                                )
                            ) > 0,
                    )
                    ->take(3)
                    ->map(
                        fn (array $item): array => [
                            'id' =>
                                (int) $item['id'],
                            'name' =>
                                (string) $item[
                                    'name'
                                ],
                            'pressure_score' =>
                                (int) $item[
                                    'pressure_score'
                                ],
                        ],
                    )
                    ->values()
                    ->all(),
            'read_only' => true,
            'generated_without_network' =>
                true,
        ];
    }

    private function sectionFor(
        array $priority,
    ): string {
        $rank = (int) (
            $priority['rank'] ?? 0
        );

        $level = (string) (
            $priority['level'] ?? ''
        );

        if (
            $level === 'critical'
            || $rank >= 90
        ) {
            return 'immediate';
        }

        if (
            $this->isFollowUp(
                $priority,
            )
        ) {
            return 'follow_up';
        }

        if ($rank >= 65) {
            return 'today';
        }

        return 'capacity';
    }

    private function isFollowUp(
        array $priority,
    ): bool {
        if (
            ($priority['type'] ?? null)
            === 'obligation'
        ) {
            return true;
        }

        $text = mb_strtolower(
            implode(' ', [
                (string) (
                    $priority['why']
                    ?? ''
                ),
                (string) (
                    $priority[
                        'suggested_move'
                    ] ?? ''
                ),
            ]),
        );

        foreach (
            [
                'seguimiento',
                'espera vencido',
                'dependencia',
                'vencimiento',
            ]
            as $needle
        ) {
            if (
                str_contains(
                    $text,
                    $needle,
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function planItem(
        array $priority,
    ): array {
        return [
            'sequence' => 0,
            'organization_id' =>
                (int) (
                    $priority[
                        'organization_id'
                    ] ?? 0
                ),
            'organization' =>
                (string) (
                    $priority[
                        'organization'
                    ] ?? 'Sin ámbito'
                ),
            'type' =>
                (string) (
                    $priority['type']
                    ?? 'unknown'
                ),
            'type_label' =>
                (string) (
                    $priority[
                        'type_label'
                    ] ?? 'Elemento'
                ),
            'id' =>
                (int) (
                    $priority['id']
                    ?? 0
                ),
            'title' =>
                (string) (
                    $priority['title']
                    ?? 'Sin título'
                ),
            'rank' =>
                (int) (
                    $priority['rank']
                    ?? 0
                ),
            'level' =>
                (string) (
                    $priority['level']
                    ?? 'normal'
                ),
            'level_label' =>
                (string) (
                    $priority[
                        'level_label'
                    ] ?? 'Normal'
                ),
            'why' =>
                (string) (
                    $priority['why']
                    ?? 'Señal priorizada'
                ),
            'suggested_move' =>
                (string) (
                    $priority[
                        'suggested_move'
                    ] ?? 'Revisar antes de actuar.'
                ),
            'url' =>
                (string) (
                    $priority['url']
                    ?? '#'
                ),
        ];
    }

    private function summary(
        array $sections,
        int $total,
        int $pressure,
    ): string {
        if ($total === 0) {
            return 'No hay asuntos transversales que Jarvis necesite incorporar al plan de hoy.';
        }

        $immediate = count(
            $sections['immediate']
                ['items'],
        );

        $followUps = count(
            $sections['follow_up']
                ['items'],
        );

        $summary =
            'Jarvis ordenó '
            .$total
            .' asunto'
            .($total === 1 ? '' : 's')
            .' para la jornada';

        if ($immediate > 0) {
            $summary .=
                ', con '
                .$immediate
                .' de prioridad inmediata';
        }

        if ($followUps > 0) {
            $summary .=
                ' y '
                .$followUps
                .' seguimiento'
                .($followUps === 1 ? '' : 's');
        }

        return $summary
            .'. Presión global: '
            .$pressure
            .'/100.';
    }
}
