<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Support\TaskPriorityCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class TaskPriorityCalculatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_overdue_critical_task_receives_critical_band(): void
    {
        Carbon::setTestNow(
            '2026-08-06 09:00:00',
        );

        $task = new Task([
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->subMinute(),
            'last_activity_at' => now()->subDays(30),
        ]);

        $result = (new TaskPriorityCalculator())
            ->calculate($task);

        $this->assertSame(96, $result['score']);
        $this->assertSame('critical', $result['band']);
    }

    public function test_low_task_without_due_date_is_planned(): void
    {
        Carbon::setTestNow(
            '2026-08-06 09:00:00',
        );

        $task = new Task([
            'status' => 'pending',
            'urgency' => 'low',
            'impact' => 'low',
            'last_activity_at' => now(),
        ]);

        $result = (new TaskPriorityCalculator())
            ->calculate($task);

        $this->assertSame(8, $result['score']);
        $this->assertSame('planned', $result['band']);
    }
}
