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
}
