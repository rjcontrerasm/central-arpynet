<?php

namespace App\Support;

use App\Models\Task;
use Carbon\CarbonInterface;

class TaskPriorityCalculator
{
    /**
     * @return array{score: int, band: string}
     */
    public function calculate(
        Task $task,
        ?CarbonInterface $now = null,
    ): array {
        $now ??= now();

        if ($task->status === 'completed') {
            return ['score' => 0, 'band' => 'completed'];
        }

        if ($task->status === 'cancelled') {
            return ['score' => 0, 'band' => 'cancelled'];
        }

        $score =
            $this->duePoints($task, $now)
            + $this->urgencyPoints($task->urgency)
            + $this->impactPoints($task->impact)
            + $this->stagnationPoints($task, $now);

        $score = min(100, max(0, $score));

        $band = match ($task->status) {
            'waiting' => 'waiting',
            'delegated' => 'delegated',
            'someday' => 'someday',
            default => match (true) {
                $score >= 85 => 'critical',
                $score >= 65 => 'today',
                $score >= 40 => 'week',
                default => 'planned',
            },
        };

        return [
            'score' => $score,
            'band' => $band,
        ];
    }

    private function duePoints(
        Task $task,
        CarbonInterface $now,
    ): int {
        if (! $task->due_at) {
            return 0;
        }

        $minutes = $now->diffInMinutes(
            $task->due_at,
            false,
        );

        return match (true) {
            $minutes <= 0 => 50,
            $minutes <= 1_440 => 45,
            $minutes <= 4_320 => 38,
            $minutes <= 10_080 => 30,
            $minutes <= 20_160 => 20,
            $minutes <= 43_200 => 10,
            default => 0,
        };
    }

    private function urgencyPoints(?string $urgency): int
    {
        return match ($urgency) {
            'low' => 5,
            'high' => 18,
            'critical' => 25,
            default => 10,
        };
    }

    private function impactPoints(?string $impact): int
    {
        return match ($impact) {
            'low' => 3,
            'high' => 11,
            'critical' => 15,
            default => 7,
        };
    }

    private function stagnationPoints(
        Task $task,
        CarbonInterface $now,
    ): int {
        $activity = $task->last_activity_at
            ?? $task->updated_at
            ?? $task->created_at;

        if (! $activity) {
            return 0;
        }

        $days = $activity->diffInDays($now);

        return match (true) {
            $days >= 30 => 10,
            $days >= 14 => 7,
            $days >= 7 => 4,
            default => 0,
        };
    }
}
