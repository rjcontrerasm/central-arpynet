<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskNextActionQuickEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_next_action_and_return_to_daily(): void
    {
        [$user, $organization, $task] = $this->context();

        $this->actingAs($user)
            ->post("/tareas/{$task->id}/proxima-accion", [
                'next_action' => 'Enviar propuesta final',
                'return_to' => 'daily',
                'scope' => $organization->id,
                'priority' => 'today',
            ])
            ->assertRedirect(route('daily-ops.show', [
                'scope' => $organization->id,
                'priority' => 'today',
            ]));

        $this->assertSame('Enviar propuesta final', $task->fresh()->next_action);
    }

    public function test_tracking_renders_quick_next_action_editor(): void
    {
        [$user, , $task] = $this->context();

        $this->actingAs($user)
            ->get('/seguimiento?type=task&focus=no_next_action')
            ->assertOk()
            ->assertSee($task->title)
            ->assertSee('Definir próxima acción')
            ->assertSee('/proxima-accion', false);
    }

    public function test_foreign_task_cannot_be_edited(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Ajena',
            'slug' => 'ajena-next-action',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $task = Task::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'Tarea ajena',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post("/tareas/{$task->id}/proxima-accion", [
                'next_action' => 'No permitido',
                'return_to' => 'daily',
            ])
            ->assertForbidden();

        $this->assertNull($task->fresh()->next_action);
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

        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Definir siguiente paso',
            'status' => 'in_progress',
            'urgency' => 'normal',
            'impact' => 'normal',
            'next_action' => null,
            'created_by' => $user->id,
        ]);

        return [$user, $organization, $task];
    }
}
