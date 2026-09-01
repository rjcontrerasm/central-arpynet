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
        $stored = $task->getAttribute('priority_score');

        if (is_numeric($stored)) {
            return max(
                0,
                min(100, (int) $stored),
            );
        }

        $due = 0;

        if ($task->due_at) {
            $days = $now
                ->startOfDay()
                ->diffInDays(
                    CarbonImmutable::parse($task->due_at)->startOfDay(),
                    false,
                );

            $due = match (true) {
                $days < 0 => 50,
                $days === 0 => 45,
                $days === 1 => 38,
                $days <= 3 => 30,
                $days <= 7 => 22,
                $days <= 30 => 12,
                default => 5,
            };
        }

        $urgency = match ($task->urgency) {
            'high' => 25,
            'medium' => 12,
            default => 0,
        };

        $impact = match ($task->impact) {
            'high' => 15,
            'medium' => 8,
            default => 0,
        };

        $inactiveDays = $task->updated_at
            ? $task->updated_at->diffInDays($now)
            : 0;

        $inactivity = match (true) {
            $inactiveDays >= 14 => 10,
            $inactiveDays >= 7 => 6,
            $inactiveDays >= 3 => 3,
            default => 0,
        };

        return min(
            100,
            $due + $urgency + $impact + $inactivity,
        );
    }

    public static function band(
        Task $task,
        CarbonImmutable $now,
    ): string {
        $stored = $task->getAttribute('priority_band');

        if (
            is_string($stored)
            && in_array(
                $stored,
                ['critical', 'today', 'week', 'planned'],
                true,
            )
        ) {
            return $stored;
        }

        $score = self::score($task, $now);

        return match (true) {
            $score >= 85 => 'critical',
            $score >= 65 => 'today',
            $score >= 40 => 'week',
            default => 'planned',
        };
    }

    public static function label(string $band): string
    {
        return match ($band) {
            'critical' => 'Crítica',
            'today' => 'Hoy',
            'week' => 'Semana',
            default => 'Planificada',
        };
    }
}
