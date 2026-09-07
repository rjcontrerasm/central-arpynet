<?php

namespace Tests\Feature;

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

class CentralAgentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_v4_is_read_preview_only_and_has_no_execute_methods(): void
    {
        $gateway = app(
            CentralAgentGateway::class,
        );

        $contract = $gateway->contract();

        $this->assertSame(
            'central-agent-contract-v4',
            $contract['contract'],
        );

        $this->assertFalse(
            $contract['public_api'],
        );

        $this->assertFalse(
            $contract['network_calls'],
        );

        $this->assertFalse(
            $contract['write_execution'],
        );

        $this->assertSame(
            [
                'task.read',
                'task.action.preview',
                'task.action.propose',
                'project.read',
                'project.action.preview',
                'project.action.propose',
                'service_order.read',
                'service_order.action.preview',
                'service_order.action.propose',
                'organization.operational_context.read',
            ],
            $contract['allowed_operations'],
        );

        $reflection = new ReflectionClass(
            CentralAgentGateway::class,
        );

        foreach ([
            'executeTaskAction',
            'executeProjectAction',
            'executeServiceOrderAction',
            'executeOrganizationAction',
        ] as $method) {
            $this->assertFalse(
                $reflection->hasMethod($method),
            );
        }
    }

    public function test_task_context_and_preview_remain_read_only(): void
    {
        [$user, $organization] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' => 'Tarea para Jarvis',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'high',
            'created_by' => $user->id,
        ]);

        $gateway = app(
            CentralAgentGateway::class,
        );

        $before = $task->fresh()
            ->toArray();

        $context = $gateway->taskContext(
            $user,
            $task,
        );

        $preview = $gateway->previewTaskAction(
            $user,
            $task,
            'tomorrow',
        );

        $this->assertSame(
            'Tarea para Jarvis',
            $context['title'],
        );

        $this->assertTrue(
            $preview['confirmation_required'],
        );

        $this->assertSame(
            $before,
            $task->fresh()->toArray(),
        );
    }

    public function test_project_and_service_context_are_read_only(): void
    {
        [$user, $organization] =
            $this->context();

        $client = Client::query()->create([
            'organization_id' =>
                $organization->id,
            'name' => 'Cliente Jarvis',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' => 'Proyecto Jarvis 360',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'target_date' => now()
                ->addDays(3)
                ->toDateString(),
            'next_action' =>
                'Validar entregable',
            'created_by' => $user->id,
        ]);

        $order = ServiceOrder::query()->create([
            'organization_id' =>
                $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio Jarvis 360',
            'stage' => 'execution',
            'currency' => 'PEN',
            'amount' => 1500,
            'next_action' =>
                'Entregar informe',
            'created_by' => $user->id,
        ]);

        $gateway = app(
            CentralAgentGateway::class,
        );

        $projectBefore =
            $project->fresh()->toArray();
        $orderBefore =
            $order->fresh()->toArray();

        $projectContext =
            $gateway->projectContext(
                $user,
                $project,
            );

        $orderContext =
            $gateway->serviceOrderContext(
                $user,
                $order,
            );

        $this->assertSame(
            'Proyecto Jarvis 360',
            $projectContext['name'],
        );

        $this->assertSame(
            'Validar entregable',
            $projectContext['next_action'],
        );

        $this->assertSame(
            'Servicio Jarvis 360',
            $orderContext['title'],
        );

        $this->assertSame(
            'Cliente Jarvis',
            $orderContext['client'],
        );

        $this->assertSame(
            $projectBefore,
            $project->fresh()->toArray(),
        );

        $this->assertSame(
            $orderBefore,
            $order->fresh()->toArray(),
        );
    }

    public function test_organization_context_exposes_360_counts_and_attention_without_writes(): void
    {
        [$user, $organization] =
            $this->context();

        Client::query()->create([
            'organization_id' =>
                $organization->id,
            'name' => 'Cliente contexto',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto sin acción Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea vencida Jarvis',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $gateway = app(
            CentralAgentGateway::class,
        );

        $taskCountBefore =
            Task::query()->count();
        $projectCountBefore =
            Project::query()->count();

        $context =
            $gateway->organizationContext(
                $user,
                $organization,
            );

        $this->assertSame(
            'organization',
            $context['type'],
        );

        $this->assertSame(
            1,
            $context['counts']['clients'],
        );

        $this->assertSame(
            1,
            $context['counts']['projects_open'],
        );

        $this->assertSame(
            1,
            $context['counts']['tasks_open'],
        );

        $this->assertNotEmpty(
            $context['attention'],
        );

        $this->assertSame(
            $taskCountBefore,
            Task::query()->count(),
        );

        $this->assertSame(
            $projectCountBefore,
            Project::query()->count(),
        );
    }

    public function test_foreign_entities_are_not_visible_to_gateway(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()
            ->create([
                'name' => 'Ajena agente',
                'slug' => 'ajena-agente-v2',
                'category' => 'company',
                'timezone' => 'America/Lima',
                'is_active' => true,
                'created_by' => $user->id,
            ]);

        $project = Project::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'No visible',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->expectException(
            AuthorizationException::class,
        );

        app(CentralAgentGateway::class)
            ->projectContext(
                $user,
                $project,
            );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' =>
                    'arpynet-agent-v2',
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
            'current_organization_id' =>
                $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
