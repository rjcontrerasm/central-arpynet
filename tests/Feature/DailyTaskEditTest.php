<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTaskEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_edit_task(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/editar",
                [
                    'organization_id' =>
                        $organization->id,
                    'due_date' => '2026-09-05',
                    'urgency' => 'high',
                    'impact' => 'low',
                ],
            )
            ->assertRedirect('/mi-dia');

        $task->refresh();

        $this->assertSame(
            '2026-09-05 17:00:00',
            $task->due_at->format('Y-m-d H:i:s'),
        );

        $this->assertSame('high', $task->urgency);
        $this->assertSame('low', $task->impact);
    }

    public function test_user_can_remove_due_date(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/editar",
                [
                    'organization_id' =>
                        $organization->id,
                    'due_date' => '',
                    'urgency' => 'medium',
                    'impact' => 'medium',
                ],
            )
            ->assertRedirect('/mi-dia');

        $this->assertNull(
            $task->fresh()->due_at,
        );
    }

    public function test_user_can_move_task_to_allowed_scope(): void
    {
        [$user, $organization] = $this->context();

        $personal = Organization::query()->create([
            'name' => 'Personal',
            'slug' => 'personal',
            'category' => 'personal',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $personal->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/editar",
                [
                    'organization_id' => $personal->id,
                    'due_date' => '2026-09-06',
                    'urgency' => 'low',
                    'impact' => 'high',
                ],
            )
            ->assertRedirect('/mi-dia');

        $this->assertSame(
            $personal->id,
            $task->fresh()->organization_id,
        );
    }

    public function test_foreign_scope_is_forbidden(): void
    {
        [$user, $organization] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa ajena',
            'slug' => 'empresa-ajena',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/editar",
                [
                    'organization_id' => $foreign->id,
                    'due_date' => '2026-09-07',
                    'urgency' => 'high',
                    'impact' => 'high',
                ],
            )
            ->assertForbidden();

        $this->assertSame(
            $organization->id,
            $task->fresh()->organization_id,
        );
    }

    private function task(
        User $user,
        Organization $organization,
    ): Task {
        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea editable',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'due_at' => '2026-09-01 17:00:00',
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
