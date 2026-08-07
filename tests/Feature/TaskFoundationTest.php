<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_task_is_assigned_and_prioritized_on_creation(): void
    {
        Carbon::setTestNow(
            '2026-08-06 09:00:00',
        );

        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Presentar informe',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->addDay(),
        ]);

        $this->assertSame($user->id, $task->created_by);
        $this->assertSame($user->id, $task->assigned_to);
        $this->assertGreaterThan(0, $task->priority_score);
        $this->assertSame('today', $task->priority_band);
    }

    public function test_completed_task_has_zero_priority(): void
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Personal',
            'slug' => 'personal',
            'category' => 'personal',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea finalizada',
            'status' => 'completed',
            'urgency' => 'critical',
            'impact' => 'critical',
            'due_at' => now()->subDay(),
        ]);

        $this->assertSame(0, $task->priority_score);
        $this->assertSame('completed', $task->priority_band);
        $this->assertNotNull($task->completed_at);
    }
}
