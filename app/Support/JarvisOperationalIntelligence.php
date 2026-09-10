<?php

namespace App\Support;

use Illuminate\Support\Collection;

class JarvisOperationalIntelligence
{
    public function analyze(
        ?array $context,
    ): array {
        if (! is_array($context)) {
            return $this->emptyAnalysis();
        }

        $attention = collect(
            $context['attention'] ?? [],
        )
            ->filter(
                fn (mixed $item): bool =>
                    is_array($item),
            )
            ->sortByDesc(
                fn (array $item): int =>
                    (int) ($item['rank'] ?? 0),
            )
            ->values();

        $distribution = [
            'critical' =>
                $this->levelCount(
                    $attention,
                    'critical',
                ),
            'attention' =>
                $this->levelCount(
                    $attention,
                    'attention',
                ),
            'watch' =>
                $this->levelCount(
                    $attention,
                    'watch',
                ),
        ];

        $incidentCount = max(
            0,
            (int) (
                $context['counts']
                    ['incidents_open']
                ?? 0
            ),
        );

        $topRank = (int) (
            $attention->first()['rank']
            ?? 0
        );

        $pressureScore = min(
            100,
            ($distribution['critical'] * 18)
            + ($distribution['attention'] * 10)
            + ($distribution['watch'] * 5)
            + (min($incidentCount, 4) * 5)
            + match (true) {
                $topRank >= 95 => 10,
                $topRank >= 80 => 5,
                default => 0,
            },
        );

        $drivers = $attention
            ->flatMap(
                fn (array $item): array =>
                    array_values(
                        array_filter(
                            $item['reasons']
                                ?? [],
                            fn (mixed $reason): bool =>
                                is_string($reason)
                                && trim($reason) !== '',
                        ),
                    ),
            )
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->map(
                fn (int $count, string $label): array => [
                    'label' => $label,
                    'count' => $count,
                ],
            )
            ->values()
            ->all();

        $priorities = $attention
            ->take(6)
            ->map(
                fn (array $item): array =>
                    $this->priority(
                        $item,
                    ),
            )
            ->values()
            ->all();

        $actionable = collect($priorities)
            ->filter(
                fn (array $priority): bool =>
                    filled(
                        $priority[
                            'proposal_action'
                        ] ?? null,
                    ),
            )
            ->count();

        return [
            'pressure_score' =>
                $pressureScore,
            'pressure_level' =>
                $this->pressureLevel(
                    $pressureScore,
                ),
            'pressure_label' =>
                $this->pressureLabel(
                    $pressureScore,
                ),
            'summary' =>
                $this->summary(
                    $context,
                    $distribution,
                    $incidentCount,
                    $attention->first(),
                ),
            'distribution' =>
                $distribution,
            'drivers' => $drivers,
            'priorities' => $priorities,
            'actionable_priorities' =>
                $actionable,
            'attention_total' =>
                $attention->count(),
            'read_only' => true,
            'generated_without_network' =>
                true,
        ];
    }

    private function priority(
        array $item,
    ): array {
        $type = (string) (
            $item['type'] ?? 'unknown'
        );

        $reasons = array_values(
            array_filter(
                $item['reasons'] ?? [],
                fn (mixed $reason): bool =>
                    is_string($reason)
                    && trim($reason) !== '',
            ),
        );

        $noNextAction = (bool) (
            $item['no_next_action']
            ?? false
        );

        $stagnant = (bool) (
            $item['stagnant']
            ?? false
        );

        $recommendation =
            $this->recommendation(
                $type,
                $reasons,
                $noNextAction,
                $stagnant,
            );

        return [
            'type' => $type,
            'type_label' =>
                $item['type_label']
                ?? $this->typeLabel($type),
            'id' => (int) (
                $item['id'] ?? 0
            ),
            'title' => (string) (
                $item['title']
                ?? 'Sin título'
            ),
            'level' => (string) (
                $item['level']
                ?? 'normal'
            ),
            'level_label' =>
                (string) (
                    $item['level_label']
                    ?? 'Normal'
                ),
            'rank' => (int) (
                $item['rank'] ?? 0
            ),
            'why' => $reasons !== []
                ? implode(' · ', $reasons)
                : 'Señal operativa priorizada',
            'suggested_move' =>
                $recommendation[
                    'suggested_move'
                ],
            'proposal_action' =>
                $recommendation[
                    'proposal_action'
                ],
            'proposal_label' =>
                $recommendation[
                    'proposal_label'
                ],
            'url' => (string) (
                $item['url'] ?? '#'
            ),
        ];
    }

