<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Support\CentralAgentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralAgentProposalExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_proposal_needs_second_confirmation_executes_and_undoes(): void
    {
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto ejecutar Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' => 'Antes',
            'created_by' => $user->id,
        ]);

        $proposal = app(CentralAgentGateway::class)
            ->proposeProjectAction(
                $user,
                $project,
                'project.next_action.set',
                ['next_action' => 'Después'],
            );

        $this->actingAs($user)
            ->post("/jarvis/propuestas/{$proposal->id}/aprobar")
            ->assertRedirect();

        $this->actingAs($user)
            ->post("/jarvis/propuestas/{$proposal->id}/ejecutar")
            ->assertSessionHasErrors('confirm_execution');

        $this->assertSame('Antes', $project->fresh()->next_action);

        $this->actingAs($user)
            ->post(
                "/jarvis/propuestas/{$proposal->id}/ejecutar",
                ['confirm_execution' => '1'],
            )
            ->assertRedirect('/jarvis?status=executed');

        $proposal->refresh();

        $this->assertSame('executed', $proposal->status);
        $this->assertNotNull($proposal->undo_action_id);
        $this->assertSame('Después', $project->fresh()->next_action);

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'event' => 'agent_proposal.executed',
                'subject_id' => $proposal->id,
            ],
        );

        $this->actingAs($user)
            ->post('/deshacer', ['undo_id' => $proposal->undo_action_id])
            ->assertRedirect();

        $this->assertSame('Antes', $project->fresh()->next_action);
    }

    public function test_service_and_task_execution_support_undo(): void
    {
        [$user, $organization] = $this->context();

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente ejecución',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio ejecución',
            'stage' => 'quotation',
            'currency' => 'PEN',
            'created_by' => $user->id,
        ]);

        $serviceProposal = app(CentralAgentGateway::class)
            ->proposeServiceOrderAction(
                $user,
                $service,
                'service_order.stage.set',
                ['stage' => 'execution'],
            );

        $this->approveAndExecute($user, $serviceProposal->id);
        $serviceProposal->refresh();

        $this->assertSame('execution', $service->fresh()->stage);

        $this->actingAs($user)
            ->post('/deshacer', ['undo_id' => $serviceProposal->undo_action_id])
            ->assertRedirect();

        $this->assertSame('quotation', $service->fresh()->stage);

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea ejecución Jarvis',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'created_by' => $user->id,
        ]);

        $taskProposal = app(CentralAgentGateway::class)
            ->proposeTaskAction(
                $user,
                $task,
                'complete',
            );

        $this->approveAndExecute($user, $taskProposal->id);
        $taskProposal->refresh();

        $this->assertSame('completed', $task->fresh()->status);

        $this->actingAs($user)
            ->post('/deshacer', ['undo_id' => $taskProposal->undo_action_id])
            ->assertRedirect();

        $this->assertSame('pending', $task->fresh()->status);
    }

    public function test_project_task_creation_executes_and_undo_soft_deletes_created_task(): void
    {
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto crear tarea',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $proposal = app(CentralAgentGateway::class)
            ->proposeProjectAction(
                $user,
                $project,
                'project.task.create',
                [
                    'title' => 'Tarea creada por confirmación',
                    'urgency' => 'high',
                    'due_date' => '2026-09-09',
                ],
            );

        $this->approveAndExecute($user, $proposal->id);
        $proposal->refresh();

        $task = Task::query()
            ->where('project_id', $project->id)
            ->where('title', 'Tarea creada por confirmación')
            ->firstOrFail();

        $this->assertSame('central_agent', $task->source);

        $this->actingAs($user)
            ->post('/deshacer', ['undo_id' => $proposal->undo_action_id])
            ->assertRedirect();

        $this->assertTrue(
            Task::withTrashed()->findOrFail($task->id)->trashed(),
        );
    }

    public function test_stale_entity_is_blocked_without_overwrite(): void
    {
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto stale Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' => 'Original',
            'created_by' => $user->id,
        ]);

        $proposal = app(CentralAgentGateway::class)
            ->proposeProjectAction(
                $user,
                $project,
                'project.next_action.set',
                ['next_action' => 'Propuesto'],
            );

        $this->actingAs($user)
            ->post("/jarvis/propuestas/{$proposal->id}/aprobar")
            ->assertRedirect();

        $project->forceFill([
            'next_action' => 'Cambio humano posterior',
        ])->save();

        $this->actingAs($user)
            ->post(
                "/jarvis/propuestas/{$proposal->id}/ejecutar",
                ['confirm_execution' => '1'],
            )
            ->assertRedirect('/jarvis?status=stale');

        $proposal->refresh();

        $this->assertSame('stale', $proposal->status);
        $this->assertNull($proposal->executed_at);
        $this->assertSame(
            'Cambio humano posterior',
            $project->fresh()->next_action,
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'event' => 'agent_proposal.stale',
                'subject_id' => $proposal->id,
            ],
        );
    }

    public function test_execution_stores_before_after_actor_and_ui_button(): void
    {
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto trazabilidad',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'blockers' => 'Pendiente proveedor',
            'created_by' => $user->id,
        ]);

        $proposal = app(CentralAgentGateway::class)
            ->proposeProjectAction(
                $user,
                $project,
                'project.blockers.clear',
            );

        $this->actingAs($user)
            ->post("/jarvis/propuestas/{$proposal->id}/aprobar")
            ->assertRedirect('/jarvis?status=approved');

        $this->actingAs($user)
            ->get('/jarvis?status=approved')
            ->assertOk()
            ->assertSee('Ejecutar cambio')
            ->assertSee('Segunda confirmación', false);

        $this->actingAs($user)
            ->post(
                "/jarvis/propuestas/{$proposal->id}/ejecutar",
                ['confirm_execution' => '1'],
            )
            ->assertRedirect('/jarvis?status=executed');

        $proposal->refresh();

        $this->assertSame($user->id, $proposal->executed_by);
        $this->assertNotNull($proposal->executed_at);
        $this->assertIsArray($proposal->execution_before);
        $this->assertIsArray($proposal->execution_after);
        $this->assertSame(
            'Pendiente proveedor',
            $proposal->execution_before['blockers'],
        );
        $this->assertNull(
            $proposal->execution_after['blockers'],
        );
    }

    private function approveAndExecute(
        User $user,
        int $proposalId,
    ): void {
        $this->actingAs($user)
            ->post("/jarvis/propuestas/{$proposalId}/aprobar")
            ->assertRedirect();

        $this->actingAs($user)
            ->post(
                "/jarvis/propuestas/{$proposalId}/ejecutar",
                ['confirm_execution' => '1'],
            )
            ->assertRedirect('/jarvis?status=executed');
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-agent-execution-v5',
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
