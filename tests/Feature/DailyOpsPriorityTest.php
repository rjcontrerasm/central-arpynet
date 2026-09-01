<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyOpsPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mi_dia_shows_operational_sections(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Ahora')
            ->assertSee('Hoy')
            ->assertSee('Próximos')
            ->assertSee('Sin fecha');
    }

    public function test_scope_filter_hides_other_scope_tasks(): void
    {
        [$user, $arpynet] = $this->context();

        $personal = $this->organization(
            $user,
            'Personal',
            'personal',
        );

        $personal->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $this->task(
            $user,
            $arpynet,
            'Tarea ARPYNET',
        );

        $this->task(
            $user,
            $personal,
            'Tarea Personal',
        );

        $this->actingAs($user)
            ->get(
                '/mi-dia?scope='.$arpynet->id,
            )
            ->assertOk()
            ->assertSee('Tarea ARPYNET')
            ->assertDontSee('Tarea Personal');
    }

    public function test_foreign_scope_filter_is_forbidden(): void
    {
        [$user] = $this->context();

        $foreign = $this->organization(
            $user,
            'Ajena',
            'ajena',
        );

        $this->actingAs($user)
            ->get(
                '/mi-dia?scope='.$foreign->id,
            )
            ->assertForbidden();
    }

    public function test_priority_is_visible_on_task_card(): void
    {
        [$user, $organization] = $this->context();

        $this->task(
            $user,
            $organization,
            'Prioridad visible',
        );

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Prioridad visible')
            ->assertSee('·');
    }

    public function test_action_preserves_scope_filter(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
            'Mover mañana',
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'tomorrow',
                    'scope' => $organization->id,
                ],
            )
            ->assertRedirect(
                '/mi-dia?scope='.$organization->id,
            );
    }

    private function task(
        User $user,
        Organization $organization,
        string $title,
    ): Task {
        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => $title,
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);
    }

    private function organization(
        User $user,
        string $name,
        string $slug,
    ): Organization {
        return Organization::query()->create([
            'name' => $name,
            'slug' => $slug,
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = $this->organization(
            $user,
            'ARPYNET',
            'arpynet',
        );

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
