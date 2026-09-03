<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalUndoTaskLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_cancel_is_reversible_from_global_undo(): void
    {
        [$user, , $task] = $this->context();

        $this->actingAs($user)
            ->post(
                "/tareas/{$task->id}/cancelar",
            )
            ->assertRedirect('/mi-dia');

        $this->assertSame(
            'cancelled',
            $task->fresh()->status,
        );

        $this->post('/deshacer')
            ->assertRedirect('/mi-dia')
            ->assertSessionHas(
                'global_undo_success',
                'Acción deshecha.',
            );

        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );
    }

    public function test_delete_moves_task_to_trash_and_undo_restores_it(): void
    {
        [$user, , $task] = $this->context();

        $this->actingAs($user)
            ->post(
                "/tareas/{$task->id}/eliminar",
            )
            ->assertRedirect('/mi-dia');

        $this->assertSoftDeleted(
            'tasks',
            ['id' => $task->id],
        );

        $this->post('/deshacer')
            ->assertRedirect('/mi-dia');

        $this->assertDatabaseHas(
            'tasks',
            [
                'id' => $task->id,
                'deleted_at' => null,
            ],
        );
    }

    public function test_restore_from_trash_can_itself_be_undone(): void
    {
        [$user, , $task] = $this->context();

        $task->delete();

        $this->actingAs($user)
            ->post(
                "/papelera/tareas/{$task->id}/restaurar",
            )
            ->assertRedirect('/papelera');

        $this->assertDatabaseHas(
            'tasks',
            [
                'id' => $task->id,
                'deleted_at' => null,
            ],
        );

        $this->post('/deshacer')
            ->assertRedirect('/papelera');

        $this->assertSoftDeleted(
            'tasks',
            ['id' => $task->id],
        );
    }

    public function test_permanent_delete_requires_explicit_confirmation_and_has_no_undo(): void
    {
        [$user, , $task] = $this->context();

        $task->delete();

        $this->actingAs($user)
            ->post(
                "/papelera/tareas/{$task->id}/eliminar-definitivamente",
                ['confirmation' => 'NO'],
            )
            ->assertSessionHasErrors(
                'confirmation',
            );

        $this->assertNotNull(
            Task::withTrashed()
                ->find($task->id),
        );

        $this->post(
            "/papelera/tareas/{$task->id}/eliminar-definitivamente",
            ['confirmation' => 'ELIMINAR'],
        )
            ->assertRedirect('/papelera');

        $this->assertNull(
            Task::withTrashed()
                ->find($task->id),
        );
    }

    public function test_only_latest_action_is_undoable(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-03 10:00:00',
        );

        [$user, , $task] = $this->context();

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'tomorrow'],
            )
            ->assertRedirect('/mi-dia');

        $this->post(
            "/mi-dia/tareas/{$task->id}/accion",
            ['action' => 'next_week'],
        )
            ->assertRedirect('/mi-dia');

        $this->post('/deshacer')
            ->assertRedirect('/mi-dia');

        $this->assertSame(
            '2026-09-04',
            $task->fresh()
                ->due_at
                ?->format('Y-m-d'),
        );

        $this->post('/deshacer')
            ->assertRedirect('/mi-dia')
            ->assertSessionHas(
                'global_undo_success',
                'Ya no hay una acción para deshacer.',
            );
    }

    public function test_foreign_user_cannot_use_another_users_undo_action(): void
    {
        [$user, , $task] = $this->context();

        $foreign = User::factory()
            ->create();

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'complete'],
            )
            ->assertRedirect('/mi-dia');

        $this->actingAs($foreign)
            ->post('/mi-dia/deshacer')
            ->assertForbidden();
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' =>
                'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' => 'arpynet',
                'category' => 'company',
                'timezone' =>
                    'America/Lima',
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

        $user->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea ciclo de vida',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                CarbonImmutable::parse(
                    '2026-09-10 17:00:00',
                ),
            'created_by' => $user->id,
        ]);

        return [
            $user,
            $organization,
            $task,
        ];
    }
}