    private function recommendation(
        string $type,
        array $reasons,
        bool $noNextAction,
        bool $stagnant,
    ): array {
        if (
            $type === 'project'
            && $noNextAction
        ) {
            return [
                'suggested_move' =>
                    'Definir una siguiente acción concreta para reactivar el proyecto.',
                'proposal_action' =>
                    'project.next_action.set',
                'proposal_label' =>
                    'Definir siguiente acción del proyecto',
            ];
        }

        if (
            in_array(
                $type,
                ['service', 'service_order'],
                true,
            )
            && $noNextAction
        ) {
            return [
                'suggested_move' =>
                    'Definir la siguiente acción del servicio y su fecha de seguimiento.',
                'proposal_action' =>
                    'service_order.next_action.set',
                'proposal_label' =>
                    'Definir siguiente acción del servicio',
            ];
        }

        if ($type === 'task') {
            if (
                in_array(
                    'Seguimiento de espera vencido',
                    $reasons,
                    true,
                )
            ) {
                return [
                    'suggested_move' =>
                        'Resolver el seguimiento de espera antes de reprogramar la tarea.',
                    'proposal_action' => null,
                    'proposal_label' => null,
                ];
            }

            if (
                in_array(
                    'Tarea vencida',
                    $reasons,
                    true,
                )
            ) {
                return [
                    'suggested_move' =>
                        'Revisar el estado y decidir si iniciar, completar o reprogramar la tarea.',
                    'proposal_action' => null,
                    'proposal_label' => null,
                ];
            }

            return [
                'suggested_move' =>
                    'Revisar la tarea priorizada y confirmar su siguiente movimiento.',
                'proposal_action' => null,
                'proposal_label' => null,
            ];
        }

        if ($type === 'project') {
            if (
                in_array(
                    'Tiene bloqueos',
                    $reasons,
                    true,
                )
            ) {
                return [
                    'suggested_move' =>
                        'Revisar el bloqueo y confirmar si sigue vigente antes de modificarlo.',
                    'proposal_action' => null,
                    'proposal_label' => null,
                ];
            }

            if ($stagnant) {
                return [
                    'suggested_move' =>
                        'Revisar el proyecto estancado y acordar un siguiente paso verificable.',
                    'proposal_action' => null,
                    'proposal_label' => null,
                ];
            }

            return [
                'suggested_move' =>
                    'Revisar estado, fecha objetivo y siguiente acción del proyecto.',
                'proposal_action' => null,
                'proposal_label' => null,
            ];
        }

        if (
            in_array(
                $type,
                ['service', 'service_order'],
                true,
            )
        ) {
            return [
                'suggested_move' =>
                    $stagnant
                        ? 'Revisar la etapa y reactivar el seguimiento del servicio.'
                        : 'Revisar la etapa y la siguiente acción del servicio.',
                'proposal_action' => null,
                'proposal_label' => null,
            ];
        }

        if ($type === 'obligation') {
            return [
                'suggested_move' =>
                    'Atender o registrar el vencimiento antes de continuar con otras prioridades.',
                'proposal_action' => null,
                'proposal_label' => null,
            ];
        }

        return [
            'suggested_move' =>
                'Revisar la señal antes de tomar una acción.',
            'proposal_action' => null,
            'proposal_label' => null,
        ];
    }

    private function summary(
        array $context,
        array $distribution,
        int $incidentCount,
        mixed $topItem,
    ): string {
        $parts = [];

        if ($distribution['critical'] > 0) {
            $parts[] =
                $distribution['critical']
                .' foco'
                .($distribution['critical'] === 1 ? '' : 's')
                .' crítico'
                .($distribution['critical'] === 1 ? '' : 's');
        }

        if ($distribution['attention'] > 0) {
            $parts[] =
                $distribution['attention']
                .' en atención';
        }

        if ($incidentCount > 0) {
            $parts[] =
                $incidentCount
                .' incidente'
                .($incidentCount === 1 ? '' : 's')
                .' abierto'
                .($incidentCount === 1 ? '' : 's');
        }

        if ($parts === []) {
            return 'El ámbito no presenta presión operativa relevante con las señales actuales.';
        }

        $summary =
            'Detecto '
            .implode(', ', $parts)
            .'.';

        if (is_array($topItem)) {
            $title = trim(
                (string) (
                    $topItem['title']
                    ?? ''
                ),
            );

            if ($title !== '') {
                $summary .=
                    ' La prioridad principal es “'
                    .$title
                    .'”.';
            }
        }

        return $summary;
    }

    private function levelCount(
        Collection $attention,
        string $level,
    ): int {
        return $attention
            ->filter(
                fn (array $item): bool =>
                    ($item['level'] ?? null)
                    === $level,
            )
            ->count();
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

    private function typeLabel(
        string $type,
    ): string {
        return match ($type) {
            'task' => 'Tarea',
            'project' => 'Proyecto',
            'service',
            'service_order' => 'Servicio',
            'obligation' => 'Vencimiento',
            default => 'Elemento',
        };
    }

    private function emptyAnalysis(): array
    {
        return [
            'pressure_score' => 0,
            'pressure_level' =>
                'controlled',
            'pressure_label' =>
                'Controlada',
            'summary' =>
                'No hay un ámbito operativo disponible para analizar.',
            'distribution' => [
                'critical' => 0,
                'attention' => 0,
                'watch' => 0,
            ],
            'drivers' => [],
            'priorities' => [],
            'actionable_priorities' => 0,
            'attention_total' => 0,
            'read_only' => true,
            'generated_without_network' =>
                true,
        ];
    }
}
