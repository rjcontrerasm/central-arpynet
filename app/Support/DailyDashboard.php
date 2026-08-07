<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DailyDashboard
{
    /**
     * @return array{overdue:int,today:int,critical:int,waiting:int,open:int}
     */
    public function metrics(User $user): array
    {
        $base = $this->openTasks($user);

        return [
            'overdue' => (clone $base)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
            'today' => (clone $base)
                ->whereBetween('due_at', [
                    now()->startOfDay(),
                    now()->endOfDay(),
                ])
                ->count(),
            'critical' => (clone $base)
                ->where('priority_band', 'critical')
                ->count(),
            'waiting' => (clone $base)
                ->where('status', 'waiting')
                ->count(),
            'open' => (clone $base)->count(),
        ];
    }

    public function priorityTasks(User $user): Builder
    {
        return $this->openTasks($user)
            ->with('organization')
            ->orderByDesc('priority_score')
            ->orderByRaw('due_at IS NULL, due_at ASC')
            ->orderByDesc('updated_at');
    }

    public function upcomingTasks(User $user): Builder
    {
        return $this->openTasks($user)
            ->with('organization')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [
                now(),
                now()->addDays(7)->endOfDay(),
            ])
            ->orderBy('due_at')
            ->orderByDesc('priority_score');
    }

    private function openTasks(User $user): Builder
    {
        return Task::query()
            ->visibleTo($user)
            ->open();
    }
}
