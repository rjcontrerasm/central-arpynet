<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTaskWaitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_put_task_on_waiting(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/esperar",
                [
                    'waiting_until' => '2026-09-04',
                    'waiting_reason' =>
                        'Esperando respuesta del cliente',
                ],
            )
            ->assertRedirect('/mi-dia');

        $task->refresh();

        $this->assertNotNull(
            $task->waiting_since,
        );

        $this->assertSame(
            '2026-09-04',
            $task->waiting_until->format('Y-m-d'),
        );

        $this->assertSame(
            'Esperando respuesta del cliente',
            $task->waiting_reason,
        );
    }

    public function test_waiting_task_is_shown_in_waiting_section(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $task->forceFill([
            'waiting_since' => now(),
            'waiting_until' => '2026-09-04',
            'waiting_reason' => 'Esperando aprobación',
        ])->save();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('En espera')
            ->assertSee('Esperando aprobación')
            ->assertSee('Reactivar');
    }

    public function test_user_can_resume_waiting_task(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $task->forceFill([
            'waiting_since' => now(),
            'waiting_until' => '2026-09-04',
            'waiting_reason' => 'Esperando aprobación',
        ])->save();

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/reactivar",
            )
            ->assertRedirect('/mi-dia');

        $task->refresh();

        $this->assertNull($task->waiting_since);
        $this->assertNull($task->waiting_until);
        $this->assertNull($task->waiting_reason);
    }

    public function test_foreign_task_cannot_be_put_on_waiting(): void
    {
        [$user] = $this->context();

        $foreignUser = User::factory()->create();

        $foreignOrg = Organization::query()->create([
            'name' => 'Ajena',
            'slug' => 'ajena',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $foreignUser->id,
        ]);

        $foreignOrg->users()->attach(
            $foreignUser->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $task = $this->task(
            $foreignUser,
            $foreignOrg,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/esperar",
                [
                    'waiting_until' => '2026-09-04',
                    'waiting_reason' => 'No permitido',
                ],
            )
            ->assertForbidden();
    }

    private function task(
        User $user,
        Organization $organization,
    ): Task {
        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea seguimiento',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'due_at' => '2026-09-10 17:00:00',
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
