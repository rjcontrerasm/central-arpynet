<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Support\CentralAgentGateway;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class CentralAgentProposalQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_v4_contract_allows_proposal_persistence_but_not_execution(): void
    {
        $gateway = app(
            CentralAgentGateway::class,
        );

        $contract = $gateway->contract();

        $this->assertSame(
            'central-agent-contract-v4',
            $contract['contract'],
        );

        $this->assertTrue(
            $contract['proposal_persistence'],
        );

        $this->assertFalse(
            $contract['write_execution'],
        );

        $this->assertFalse(
            $contract['network_calls'],
        );

        $this->assertContains(
            'project.action.propose',
            $contract['allowed_operations'],
        );

        $this->assertContains(
            'service_order.action.propose',
            $contract['allowed_operations'],
        );

        $this->assertContains(
            'proposal.execute',
            $contract['blocked_operations'],
        );

        $reflection = new ReflectionClass(
            CentralAgentGateway::class,
        );

        foreach ([
            'executeProposal',
            'executeProjectAction',
            'executeServiceOrderAction',
        ] as $method) {
            $this->assertFalse(
                $reflection->hasMethod($method),
            );
        }
    }

    public function test_project_proposal_is_queued_without_mutating_project_and_is_deduplicated(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto cola Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' =>
                'Acción original',
            'created_by' => $user->id,
        ]);

        $before = $project->fresh()
            ->toArray();

        $gateway = app(
            CentralAgentGateway::class,
        );

        $first =
            $gateway->proposeProjectAction(
                $user,
                $project,
                'project.next_action.set',
                [
                    'next_action' =>
                        'Nueva acción propuesta',
                ],
                'El proyecto requiere un siguiente paso más concreto.',
            );

        $second =
            $gateway->proposeProjectAction(
                $user,
                $project,
                'project.next_action.set',
                [
                    'next_action' =>
                        'Nueva acción propuesta',
                ],
                'Duplicada',
            );

        $this->assertSame(
            $first->id,
            $second->id,
        );

        $this->assertSame(
            'pending',
            $first->status,
        );

        $this->assertSame(
            'Nueva acción propuesta',
            $first->proposed_changes[
                'next_action'
            ],
        );

        $this->assertSame(
            $before,
            $project->fresh()->toArray(),
        );

        $this->assertDatabaseCount(
            'agent_action_proposals',
            1,
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'event' =>
                    'agent_proposal.created',
                'subject_id' =>
                    $first->id,
            ],
        );
    }

    public function test_human_approval_does_not_execute_project_change(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto aprobar Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' =>
                'Antes',
            'created_by' => $user->id,
        ]);

        $proposal = app(
            CentralAgentGateway::class,
        )->proposeProjectAction(
            $user,
            $project,
            'project.next_action.set',
            [
                'next_action' =>
                    'Después propuesto',
            ],
        );

        $this->actingAs($user)
            ->post(
                '/jarvis/propuestas/'
                .$proposal->id
                .'/aprobar',
            )
            ->assertRedirect();

        $this->assertSame(
            'approved',
            $proposal->fresh()->status,
        );

        $this->assertSame(
            'Antes',
            $project->fresh()
                ->next_action,
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'event' =>
                    'agent_proposal.approved',
                'subject_id' =>
                    $proposal->id,
            ],
        );
    }

    public function test_human_rejection_does_not_execute_service_change(): void
    {
        [$user, $organization] =
            $this->context();

        $client = Client::query()->create([
            'organization_id' =>
                $organization->id,
            'name' => 'Cliente Jarvis cola',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $service =
            ServiceOrder::query()->create([
                'organization_id' =>
                    $organization->id,
                'client_id' =>
                    $client->id,
                'title' =>
                    'Servicio cola Jarvis',
                'stage' => 'quotation',
                'currency' => 'PEN',
                'created_by' => $user->id,
            ]);

        $proposal = app(
            CentralAgentGateway::class,
        )->proposeServiceOrderAction(
            $user,
            $service,
            'service_order.stage.set',
            [
                'stage' => 'execution',
            ],
        );

        $this->actingAs($user)
            ->post(
                '/jarvis/propuestas/'
                .$proposal->id
                .'/rechazar',
            )
            ->assertRedirect();

        $this->assertSame(
            'rejected',
            $proposal->fresh()->status,
        );

        $this->assertSame(
            'quotation',
            $service->fresh()->stage,
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'event' =>
                    'agent_proposal.rejected',
                'subject_id' =>
                    $proposal->id,
            ],
        );
    }

    public function test_task_proposal_can_be_queued_without_task_write(): void
    {
        [$user, $organization] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea propuesta Jarvis',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'created_by' => $user->id,
        ]);

        $before = $task->fresh()
            ->toArray();

        $proposal = app(
            CentralAgentGateway::class,
        )->proposeTaskAction(
            $user,
            $task,
            'tomorrow',
            'Moverla para mañana.',
        );

        $this->assertSame(
            'task',
            $proposal->subject_type,
        );

        $this->assertSame(
            $before,
            $task->fresh()->toArray(),
        );
    }

    public function test_foreign_proposal_cannot_be_created_or_reviewed(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()
            ->create([
                'name' => 'Ajena Jarvis cola',
                'slug' =>
                    'ajena-jarvis-cola',
                'category' => 'company',
                'timezone' =>
                    'America/Lima',
                'is_active' => true,
                'created_by' => $user->id,
            ]);

        $project = Project::query()->create([
            'organization_id' =>
                $foreign->id,
            'name' => 'Proyecto ajeno',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->expectException(
            AuthorizationException::class,
        );

        app(CentralAgentGateway::class)
            ->proposeProjectAction(
                $user,
                $project,
                'project.next_action.set',
                [
                    'next_action' =>
                        'No permitido',
                ],
            );
    }

    public function test_jarvis_queue_ui_is_scoped_and_visible(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto visible Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        app(CentralAgentGateway::class)
            ->proposeProjectAction(
                $user,
                $project,
                'project.next_action.set',
                [
                    'next_action' =>
                        'Validar alcance',
                ],
                'Falta concretar el siguiente paso.',
            );

        $this->actingAs($user)
            ->get('/jarvis')
            ->assertOk()
            ->assertSee('Jarvis')
            ->assertSee(
                'Proyecto visible Jarvis',
            )
            ->assertSee(
                'Validar alcance',
            )
            ->assertSee(
                'Aprobar',
            )
            ->assertSee(
                'Rechazar',
            )
            ->assertSee(
                'aprobar una propuesta no modifica',
                false,
            )
            ->assertSee(
                'La ejecución seguirá bloqueada',
                false,
            );
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
                'slug' =>
                    'arpynet-agent-queue-v4',
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

        return [
            $user,
            $organization,
        ];
    }
}
