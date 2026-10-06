<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyOpsWorkViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_views_separate_mine_team_and_unassigned(): void
    {
        [$owner, $teammate, $organization, $team] = $this->context();

        $mine = $this->task(
            $organization,
            'Solo mía',
            $owner,
        );

        $teammateTask = $this->task(
            $organization,
            'De compañera',
            $teammate,
        );

        $unassigned = $this->task(
            $organization,
            'Sin responsable',
        );

        $legacyOrganizationTask = $this->task(
            $organization,
            'Antigua de toda la empresa',
            $owner,
        );

        $mine->workTeams()->attach($team->id);
        $teammateTask->workTeams()->attach($team->id);
        $unassigned->workTeams()->attach($team->id);

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
            ->assertDontSee('Antigua de toda la empresa')
            ->assertSee('Responsable:')
            ->assertSee($teammate->name);

        $this->actingAs($owner)
            ->get('/mi-dia?view=unassigned')
            ->assertOk()
            ->assertDontSee('Solo mía')
            ->assertDontSee('De compañera')
            ->assertSee('Sin responsable');
    }

    public function test_default_team_opens_mi_dia_in_team_context(): void
    {
        [$owner, $teammate, $organization, $team] =
            $this->context();

        $teammate->forceFill([
            'default_work_team_id' => $team->id,
        ])->save();

        $teamTask = $this->task(
            $organization,
            'Administración compartida',
            $owner,
        );

        $teamTask->forceFill([
            'visibility_scope' => 'teams',
        ])->save();
        $teamTask->workTeams()->attach($team->id);

        $this->actingAs($teammate)
            ->get('/mi-dia')
            ->assertOk()
            ->assertViewHas(
                'selectedWorkTeam',
                $team->id,
            )
            ->assertViewHas(
                'selectedWorkView',
                'team',
            )
            ->assertSee('Administración compartida')
            ->assertDontSee('class="pill team-context"', false)
            ->assertSee('task-card-heading', false)
            ->assertSee('task-organization-badge', false);
    }

    public function test_explicit_mine_view_overrides_default_team(): void
    {
        [$owner, $teammate, $organization, $team] =
            $this->context();

        $teammate->forceFill([
            'default_work_team_id' => $team->id,
        ])->save();

        $mine = $this->task(
            $organization,
            'Tarea personal explícita',
            $teammate,
        );

        $shared = $this->task(
            $organization,
            'Tarea de equipo explícita',
            $owner,
        );

        $shared->forceFill([
            'visibility_scope' => 'teams',
        ])->save();
        $shared->workTeams()->attach($team->id);

        $this->actingAs($teammate)
            ->get('/mi-dia?view=mine')
            ->assertOk()
            ->assertViewHas(
                'selectedWorkTeam',
                null,
            )
            ->assertViewHas(
                'selectedWorkView',
                'mine',
            )
            ->assertSee('Tarea personal explícita')
            ->assertDontSee('Tarea de equipo explícita');

        $this->assertNotNull($mine);
    }

    public function test_service_orders_follow_selected_work_view(): void
    {
        [$owner, $teammate, $organization, $team] = $this->context();

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente Daily Ops',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio asignado al equipo',
            'stage' => 'execution',
            'assigned_to' => $teammate->id,
            'work_team_id' => $team->id,
            'created_by' => $owner->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
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

    public function test_mi_dia_default_includes_services_from_all_user_teams(): void
    {
        [$owner, , $organization, $administration] =
            $this->context();

        $marisol = User::factory()->create([
            'name' => 'Marisol',
            'email' => 'arpynetsac@gmail.com',
            'is_active' => true,
        ]);

        $support = WorkTeam::query()->create([
            'home_organization_id' => $organization->id,
            'name' => 'Soporte',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $administration->users()->attach(
            $marisol->id,
            [
                'role' => 'member',
                'is_active' => true,
            ],
        );

        $support->users()->attach(
            $marisol->id,
            [
                'role' => 'member',
                'is_active' => true,
            ],
        );

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente servicios por equipo',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio de Administración',
            'stage' => 'execution',
            'assigned_to' => $owner->id,
            'work_team_id' => $administration->id,
            'created_by' => $owner->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio de Soporte',
            'stage' => 'execution',
            'assigned_to' => $owner->id,
            'work_team_id' => $support->id,
            'created_by' => $owner->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio fuera de sus equipos',
            'stage' => 'execution',
            'assigned_to' => $owner->id,
            'work_team_id' => null,
            'created_by' => $owner->id,
        ]);

        $this->assertFalse(
            $marisol->canAccessOrganization(
                $organization->id,
            ),
        );

        $this->actingAs($marisol)
            ->get('/mi-dia')
            ->assertOk()
            ->assertViewHas('selectedWorkView', 'mine')
            ->assertViewHas('selectedWorkTeam', null)
            ->assertSee('Servicio de Administración')
            ->assertSee('Servicio de Soporte')
            ->assertDontSee('Servicio fuera de sus equipos')
            ->assertSee($organization->name);

        $this->actingAs($marisol)
            ->get('/mi-dia?work_team='.$administration->id)
            ->assertOk()
            ->assertViewHas('selectedWorkView', 'team')
            ->assertViewHas(
                'selectedWorkTeam',
                $administration->id,
            )
            ->assertSee('Servicio de Administración')
            ->assertDontSee('Servicio de Soporte')
            ->assertDontSee('Servicio fuera de sus equipos');
    }

    public function test_viewer_sees_team_work_without_mutation_controls(): void
    {
        [$owner, , $organization, $team] = $this->context();

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

        $viewerTask = $this->task(
            $organization,
            'Tarea visible sin edición',
            $owner,
        );

        $team->users()->attach(
            $viewer->id,
            [
                'role' => 'viewer',
                'is_active' => true,
            ],
        );

        $viewerTask->forceFill([
            'visibility_scope' => 'teams',
        ])->save();
        $viewerTask->workTeams()->attach($team->id);

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

        $team = WorkTeam::query()->create([
            'home_organization_id' => $organization->id,
            'name' => 'Administración',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $team->users()->attach([
            $owner->id => [
                'role' => 'lead',
                'is_active' => true,
            ],
            $teammate->id => [
                'role' => 'member',
                'is_active' => true,
            ],
        ]);

        return [$owner, $teammate, $organization, $team];
    }
}
