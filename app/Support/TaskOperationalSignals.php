<?php

namespace App\Support;

use App\Models\Task;
use Carbon\CarbonImmutable;

class TaskOperationalSignals
{
    /**
     * @return array{
     *     level: string,
     *     rank: int,
     *     reasons: array<int, string>,
     *     stagnation_days: int,
     *     stagnant: bool,
     *     no_next_action: bool
     * }
     */
    public static function evaluate(
        Task $task,
        CarbonImmutable $now,
    ): array {
        $activity =
            $task->last_activity_at
            ?? $task->updated_at
            ?? $task->created_at;

        $days = 0;

        if ($activity) {
            $activity =
                CarbonImmutable::instance(
                    $activity,
                );

            if (
                ! $activity->isAfter($now)
            ) {
                $days = (int) $activity
                    ->diffInDays(
                        $now,
                        false,
                    );
            }
        }

        $reasons = [];
        $level = 'normal';
        $rank = 0;
        $stagnant = false;
        $noNextAction = false;

        if (
            in_array(
                $task->status,
                [
                    'completed',
                    'cancelled',
                    'waiting',
                    'delegated',
                    'someday',
                ],
                true,
            )
        ) {
            return [
                'level' => $level,
                'rank' => $rank,
                'reasons' => $reasons,
                'stagnation_days' =>
                    $days,
                'stagnant' => false,
                'no_next_action' =>
                    false,
            ];
        }

        if (
            $task->status === 'inbox'
        ) {
            if ($days >= 7) {
                $level = 'critical';
                $rank = 90;
                $stagnant = true;
                $reasons[] =
                    'Sin procesar '
                    .$days
                    .' días';
            } elseif ($days >= 2) {
                $level = 'attention';
                $rank = 72;
                $stagnant = true;
                $reasons[] =
                    'Sin procesar '
                    .$days
                    .' días';
            }

            if ($days >= 2) {
                $noNextAction = true;
            }
        }

        if (
            $task->status
            === 'in_progress'
        ) {
            if ($days >= 14) {
                $level = 'critical';
                $rank = 92;
                $stagnant = true;
                $reasons[] =
                    'En curso sin movimiento '
                    .$days
                    .' días';
            } elseif ($days >= 7) {
                $level = 'attention';
                $rank = 76;
                $stagnant = true;
                $reasons[] =
                    'En curso sin movimiento '
                    .$days
                    .' días';
            }

            if (
                blank(
                    $task->next_action,
                )
            ) {
                $noNextAction = true;

                if (
                    $level === 'normal'
                ) {
                    $level = 'attention';
                }

                $rank = max(
                    $rank,
                    78,
                );

                $reasons[] =
                    'En curso sin siguiente acción';
            }
        }

        if (
            $task->status === 'pending'
        ) {
            $dueSoon =
                $task->due_at
                && $task->due_at->lte(
                    $now->addDays(7),
                );

            if (
                blank(
                    $task->next_action,
                )
                && (
                    $dueSoon
                    || (
                        ! $task->due_at
                        && $days >= 14
                    )
                )
            ) {
                $noNextAction = true;

                if (
                    $level === 'normal'
                ) {
                    $level = 'watch';
                }

                $rank = max(
                    $rank,
                    52,
                );

                $reasons[] =
                    'Sin siguiente acción';
            }

            /*
             * Pending tasks are allowed to sit
             * when genuinely planned. Only a very
             * old, undated pending task is marked
             * stagnant, and only as attention.
             */
            if (
                ! $task->due_at
                && $days >= 30
            ) {
                $stagnant = true;

                if (
                    $level === 'normal'
                    || $level === 'watch'
                ) {
                    $level =
                        'attention';
                }

                $rank = max(
                    $rank,
                    70,
                );

                $reasons[] =
                    'Pendiente sin movimiento '
                    .$days
                    .' días';
            }
        }

        return [
            'level' => $level,
            'rank' => $rank,
            'reasons' =>
                array_values(
                    array_unique(
                        $reasons,
                    ),
                ),
            'stagnation_days' =>
                $days,
            'stagnant' =>
                $stagnant,
            'no_next_action' =>
                $noNextAction,
        ];
    }
}
