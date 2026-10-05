<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTeamQuickCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_cross_organization_team_task_with_single_assignee(): void
    {
        [$rolando, $lissette, $arpynet, $pcsotec, $team] =
            $this->context();

        $this->actingAs($rolando)
            ->get('/captura')
            ->assertOk()
            ->assertSee('Responsable')
            ->assertSee('Visibilidad')
            ->assertSee('Equipos con acceso')
            ->assertSee('Administración');

        $this->actingAs($rolando)
            ->post('/captura', [
                'organization_id' => $pcsotec->id,
                'title' => 'Facturar servicio Y de PC SOTEC',
                'due_mode' => 'today',
                'urgency' => 'normal',
                'impact' => 'high',
                'assigned_to' => $lissette->id,
                'visibility_scope' => 'teams',
                'work_team_ids' => [$team->id],
            ])
            ->assertRedirect('/captura');

        $task = Task::query()
            ->where(
                'title',
                'Facturar servicio Y de PC SOTEC',
            )
            ->firstOrFail();

        $this->assertSame(
            $pcsotec->id,
            $task->organization_id,
        );
        $this->assertSame(
            $lissette->id,
            $task->assigned_to,
        );
        $this->assertSame(
            'teams',
            $task->visibility_scope,
        );
        $this->assertTrue(
            $task->workTeams()
                ->whereKey($team->id)
                ->exists(),
        );

        $this->assertTrue(
            Task::query()
                ->visibleTo($lissette)
                ->whereKey($task->id)
                ->exists(),
        );

        $this->assertFalse(
            $lissette->canAccessOrganization(
                $pcsotec->id,
            ),
        );
    }

    public function test_selected_team_normalizes_contradictory_organization_visibility(): void
    {
        [$rolando, $lissette, $arpynet, , $team] =
            $this->context();

        $this->actingAs($lissette)
            ->post('/captura', [
                'organization_id' => $arpynet->id,
                'title' => 'Pendiente para el equipo de administración',
                'due_mode' => 'today',
                'urgency' => 'normal',
                'impact' => 'normal',
                'assigned_to' => $lissette->id,
                'visibility_scope' => 'organization',
                'work_team_ids' => [$team->id],
            ])
            ->assertRedirect('/captura');

        $task = Task::query()
            ->where(
                'title',
                'Pendiente para el equipo de administración',
            )
            ->firstOrFail();

        $this->assertSame(
            'teams',
            $task->visibility_scope,
        );

        $this->assertTrue(
            $task->workTeams()
                ->whereKey($team->id)
                ->exists(),
        );

        $this->assertTrue(
            Task::query()
                ->visibleTo($rolando)
                ->whereKey($task->id)
                ->exists(),
        );
    }

    public function test_legacy_capture_defaults_to_creator_and_organization_visibility(): void
    {
        [$rolando, , $arpynet] =
            $this->context();

        $this->actingAs($rolando)
            ->post('/captura', [
                'organization_id' => $arpynet->id,
                'title' => 'Tarea normal',
                'due_mode' => 'none',
                'urgency' => 'normal',
                'impact' => 'normal',
            ])
            ->assertRedirect('/captura');

        $task = Task::query()
            ->where('title', 'Tarea normal')
            ->firstOrFail();

        $this->assertSame(
            $rolando->id,
            $task->assigned_to,
        );
        $this->assertSame(
            'organization',
            $task->visibility_scope,
        );
        $this->assertSame(
            0,
            $task->workTeams()->count(),
        );
    }

    public function test_user_default_team_preselects_capture_outside_team_context(): void
    {
        [$rolando, $lissette, , , $team] =
            $this->context();

        $lissette->forceFill([
            'default_work_team_id' => $team->id,
        ])->save();

        $this->actingAs($lissette)
            ->get('/captura')
            ->assertOk()
            ->assertViewHas(
                'defaultWorkTeamId',
                $team->id,
            )
            ->assertViewHas(
                'defaultVisibilityScope',
                'teams',
            );

        $this->actingAs($rolando)
            ->get('/captura')
            ->assertOk()
            ->assertViewHas(
                'defaultWorkTeamId',
                null,
            )
            ->assertViewHas(
                'defaultVisibilityScope',
                'organization',
            );
    }

    public function test_team_view_context_overrides_user_default_team(): void
    {
        [$rolando, $lissette, $arpynet, , $team] =
            $this->context();

        $support = WorkTeam::query()->create([
            'home_organization_id' => $arpynet->id,
            'name' => 'Soporte',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $support->users()->attach([
            $rolando->id => [
                'role' => 'lead',
                'is_active' => true,
            ],
            $lissette->id => [
                'role' => 'member',
                'is_active' => true,
            ],
        ]);

        $lissette->forceFill([
            'default_work_team_id' => $team->id,
        ])->save();

        $this->actingAs($lissette)
            ->get('/captura?work_team='.$support->id)
            ->assertOk()
            ->assertViewHas(
                'contextWorkTeamId',
                $support->id,
            )
            ->assertViewHas(
                'defaultWorkTeamId',
                $support->id,
            )
            ->assertViewHas(
                'defaultVisibilityScope',
                'teams',
            );
    }

    public function test_contextual_capture_prefills_team_and_company(): void
    {
        [$rolando, $lissette, $arpynet, $pcsotec, $team] =
            $this->context();

        $this->actingAs($rolando)
            ->get(
                '/captura?work_team='.$team->id
                .'&organization_id='.$pcsotec->id,
            )
            ->assertOk()
            ->assertViewHas(
                'contextWorkTeamId',
                $team->id,
            )
            ->assertViewHas(
                'defaultOrganizationId',
                $pcsotec->id,
            )
            ->assertSee('Contexto de captura')
            ->assertSee('Equipo: Administración')
            ->assertSee('Empresa: PC SOTEC')
            ->assertSee(
                'data-work-team-ids="'.$team->id.'"',
                false,
            )
            ->assertSee(
                'data-organization-ids="'.$arpynet->id.'"',
                false,
            );
    }

    public function test_team_member_can_capture_in_existing_transversal_company_scope(): void
    {
        [$rolando, $lissette, , $pcsotec, $team] =
            $this->context();

        $seed = Task::query()->create([
            'organization_id' => $pcsotec->id,
            'title' => 'Referencia administrativa PC SOTEC',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'assigned_to' => $rolando->id,
            'created_by' => $rolando->id,
            'visibility_scope' => 'teams',
        ]);

        $seed->workTeams()->attach($team->id);

        $this->assertFalse(
            $lissette->canAccessOrganization(
                $pcsotec->id,
            ),
        );

        $this->actingAs($lissette)
            ->get(
                '/captura?work_team='.$team->id
                .'&organization_id='.$pcsotec->id,
            )
            ->assertOk()
            ->assertViewHas(
                'defaultOrganizationId',
                $pcsotec->id,
            )
            ->assertSee('Empresa: PC SOTEC');

        $this->actingAs($lissette)
            ->post('/captura', [
                'organization_id' => $pcsotec->id,
                'title' => 'Facturar renovación PC SOTEC',
                'due_mode' => 'today',
                'urgency' => 'normal',
                'impact' => 'high',
                'assigned_to' => $lissette->id,
                'visibility_scope' => 'teams',
                'work_team_ids' => [$team->id],
                'capture_context_work_team_id' =>
                    $team->id,
                'capture_context_organization_id' =>
                    $pcsotec->id,
            ])
            ->assertRedirect(
                '/captura?work_team='.$team->id
                .'&organization_id='.$pcsotec->id,
            );

        $task = Task::query()
            ->where(
                'title',
                'Facturar renovación PC SOTEC',
            )
            ->firstOrFail();

        $this->assertSame(
            $pcsotec->id,
            $task->organization_id,
        );
        $this->assertSame(
            'teams',
            $task->visibility_scope,
        );
        $this->assertTrue(
            $task->workTeams()
                ->whereKey($team->id)
                ->exists(),
        );
    }

    public function test_team_member_cannot_turn_transversal_scope_into_company_access(): void
    {
        [$rolando, $lissette, , $pcsotec, $team] =
            $this->context();

        $seed = Task::query()->create([
            'organization_id' => $pcsotec->id,
            'title' => 'Ámbito transversal existente',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'assigned_to' => $rolando->id,
            'created_by' => $rolando->id,
            'visibility_scope' => 'teams',
        ]);

        $seed->workTeams()->attach($team->id);

        $this->actingAs($lissette)
            ->post('/captura', [
                'organization_id' => $pcsotec->id,
                'title' => 'Intento de ampliar acceso',
                'due_mode' => 'none',
                'urgency' => 'normal',
                'impact' => 'normal',
                'assigned_to' => $lissette->id,
                'visibility_scope' => 'organization',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', [
            'title' => 'Intento de ampliar acceso',
        ]);
    }

    public function test_capture_rejects_foreign_company_context(): void
    {
        [$rolando] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa fuera de contexto',
            'slug' => 'empresa-fuera-captura',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $this->actingAs($rolando)
            ->get(
                '/captura?organization_id='
                .$foreign->id,
            )
            ->assertForbidden();
    }

    public function test_contextual_capture_preserves_team_after_save(): void
    {
        [$rolando, $lissette, $arpynet, , $team] =
            $this->context();

        $this->actingAs($rolando)
            ->post('/captura', [
                'organization_id' => $arpynet->id,
                'title' => 'Tarea contextual administración',
                'due_mode' => 'today',
                'urgency' => 'normal',
                'impact' => 'normal',
                'assigned_to' => $lissette->id,
                'visibility_scope' => 'teams',
                'work_team_ids' => [$team->id],
                'capture_context_work_team_id' =>
                    $team->id,
            ])
            ->assertRedirect(
                '/captura?work_team='.$team->id
                .'&organization_id='.$arpynet->id,
            );

        $task = Task::query()
            ->where(
                'title',
                'Tarea contextual administración',
            )
            ->firstOrFail();

        $this->assertTrue(
            $task->workTeams()
                ->whereKey($team->id)
                ->exists(),
        );
    }

    public function test_team_task_rejects_assignee_outside_selected_team(): void
    {
        [$rolando, , , $pcsotec, $team] =
            $this->context();

        $outside = User::factory()->create([
            'name' => 'Persona externa',
            'is_active' => true,
        ]);

        $this->actingAs($rolando)
            ->from('/captura')
            ->post('/captura', [
                'organization_id' => $pcsotec->id,
                'title' => 'Asignación inválida',
                'due_mode' => 'none',
                'urgency' => 'normal',
                'impact' => 'normal',
                'assigned_to' => $outside->id,
                'visibility_scope' => 'teams',
                'work_team_ids' => [$team->id],
            ])
            ->assertRedirect('/captura')
            ->assertSessionHasErrors('assigned_to');

        $this->assertDatabaseMissing('tasks', [
            'title' => 'Asignación inválida',
        ]);
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

        $arpynet = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-team-capture',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $pcsotec = Organization::query()->create([
            'name' => 'PC SOTEC',
            'slug' => 'pcsotec-team-capture',
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
        ]);

        $pcsotec->users()->attach(
            $rolando->id,
            [
                'role' => 'owner',
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $rolando->forceFill([
            'current_organization_id' =>
                $arpynet->id,
        ])->save();

        $team = WorkTeam::query()->create([
            'home_organization_id' =>
                $arpynet->id,
            'name' => 'Administración',
            'description' =>
                'Administración transversal',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $team->users()->attach([
            $rolando->id => [
                'role' => 'lead',
                'is_active' => true,
            ],
            $lissette->id => [
                'role' => 'member',
                'is_active' => true,
            ],
        ]);

        return [
            $rolando,
            $lissette,
            $arpynet,
            $pcsotec,
            $team,
        ];
    }
}
