<?php

namespace App\Support;

use App\Models\DailyReviewSession;
use Carbon\CarbonImmutable;

class JarvisDailyReviewAssistant
{
    public function build(
        array $executivePrioritization,
        array $dailyPlan,
        ?DailyReviewSession $review = null,
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

        $critical = $priorities
            ->filter(
                fn (array $item): bool =>
                    ($item['level'] ?? null)
                        === 'critical'
                    || (int) (
                        $item['rank'] ?? 0
                    ) >= 90,
            )
            ->take(5)
            ->map(
                fn (array $item): array =>
                    $this->reviewItem($item),
            )
            ->values()
            ->all();

        $followUps = $priorities
            ->filter(
                fn (array $item): bool =>
                    $this->isFollowUp($item),
            )
            ->take(5)
            ->map(
                fn (array $item): array =>
                    $this->reviewItem($item),
            )
            ->values()
            ->all();

        $withoutNextAction = $priorities
            ->filter(
                fn (array $item): bool =>
                    $this->withoutNextAction(
                        $item,
                    ),
            )
            ->take(5)
            ->map(
                fn (array $item): array =>
                    $this->reviewItem($item),
            )
            ->values()
            ->all();

        $blocked = $priorities
            ->filter(
                fn (array $item): bool =>
                    $this->isBlocked($item),
            )
            ->take(5)
            ->map(
                fn (array $item): array =>
                    $this->reviewItem($item),
            )
            ->values()
            ->all();

        $steps = [
            'decisions' => [
                'label' =>
                    'Decisiones y críticos',
                'reviewed' =>
                    (bool) $review?->decisions_reviewed_at,
            ],
            'waiting' => [
                'label' =>
                    'Seguimientos en espera',
                'reviewed' =>
                    (bool) $review?->waiting_reviewed_at,
            ],
            'tasks' => [
                'label' =>
                    'Tareas con fecha',
                'reviewed' =>
                    (bool) $review?->tasks_reviewed_at,
            ],
            'operations' => [
                'label' =>
                    'Servicios y vencimientos',
                'reviewed' =>
                    (bool) $review?->operations_reviewed_at,
            ],
        ];

        $reviewedCount =
            $review?->reviewedCount()
            ?? 0;

        $sections = [
            'critical' => [
                'label' =>
                    'Críticos abiertos',
                'count' =>
                    count($critical),
                'items' => $critical,
            ],
            'follow_up' => [
                'label' =>
                    'Seguimientos',
                'count' =>
                    count($followUps),
                'items' => $followUps,
            ],
            'next_action' => [
                'label' =>
                    'Sin siguiente acción',
                'count' =>
                    count($withoutNextAction),
                'items' =>
                    $withoutNextAction,
            ],
            'blocked' => [
                'label' =>
                    'Bloqueos / estancados',
                'count' =>
                    count($blocked),
                'items' => $blocked,
            ],
        ];

        $uniqueLooseEnds =
            collect($sections)
                ->flatMap(
                    fn (array $section): array =>
                        $section['items'],
                )
                ->unique(
                    fn (array $item): string =>
                        $item[
                            'organization_id'
                        ]
                        .':'
                        .$item['type']
                        .':'
                        .$item['id'],
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
                    $reviewedCount,
                    (bool) $review?->completed_at,
                ),
            'sections' => $sections,
            'unique_loose_ends' =>
                $uniqueLooseEnds,
            'review' => [
                'exists' =>
                    $review !== null,
                'reviewed_count' =>
                    $reviewedCount,
                'total_steps' => 4,
                'completed' =>
                    (bool) $review?->completed_at,
                'steps' => $steps,
                'url' =>
                    route(
                        'daily-review.show',
                        [],
                        false,
                    ),
            ],
            'daily_plan_items' =>
                (int) (
                    $dailyPlan[
                        'total_items'
                    ] ?? 0
                ),
            'read_only' => true,
            'generated_without_network' =>
                true,
        ];
    }

    private function reviewItem(
        array $item,
    ): array {
        return [
            'organization_id' =>
                (int) (
                    $item[
                        'organization_id'
                    ] ?? 0
                ),
            'organization' =>
                (string) (
                    $item['organization']
                    ?? 'Sin ámbito'
                ),
            'type' =>
                (string) (
                    $item['type']
                    ?? 'unknown'
                ),
            'type_label' =>
                (string) (
                    $item[
                        'type_label'
                    ] ?? 'Elemento'
                ),
            'id' =>
                (int) (
                    $item['id'] ?? 0
                ),
            'title' =>
                (string) (
                    $item['title']
                    ?? 'Sin título'
                ),
            'rank' =>
                (int) (
                    $item['rank'] ?? 0
                ),
            'level_label' =>
                (string) (
                    $item[
                        'level_label'
                    ] ?? 'Normal'
                ),
            'why' =>
                (string) (
                    $item['why']
                    ?? 'Señal priorizada'
                ),
            'suggested_move' =>
                (string) (
                    $item[
                        'suggested_move'
                    ] ?? 'Revisar antes de cerrar.'
                ),
            'url' =>
                (string) (
                    $item['url']
                    ?? '#'
                ),
        ];
    }

    private function isFollowUp(
        array $item,
    ): bool {
        if (
            ($item['type'] ?? null)
            === 'obligation'
        ) {
            return true;
        }

        return $this->containsAny(
            $item,
            [
                'seguimiento',
                'espera vencido',
                'dependencia',
                'vencimiento',
            ],
        );
    }

    private function withoutNextAction(
        array $item,
    ): bool {
        if (
            in_array(
                $item[
                    'proposal_action'
                ] ?? null,
                [
                    'project.next_action.set',
                    'service_order.next_action.set',
                ],
                true,
            )
        ) {
            return true;
        }

        return $this->containsAny(
            $item,
            ['sin siguiente acción'],
        );
    }

    private function isBlocked(
        array $item,
    ): bool {
        return $this->containsAny(
            $item,
            [
                'bloqueo',
                'bloqueos',
                'estancado',
                'estancamiento',
            ],
        );
    }

    private function containsAny(
        array $item,
        array $needles,
    ): bool {
        $text = mb_strtolower(
            implode(' ', [
                (string) (
                    $item['why']
                    ?? ''
                ),
                (string) (
                    $item[
                        'suggested_move'
                    ] ?? ''
                ),
            ]),
        );

        foreach ($needles as $needle) {
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

    private function summary(
        array $sections,
        int $reviewedCount,
        bool $completed,
    ): string {
        $critical = (int)
            $sections['critical']
                ['count'];

        $followUps = (int)
            $sections['follow_up']
                ['count'];

        $nextAction = (int)
            $sections['next_action']
                ['count'];

        $blocked = (int)
            $sections['blocked']
                ['count'];

        if (
            $critical === 0
            && $followUps === 0
            && $nextAction === 0
            && $blocked === 0
        ) {
            $text =
                'No detecto cabos sueltos destacados dentro del top transversal actual.';
        } else {
            $text =
                'Antes de cerrar: '
                .$critical
                .' crítico'
                .($critical === 1 ? '' : 's')
                .', '
                .$followUps
                .' seguimiento'
                .($followUps === 1 ? '' : 's')
                .', '
                .$nextAction
                .' sin siguiente acción y '
                .$blocked
                .' con bloqueo o estancamiento.';
        }

        if ($completed) {
            return $text
                .' La revisión oficial de hoy ya está completada.';
        }

        return $text
            .' Revisión oficial: '
            .$reviewedCount
            .'/4 pasos revisados.';
    }
}
