<?php

namespace App\Support;

use App\Models\Task;
use Carbon\CarbonImmutable;

class DailyTaskPriority
{
    public static function score(
        Task $task,
        CarbonImmutable $now,
    ): int {
        return app(
            TaskPriorityCalculator::class,
        )->calculate(
            $task,
            $now,
        )['score'];
    }

    public static function band(
        Task $task,
        CarbonImmutable $now,
    ): string {
        return app(
            TaskPriorityCalculator::class,
        )->calculate(
            $task,
            $now,
        )['band'];
    }

    public static function label(
        string $band,
    ): string {
        return match ($band) {
            'critical' => 'Crítico',
            'today' => 'Hoy',
            'week' => 'Semana',
            'waiting' => 'En espera',
            'delegated' => 'Delegada',
            'someday' => 'Algún día',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            default => 'Planificado',
        };
    }

    public static function reviewLevel(
        Task $task,
    ): string {
        $weights = [
            'low' => 1,
            'normal' => 2,
            'medium' => 2,
            'high' => 3,
            'critical' => 4,
        ];

        $level = max(
            $weights[$task->urgency ?? 'normal'] ?? 2,
            $weights[$task->impact ?? 'normal'] ?? 2,
        );

        return match ($level) {
            4 => 'critical',
            3 => 'high',
            1 => 'low',
            default => 'normal',
        };
    }

    public static function reviewRank(
        Task $task,
    ): int {
        return match (self::reviewLevel($task)) {
            'critical' => 0,
            'high' => 1,
            'normal' => 2,
            default => 3,
        };
    }

    public static function reviewLabel(
        Task $task,
    ): string {
        return match (self::reviewLevel($task)) {
            'critical' => 'Crítica',
            'high' => 'Alta',
            'low' => 'Baja',
            default => 'Normal',
        };
    }
}
