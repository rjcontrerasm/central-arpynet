<?php

namespace App\Support;

use App\Models\ObligationOccurrence;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use Carbon\CarbonImmutable;

class GlobalTrackingItemFactory
{
    public static function task(
        Task $task,
        CarbonImmutable $now,
    ): array {
        $score = DailyTaskPriority::score(
            $task,
            $now,
        );

        $band = DailyTaskPriority::band(
            $task,
            $now,
        );

        $reasons = [];
        $level = 'normal';
        $rank = $score;

        if (
            $task->due_at
            && $task->due_at->isBefore(
                $now->startOfDay(),
            )
        ) {
            $level = 'critical';
            $rank = max($rank, 100);
            $reasons[] = 'Tarea vencida';
        } elseif ($score >= 85) {
            $level = 'critical';
            $rank = max($rank, 90);
            $reasons[] = 'Prioridad crítica';
        } elseif ($score >= 65) {
            $level = 'attention';
            $rank = max($rank, 75);
            $reasons[] = 'Prioridad para hoy';
        } elseif ($score >= 40) {
            $level = 'watch';
            $rank = max($rank, 55);
            $reasons[] = 'Prioridad semanal';
        }

        if ($task->waiting_since) {
            if (
                $task->waiting_until
                && $task->waiting_until->isPast()
            ) {
                $level = 'critical';
                $rank = max($rank, 96);
                $reasons[] = 'Seguimiento de espera vencido';
            } else {
                if ($level === 'normal') {
                    $level = 'watch';
                }

                $rank = max($rank, 50);
                $reasons[] = 'En espera';
            }
        }

        return [
            'type' => 'task',
            'type_label' => 'Tarea',
            'id' => $task->id,
            'title' => $task->title,
            'organization_id' => $task->organization_id,
            'organization' => $task->organization?->name
                ?? 'Sin ámbito',
            'level' => $level,
            'level_label' => self::levelLabel($level),
            'rank' => $rank,
            'reasons' => array_values(
                array_unique($reasons),
            ),
            'meta' => 'Prioridad '
                .DailyTaskPriority::label($band)
                .' · '.$score,
            'date_label' => $task->due_at
                ? 'Vence '
                    .$task->due_at->format('d/m/Y')
                : null,
            'url' => route(
                'daily-ops.show',
                ['scope' => $task->organization_id],
            ),
        ];
    }

    public static function project(
        Project $project,
        CarbonImmutable $now,
    ): array {
        $reasons = [];
        $level = 'normal';
        $rank = 20;

        $terminal = in_array(
            $project->status,
            ['completed', 'cancelled'],
            true,
        );

        if ($terminal) {
            $level = 'closed';
            $rank = 0;
        } else {
            if (
                $project->target_date
                && $project->target_date->isPast()
            ) {
                $level = 'critical';
                $rank = 95;
                $reasons[] = 'Fecha objetivo vencida';
            }

            if ($project->stagnation_days >= 30) {
                $level = 'critical';
                $rank = max($rank, 90);
                $reasons[] = 'Estancado '
                    .$project->stagnation_days
                    .' días';
            } elseif ($project->stagnation_days >= 15) {
                if ($level !== 'critical') {
                    $level = 'attention';
                }

                $rank = max($rank, 75);
                $reasons[] = 'Sin movimiento '
                    .$project->stagnation_days
                    .' días';
            }

            if (filled($project->blockers)) {
                if ($level === 'normal') {
                    $level = 'attention';
                }

                $rank = max($rank, 80);
                $reasons[] = 'Tiene bloqueos';
            }

            if (blank($project->next_action)) {
                if ($level === 'normal') {
                    $level = 'watch';
                }

                $rank = max($rank, 50);
                $reasons[] = 'Sin siguiente acción';
            }
        }

        return [
            'type' => 'project',
            'type_label' => 'Proyecto',
            'id' => $project->id,
            'title' => $project->name,
            'organization_id' => $project->organization_id,
            'organization' => $project->organization?->name
                ?? 'Sin ámbito',
            'level' => $level,
            'level_label' => self::levelLabel($level),
            'rank' => $rank,
            'reasons' => $reasons,
            'meta' => 'Avance '
                .$project->progress_percent
                .'% · '
                .$project->stagnation_label,
            'date_label' => $project->target_date
                ? 'Objetivo '
                    .$project->target_date->format('d/m/Y')
                : null,
            'url' => url('/admin/proyectos'),
        ];
    }

