<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyOpsFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filters_tasks_by_title(): void
    {
        [$user, $organization] = $this->context();

        $this->task(
            $user,
            $organization,
            'Enviar informe SUNARP',
            'high',
            'high',
        );

        $this->task(
            $user,
            $organization,
            'Comprar repuesto',
            'medium',
            'medium',
        );

        $this->actingAs($user)
            ->get('/mi-dia?q=SUNARP')
            ->assertOk()
            ->assertSee('Enviar informe SUNARP')
            ->assertDontSee('Comprar repuesto');
    }

    public function test_priority_filter_shows_critical_only(): void
    {
        [$user, $organization] = $this->context();

        $this->task(
            $user,
            $organization,
            'Tarea crítica',
            'critical',
            'high',
            now(),
        );

        $this->task(
            $user,
            $organization,
            'Tarea planificada',
            'low',
            'low',
            null,
        );

        $this->actingAs($user)
            ->get('/mi-dia?priority=critical')
            ->assertOk()
            ->assertSee('Tarea crítica')
            ->assertDontSee('Tarea planificada');
    }

    public function test_critical_filter_also_includes_overdue_task(): void
    {
        [$user, $organization] = $this->context();

        $this->task(
            $user,
            $organization,
            'Tarea vencida operativa',
            'low',
            'low',
            now()->subDay(),
        );

        $this->actingAs($user)
            ->get('/mi-dia?priority=critical')
            ->assertOk()
            ->assertSee('Tarea vencida operativa');
    }

    public function test_search_and_scope_can_be_combined(): void
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
            'Revisar contrato ARPYNET',
            'medium',
            'medium',
        );

        $this->task(
            $user,
            $personal,
            'Revisar pasaporte',
            'medium',
            'medium',
        );

        $this->actingAs($user)
            ->get(
                '/mi-dia?scope='
                .$arpynet->id
                .'&q=Revisar',
            )
            ->assertOk()
            ->assertSee('Revisar contrato ARPYNET')
            ->assertDontSee('Revisar pasaporte');
    }

    public function test_action_preserves_all_filters(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
            'Mover tarea',
            'high',
            'high',
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'tomorrow',
                    'scope' => $organization->id,
                    'q' => 'Mover',
                    'priority' => 'critical',
                ],
            )
            ->assertRedirect(
                '/mi-dia?scope='
                .$organization->id
                .'&q=Mover&priority=critical',
            );
    }

    private function task(
        User $user,
        Organization $organization,
        string $title,
        string $urgency,
        string $impact,
        mixed $dueAt = 'default',
    ): Task {
        if ($dueAt === 'default') {
            $dueAt = now();
        }

        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => $title,
            'status' => 'pending',
            'urgency' => $urgency,
            'impact' => $impact,
            'due_at' => $dueAt,
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
