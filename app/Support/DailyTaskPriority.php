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
        $dueScore = self::dueScore(
            $task,
            $now,
        );

        $urgencyScore = match (
            strtolower((string) $task->urgency)
        ) {
            'high' => 25,
            'medium' => 12,
            default => 0,
        };

        $impactScore = match (
            strtolower((string) $task->impact)
        ) {
            'high' => 15,
            'medium' => 8,
            default => 0,
        };

        $inactivityScore = self::inactivityScore(
            $task,
            $now,
        );

        return min(
            100,
            max(
                0,
                $dueScore
                + $urgencyScore
                + $impactScore
                + $inactivityScore,
            ),
        );
    }

    public static function band(
        Task $task,
        CarbonImmutable $now,
    ): string {
        $score = self::score(
            $task,
            $now,
        );

        return match (true) {
            $score >= 85 => 'critical',
            $score >= 65 => 'today',
            $score >= 40 => 'week',
            default => 'planned',
        };
    }

    public static function label(
        string $band,
    ): string {
        return match ($band) {
            'critical' => 'Crítica',
            'today' => 'Hoy',
            'week' => 'Semana',
            default => 'Planificada',
        };
    }

    private static function dueScore(
        Task $task,
        CarbonImmutable $now,
    ): int {
        if (! $task->due_at) {
            return 0;
        }

        $today = $now->startOfDay();

        $dueDate = CarbonImmutable::instance(
            $task->due_at,
        )->startOfDay();

        if ($dueDate->isBefore($today)) {
            return 50;
        }

        if ($dueDate->isSameDay($today)) {
            return 50;
        }

        $days = (int) $today->diffInDays(
            $dueDate,
            false,
        );

        return match (true) {
            $days === 1 => 40,
            $days <= 3 => 32,
            $days <= 7 => 24,
            $days <= 30 => 12,
            default => 5,
        };
    }

    private static function inactivityScore(
        Task $task,
        CarbonImmutable $now,
    ): int {
        if (! $task->updated_at) {
            return 0;
        }

        $updatedAt = CarbonImmutable::instance(
            $task->updated_at,
        );

        if ($updatedAt->isAfter($now)) {
            return 0;
        }

        $days = (int) $updatedAt->diffInDays(
            $now,
            false,
        );

        return match (true) {
            $days >= 14 => 10,
            $days >= 7 => 6,
            $days >= 3 => 3,
            default => 0,
        };
    }
}