    public static function serviceOrder(
        ServiceOrder $order,
        CarbonImmutable $now,
    ): array {
        $state = ServiceOrderOperationalState::evaluate(
            $order,
            $now,
        );

        return [
            'type' => 'service',
            'type_label' => 'Servicio',
            'id' => $order->id,
            'title' => $order->title,
            'organization_id' => $order->organization_id,
            'organization' => $order->organization?->name
                ?? 'Sin ámbito',
            'level' => $state['level'],
            'level_label' => self::levelLabel(
                $state['level'],
            ),
            'rank' => $state['rank'],
            'reasons' => $state['reasons'],
            'meta' => (
                ServiceOrder::stageOptions()[$order->stage]
                ?? $order->stage
            )
                .' · '
                .$state['days_in_stage']
                .' días en etapa',
            'date_label' => $order->next_action_at
                ? 'Acción '
                    .$order->next_action_at->format(
                        'd/m/Y H:i',
                    )
                : (
                    $order->end_date
                        ? 'Fin '
                            .$order->end_date->format('d/m/Y')
                        : null
                ),
            'url' => route(
                'service-orders-ops.show',
                ['scope' => $order->organization_id],
            ),
        ];
    }

    public static function obligation(
        ObligationOccurrence $occurrence,
        CarbonImmutable $now,
    ): array {
        $state = ObligationOperationalState::evaluate(
            $occurrence,
            $now,
        );

        $reasons = [];

        if ($state['level'] === 'overdue') {
            $reasons[] = 'Vencimiento atrasado';
        } elseif ($state['level'] === 'today') {
            $reasons[] = 'Vence hoy';
        } elseif ($state['level'] === 'critical') {
            $reasons[] = 'Próximo crítico';
        } elseif ($state['level'] === 'upcoming') {
            $reasons[] = 'Próximo vencimiento';
        }

        $level = match ($state['level']) {
            'overdue', 'today', 'critical' => 'critical',
            'upcoming' => 'watch',
            'paid', 'skipped' => 'closed',
            default => 'normal',
        };

        return [
            'type' => 'obligation',
            'type_label' => 'Vencimiento',
            'id' => $occurrence->id,
            'title' => $occurrence->obligation?->name
                ?? 'Obligación',
            'organization_id' => $occurrence->organization_id,
            'organization' => $occurrence->organization?->name
                ?? 'Sin ámbito',
            'level' => $level,
            'level_label' => self::levelLabel($level),
            'rank' => $state['rank'],
            'reasons' => $reasons,
            'meta' => $occurrence->expected_amount !== null
                ? $occurrence->currency
                    .' '
                    .number_format(
                        (float) $occurrence->expected_amount,
                        2,
                        '.',
                        ',',
                    )
                : 'Sin monto',
            'date_label' => 'Vence '
                .$occurrence->due_date->format('d/m/Y'),
            'url' => route(
                'obligation-ops.show',
                ['scope' => $occurrence->organization_id],
            ),
        ];
    }

    public static function needsAttention(
        array $item,
    ): bool {
        return in_array(
            $item['level'],
            ['critical', 'attention', 'watch'],
            true,
        );
    }

    private static function levelLabel(
        string $level,
    ): string {
        return match ($level) {
            'critical' => 'Crítico',
            'attention' => 'Atención',
            'watch' => 'Vigilar',
            'closed' => 'Cerrado',
            default => 'Normal',
        };
    }
}
