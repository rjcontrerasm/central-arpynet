<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTaskActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_user_can_complete_task(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'complete'],
            )
            ->assertRedirect('/mi-dia');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
    }

    public function test_user_can_move_task_to_tomorrow(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'tomorrow'],
            )
            ->assertRedirect('/mi-dia');

        $this->assertSame(
            '2026-09-02 17:00:00',
            $task->fresh()
                ->due_at
                ->format('Y-m-d H:i:s'),
        );
    }

    public function test_user_can_move_task_one_week(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'next_week'],
            )
            ->assertRedirect('/mi-dia');

        $this->assertSame(
            '2026-09-08 17:00:00',
            $task->fresh()
                ->due_at
                ->format('Y-m-d H:i:s'),
        );
    }

    public function test_foreign_task_is_forbidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa ajena',
            'slug' => 'empresa-ajena',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $task = Task::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'Tarea ajena',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'complete'],
            )
            ->assertForbidden();

        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );
    }

    private function task(
        User $user,
        Organization $organization,
    ): Task {
        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea de prueba',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);
    }

    private function context(): array
    {
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

        $organization->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        return [$user, $organization];
    }
}
