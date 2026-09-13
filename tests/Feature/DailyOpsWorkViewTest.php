<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyOpsWorkViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_views_separate_mine_team_and_unassigned(): void
    {
        [$owner, $teammate, $organization] = $this->context();

        $this->task(
            $organization,
            'Solo mía',
            $owner,
        );

        $this->task(
            $organization,
            'De compañera',
            $teammate,
        );

        $this->task(
            $organization,
            'Sin responsable',
        );

        $this->actingAs($owner)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Mi bandeja')
            ->assertSee('Mi equipo')
            ->assertSee('Sin asignar')
            ->assertSee('Solo mía')
            ->assertDontSee('De compañera')
            ->assertDontSee('Sin responsable');

        $this->actingAs($owner)
            ->get('/mi-dia?view=team')
            ->assertOk()
            ->assertSee('Solo mía')
            ->assertSee('De compañera')
            ->assertSee('Sin responsable')
            ->assertSee('Responsable:')
            ->assertSee($teammate->name);

        $this->actingAs($owner)
            ->get('/mi-dia?view=unassigned')
            ->assertOk()
            ->assertDontSee('Solo mía')
            ->assertDontSee('De compañera')
            ->assertSee('Sin responsable');
    }

    public function test_service_orders_follow_selected_work_view(): void
    {
        [$owner, $teammate, $organization] = $this->context();

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Servicio asignado al equipo',
            'stage' => 'execution',
            'assigned_to' => $teammate->id,
            'created_by' => $owner->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Servicio sin responsable',
            'stage' => 'quotation',
            'assigned_to' => null,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get('/mi-dia?view=team')
            ->assertOk()
            ->assertSee('Órdenes y servicios')
            ->assertSee('Servicio asignado al equipo')
            ->assertSee('Servicio sin responsable')
            ->assertSee($teammate->name);

        $this->actingAs($owner)
            ->get('/mi-dia?view=unassigned')
            ->assertOk()
            ->assertDontSee('Servicio asignado al equipo')
            ->assertSee('Servicio sin responsable');
    }

    public function test_viewer_sees_team_work_without_mutation_controls(): void
    {
        [$owner, , $organization] = $this->context();

        $viewer = User::factory()->create([
            'name' => 'Usuaria solo lectura',
            'email' => 'viewer@arpynet.com',
        ]);

        $organization->users()->attach(
            $viewer->id,
            [
                'role' => 'viewer',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $this->task(
            $organization,
            'Tarea visible sin edición',
            $owner,
        );

        $this->actingAs($viewer)
            ->get('/mi-dia?view=team')
            ->assertOk()
            ->assertSee('Tarea visible sin edición')
            ->assertDontSee('+ Captura rápida')
            ->assertDontSee('Poner en espera')
            ->assertDontSee('Guardar cambios')
            ->assertDontSee('Hacer recurrente')
            ->assertDontSee('Cancelar');
    }

    public function test_daily_action_preserves_selected_work_view(): void
    {
        [$owner, , $organization] = $this->context();

        $task = $this->task(
            $organization,
            'Mover conservando vista',
            $owner,
        );

        $this->actingAs($owner)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'tomorrow',
                    'scope' => $organization->id,
                    'q' => 'Mover',
                    'priority' => 'critical',
                    'view' => 'team',
                ],
            )
            ->assertRedirect(
                '/mi-dia?scope='
                .$organization->id
                .'&q=Mover&priority=critical&view=team',
            );
    }

    private function task(
        Organization $organization,
        string $title,
        ?User $assignee = null,
    ): Task {
        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => $title,
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => now(),
            'assigned_to' => $assignee?->id,
            'created_by' => $organization->created_by,
        ]);
    }

    private function context(): array
    {
        $owner = User::factory()->create([
            'name' => 'Rolando',
            'email' => 'rcontreras@arpynet.com',
        ]);

        $teammate = User::factory()->create([
            'name' => 'Compañera ARPYNET',
            'email' => 'equipo@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $organization->users()->attach(
            $owner->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $organization->users()->attach(
            $teammate->id,
            [
                'role' => 'member',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        return [$owner, $teammate, $organization];
    }
}
