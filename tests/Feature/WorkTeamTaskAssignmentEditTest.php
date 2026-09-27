<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTeamTaskAssignmentEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_can_be_reassigned_to_team_member_across_organizations(): void
    {
        [$rolando, $lissette, , $pcsotec, $team] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' => $pcsotec->id,
            'title' => 'Facturar servicio PC SOTEC',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'assigned_to' => $rolando->id,
            'created_by' => $rolando->id,
        ]);

        $this->actingAs($rolando)
            ->post(
                route('daily-task-edit.update', $task),
                [
                    'organization_id' => $pcsotec->id,
                    'due_date' => now()
                        ->addDay()
                        ->toDateString(),
                    'urgency' => 'high',
                    'impact' => 'high',
                    'assigned_to' => $lissette->id,
                    'visibility_scope' => 'teams',
                    'work_team_ids' => [$team->id],
                    'view' => 'team',
                    'work_team' => $team->id,
                ],
            )
            ->assertRedirect();

        $task->refresh();

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
        $this->assertFalse(
            $lissette->canAccessOrganization(
                $pcsotec->id,
            ),
        );
        $this->assertTrue(
            Task::query()
                ->visibleTo($lissette)
                ->whereKey($task->id)
                ->exists(),
        );
        $this->assertTrue(
            $task->canBeUpdatedBy($lissette),
        );
    }

    public function test_switching_back_to_organization_visibility_detaches_teams(): void
    {
        [$rolando, $lissette, $arpynet, , $team] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' => $arpynet->id,
            'title' => 'Tarea administrativa ARPYNET',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'assigned_to' => $lissette->id,
            'created_by' => $rolando->id,
            'visibility_scope' => 'teams',
        ]);

        $task->workTeams()->attach($team->id);

        $this->actingAs($rolando)
            ->post(
                route('daily-task-edit.update', $task),
                [
                    'organization_id' => $arpynet->id,
                    'urgency' => 'normal',
                    'impact' => 'normal',
                    'assigned_to' => $lissette->id,
                    'visibility_scope' => 'organization',
                ],
            )
            ->assertRedirect();

        $task->refresh();

        $this->assertSame(
            'organization',
            $task->visibility_scope,
        );
        $this->assertSame(
            0,
            $task->workTeams()->count(),
        );
    }

    public function test_team_visibility_rejects_assignee_outside_selected_team(): void
    {
        [$rolando, , , $pcsotec, $team] =
            $this->context();

        $outside = User::factory()->create([
            'name' => 'Persona externa',
            'is_active' => true,
        ]);

        $task = Task::query()->create([
            'organization_id' => $pcsotec->id,
            'title' => 'Tarea por reasignar',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'assigned_to' => $rolando->id,
            'created_by' => $rolando->id,
        ]);

        $this->actingAs($rolando)
            ->from('/mi-dia')
            ->post(
                route('daily-task-edit.update', $task),
                [
                    'organization_id' => $pcsotec->id,
                    'urgency' => 'normal',
                    'impact' => 'normal',
                    'assigned_to' => $outside->id,
                    'visibility_scope' => 'teams',
                    'work_team_ids' => [$team->id],
                ],
            )
            ->assertRedirect('/mi-dia')
            ->assertSessionHasErrors('assigned_to');

        $this->assertSame(
            $rolando->id,
            $task->fresh()->assigned_to,
        );
        $this->assertSame(
            'organization',
            $task->fresh()->visibility_scope,
        );
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
            'slug' => 'arpynet-assignment-edit',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $rolando->id,
        ]);

        $pcsotec = Organization::query()->create([
            'name' => 'PC SOTEC',
            'slug' => 'pcsotec-assignment-edit',
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

        $team = WorkTeam::query()->create([
            'home_organization_id' => $arpynet->id,
            'name' => 'Administración',
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
