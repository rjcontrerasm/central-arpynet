<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTeamTaskVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_see_restricted_tasks_from_different_organizations(): void
    {
        [$rolando, $lissette, $marisol, $outsider, $arpynet, $pcsotec] =
            $this->context();

        $team = WorkTeam::query()->create([
            'home_organization_id' => $arpynet->id,
            'name' => 'Administración',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $team->users()->attach([
            $rolando->id => ['role' => 'lead', 'is_active' => true],
            $lissette->id => ['role' => 'member', 'is_active' => true],
            $marisol->id => ['role' => 'member', 'is_active' => true],
        ]);

        $arpynetTask = Task::query()->create([
            'organization_id' => $arpynet->id,
            'title' => 'Facturar servicio X de ARPYNET',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'visibility_scope' => 'teams',
            'assigned_to' => $lissette->id,
            'created_by' => $rolando->id,
        ]);

        $pcsotecTask = Task::query()->create([
            'organization_id' => $pcsotec->id,
            'title' => 'Facturar servicio Y de PC SOTEC',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'visibility_scope' => 'teams',
            'assigned_to' => $marisol->id,
            'created_by' => $rolando->id,
        ]);

        $arpynetTask->workTeams()->attach($team->id);
        $pcsotecTask->workTeams()->attach($team->id);

        $this->assertEqualsCanonicalizing(
            [$arpynetTask->id, $pcsotecTask->id],
            Task::query()
                ->visibleTo($lissette)
                ->pluck('id')
                ->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$arpynetTask->id, $pcsotecTask->id],
            Task::query()
                ->visibleTo($marisol)
                ->pluck('id')
                ->all(),
        );

        $this->assertEmpty(
            Task::query()
                ->visibleTo($outsider)
                ->pluck('id')
                ->all(),
        );
    }

    public function test_team_member_can_work_cross_organization_task_from_mi_dia(): void
    {
        [$rolando, $lissette, $marisol, , $arpynet, $pcsotec] =
            $this->context();

        $team = WorkTeam::query()->create([
            'home_organization_id' => $arpynet->id,
            'name' => 'Administración',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $team->users()->attach([
            $lissette->id => ['role' => 'member', 'is_active' => true],
            $marisol->id => ['role' => 'viewer', 'is_active' => true],
        ]);

        $task = Task::query()->create([
            'organization_id' => $pcsotec->id,
            'title' => 'Facturar servicio Y de PC SOTEC',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'visibility_scope' => 'teams',
            'assigned_to' => $rolando->id,
            'created_by' => $rolando->id,
            'due_at' => now()->addDay(),
        ]);

        $task->workTeams()->attach($team->id);

        $this->actingAs($lissette)
            ->get('/mi-dia?view=team&scope='.$pcsotec->id)
            ->assertOk()
            ->assertSee('Facturar servicio Y de PC SOTEC')
            ->assertSee('PC SOTEC');

        $this->actingAs($lissette)
            ->post(
                route('daily-task-action.update', $task),
                [
                    'action' => 'complete',
                    'view' => 'team',
                    'scope' => $pcsotec->id,
                ],
            )
            ->assertRedirect();

        $this->assertSame(
            'completed',
            $task->fresh()->status,
        );

        $task->refresh()
            ->forceFill([
                'status' => 'pending',
                'completed_at' => null,
            ])
            ->save();

        $this->actingAs($marisol)
            ->get('/mi-dia?view=team&scope='.$pcsotec->id)
            ->assertOk()
            ->assertSee('Facturar servicio Y de PC SOTEC');

        $this->actingAs($marisol)
            ->post(
                route('daily-task-action.update', $task),
                [
                    'action' => 'complete',
                    'view' => 'team',
                    'scope' => $pcsotec->id,
                ],
            )
            ->assertForbidden();

        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );
    }

    public function test_mi_dia_can_filter_all_visible_tasks_by_team_across_organizations(): void
    {
        [$rolando, $lissette, , $outsider, $arpynet, $pcsotec] =
            $this->context();

        $team = WorkTeam::query()->create([
            'home_organization_id' => $arpynet->id,
            'name' => 'Administración',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $team->users()->attach([
            $rolando->id => ['role' => 'lead', 'is_active' => true],
            $lissette->id => ['role' => 'member', 'is_active' => true],
        ]);

        $otherTeam = WorkTeam::query()->create([
            'home_organization_id' => $arpynet->id,
            'name' => 'Cloud',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $otherTeam->users()->attach([
            $rolando->id => ['role' => 'lead', 'is_active' => true],
            $lissette->id => ['role' => 'member', 'is_active' => true],
        ]);

        $arpynetTask = Task::query()->create([
            'organization_id' => $arpynet->id,
            'title' => 'Facturar servicio ARPYNET',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'visibility_scope' => 'teams',
            'assigned_to' => $rolando->id,
            'created_by' => $rolando->id,
            'due_at' => now()->addDay(),
        ]);

        $pcsotecTask = Task::query()->create([
            'organization_id' => $pcsotec->id,
            'title' => 'Facturar servicio PC SOTEC',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'visibility_scope' => 'teams',
            'assigned_to' => $rolando->id,
            'created_by' => $rolando->id,
            'due_at' => now()->addDay(),
        ]);

        $cloudTask = Task::query()->create([
            'organization_id' => $arpynet->id,
            'title' => 'Revisar servidor Cloud',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'visibility_scope' => 'teams',
            'assigned_to' => $rolando->id,
            'created_by' => $rolando->id,
            'due_at' => now()->addDay(),
        ]);

        $arpynetTask->workTeams()->attach($team->id);
        $pcsotecTask->workTeams()->attach($team->id);
        $cloudTask->workTeams()->attach($otherTeam->id);

        $this->actingAs($lissette)
            ->get('/mi-dia?view=team&work_team='.$team->id)
            ->assertOk()
            ->assertSee('Administración')
            ->assertSee('Facturar servicio ARPYNET')
            ->assertSee('Facturar servicio PC SOTEC')
            ->assertDontSee('Revisar servidor Cloud')
            ->assertSee(
                '/captura?work_team='.$team->id,
                false,
            );

        $this->actingAs($outsider)
            ->get('/mi-dia?work_team='.$team->id)
            ->assertForbidden();
    }

    public function test_existing_organization_visibility_remains_backward_compatible(): void
    {
        [$rolando, $lissette, , , $arpynet] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $arpynet->id,
            'title' => 'Pendiente existente',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'created_by' => $rolando->id,
        ]);

        $this->assertSame(
            'organization',
            $task->fresh()->visibility_scope,
        );

        $this->assertTrue(
            Task::query()
                ->visibleTo($lissette)
                ->whereKey($task->id)
                ->exists(),
        );
    }

    public function test_team_viewer_can_read_but_not_update_restricted_task(): void
    {
        [$rolando, $lissette, $marisol, , $arpynet, $pcsotec] =
            $this->context();

        $team = WorkTeam::query()->create([
            'home_organization_id' => $arpynet->id,
            'name' => 'Administración',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $team->users()->attach([
            $lissette->id => ['role' => 'member', 'is_active' => true],
            $marisol->id => ['role' => 'viewer', 'is_active' => true],
        ]);

        $task = Task::query()->create([
            'organization_id' => $pcsotec->id,
            'title' => 'Revisar factura PC SOTEC',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'visibility_scope' => 'teams',
            'assigned_to' => $rolando->id,
            'created_by' => $rolando->id,
        ]);

        $task->workTeams()->attach($team->id);

        $this->assertTrue(
            Task::query()
                ->visibleTo($marisol)
                ->whereKey($task->id)
                ->exists(),
        );
        $this->assertFalse($task->canBeUpdatedBy($marisol));
        $this->assertTrue($task->canBeUpdatedBy($lissette));
    }

    public function test_assignee_keeps_access_even_when_not_member_of_associated_team(): void
    {
        [$rolando, $lissette, , , $arpynet, $pcsotec] = $this->context();

        $team = WorkTeam::query()->create([
            'home_organization_id' => $arpynet->id,
            'name' => 'Administración',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $task = Task::query()->create([
            'organization_id' => $pcsotec->id,
            'title' => 'Caso asignado directamente',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'visibility_scope' => 'teams',
            'assigned_to' => $lissette->id,
            'created_by' => $rolando->id,
        ]);

        $task->workTeams()->attach($team->id);

        $this->assertTrue(
            Task::query()
                ->visibleTo($lissette)
                ->whereKey($task->id)
                ->exists(),
        );
        $this->assertTrue($task->canBeUpdatedBy($lissette));
    }

    private function context(): array
    {
        $rolando = User::factory()->create([
            'name' => 'Rolando',
            'is_active' => true,
        ]);
        $lissette = User::factory()->create([
            'name' => 'Lissette',
            'is_active' => true,
        ]);
        $marisol = User::factory()->create([
            'name' => 'Marisol',
            'is_active' => true,
        ]);
        $outsider = User::factory()->create([
            'name' => 'Usuario fuera del equipo',
            'is_active' => true,
        ]);

        $arpynet = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-work-team-test',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $pcsotec = Organization::query()->create([
            'name' => 'PC SOTEC',
            'slug' => 'pcsotec-work-team-test',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $arpynet->users()->attach([
            $rolando->id => [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
            $lissette->id => [
                'role' => 'member',
                'is_default' => false,
                'is_active' => true,
            ],
            $marisol->id => [
                'role' => 'member',
                'is_default' => false,
                'is_active' => true,
            ],
            $outsider->id => [
                'role' => 'member',
                'is_default' => false,
                'is_active' => true,
            ],
        ]);

        $pcsotec->users()->attach($rolando->id, [
            'role' => 'owner',
            'is_default' => false,
            'is_active' => true,
        ]);

        return [
            $rolando,
            $lissette,
            $marisol,
            $outsider,
            $arpynet,
            $pcsotec,
        ];
    }
}
