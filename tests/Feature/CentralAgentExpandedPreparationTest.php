<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralAgentExpandedPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_human_can_prepare_expanded_project_actions_without_mutating_project(): void
    {
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto acciones ampliadas',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'blockers' => 'Esperando proveedor',
            'created_by' => $user->id,
        ]);

        $before = $project->fresh()->getAttributes();

        $this->actingAs($user)
            ->post('/jarvis/preparar-propuesta', [
                'subject_type' => 'project',
                'subject_id' => $project->id,
                'action' => 'project.status.set',
                'project_status' => 'on_hold',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/jarvis/preparar-propuesta', [
                'subject_type' => 'project',
                'subject_id' => $project->id,
                'action' => 'project.blockers.clear',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/jarvis/preparar-propuesta', [
                'subject_type' => 'project',
                'subject_id' => $project->id,
                'action' => 'project.task.create',
                'project_task_title' => 'Revisar dependencia externa',
                'project_task_urgency' => 'high',
                'project_task_due_date' => '2026-09-20',
            ])
            ->assertRedirect();

        $this->assertSame(
            $before,
            $project->fresh()->getAttributes(),
        );

        $this->assertDatabaseCount('tasks', 0);

        $statusProposal = AgentActionProposal::query()
            ->where('action_key', 'project.status.set')
            ->firstOrFail();

        $this->assertSame(
            'on_hold',
            $statusProposal->proposed_changes['status'],
        );

        $blockerProposal = AgentActionProposal::query()
            ->where('action_key', 'project.blockers.clear')
            ->firstOrFail();

        $this->assertArrayHasKey(
            'blockers',
            $blockerProposal->proposed_changes,
        );
        $this->assertNull(
            $blockerProposal->proposed_changes['blockers'],
        );

        $taskProposal = AgentActionProposal::query()
            ->where('action_key', 'project.task.create')
            ->firstOrFail();

        $this->assertSame(
            'Revisar dependencia externa',
            $taskProposal->proposed_changes['task']['title'],
        );
        $this->assertSame(
            'high',
            $taskProposal->proposed_changes['task']['urgency'],
        );
    }

    public function test_human_can_prepare_service_stage_change_without_mutating_service(): void
    {
        [$user, $organization] = $this->context();

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente acciones ampliadas',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio acciones ampliadas',
            'stage' => 'quotation',
            'currency' => 'PEN',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post('/jarvis/preparar-propuesta', [
                'subject_type' => 'service_order',
                'subject_id' => $service->id,
                'action' => 'service_order.stage.set',
                'service_stage' => 'execution',
            ])
            ->assertRedirect();

        $this->assertSame(
            'quotation',
            $service->fresh()->stage,
        );

        $proposal = AgentActionProposal::query()
            ->where('action_key', 'service_order.stage.set')
            ->firstOrFail();

        $this->assertSame(
            'execution',
            $proposal->proposed_changes['stage'],
        );
    }

    public function test_same_project_status_or_service_stage_is_rejected_as_stale_without_proposal(): void
    {
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto sin cambio real',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente sin cambio real',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio sin cambio real',
            'stage' => 'quotation',
            'currency' => 'PEN',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post('/jarvis/preparar-propuesta', [
                'subject_type' => 'project',
                'subject_id' => $project->id,
                'action' => 'project.status.set',
                'project_status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHas('agent_proposal_message');

        $this->actingAs($user)
            ->post('/jarvis/preparar-propuesta', [
                'subject_type' => 'service_order',
                'subject_id' => $service->id,
                'action' => 'service_order.stage.set',
                'service_stage' => 'quotation',
            ])
            ->assertRedirect()
            ->assertSessionHas('agent_proposal_message');

        $this->assertDatabaseCount('agent_action_proposals', 0);
    }

    public function test_project_task_creation_is_blocked_for_closed_project(): void
    {
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto cerrado',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post('/jarvis/preparar-propuesta', [
                'subject_type' => 'project',
                'subject_id' => $project->id,
                'action' => 'project.task.create',
                'project_task_title' => 'No debe crearse',
            ])
            ->assertRedirect()
            ->assertSessionHas('agent_proposal_message');

        $this->assertDatabaseCount('agent_action_proposals', 0);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_expanded_preparation_respects_organization_scope(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Organización ajena expandida',
            'slug' => 'foreign-expanded-preparation',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $project = Project::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'Proyecto ajeno expandido',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post('/jarvis/preparar-propuesta', [
                'subject_type' => 'project',
                'subject_id' => $project->id,
                'action' => 'project.status.set',
                'project_status' => 'on_hold',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('agent_action_proposals', 0);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-expanded-preparation',
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

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
