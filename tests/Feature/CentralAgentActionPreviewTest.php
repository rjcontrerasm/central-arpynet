<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\CentralAgentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\TestCase;

class CentralAgentActionPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_v3_contract_exposes_project_and_service_previews_without_execution(): void
    {
        $gateway = app(
            CentralAgentGateway::class,
        );

        $contract = $gateway->contract();

        $this->assertSame(
            'central-agent-contract-v3',
            $contract['contract'],
        );

        $this->assertContains(
            'project.action.preview',
            $contract['allowed_operations'],
        );

        $this->assertContains(
            'service_order.action.preview',
            $contract['allowed_operations'],
        );

        $this->assertFalse(
            $contract['write_execution'],
        );

        $this->assertFalse(
            $contract['network_calls'],
        );

        $this->assertArrayHasKey(
            'project',
            $contract['action_catalog'],
        );

        $this->assertArrayHasKey(
            'service_order',
            $contract['action_catalog'],
        );

        $reflection = new ReflectionClass(
            CentralAgentGateway::class,
        );

        foreach ([
            'executeProjectAction',
            'executeServiceOrderAction',
        ] as $method) {
            $this->assertFalse(
                $reflection->hasMethod($method),
            );
        }
    }

    public function test_project_update_preview_does_not_mutate_project(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto preview Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'planned',
            'created_by' => $user->id,
        ]);

        $before = $project->fresh()
            ->toArray();

        $preview = app(
            CentralAgentGateway::class,
        )->previewProjectAction(
            $user,
            $project,
            'project.next_action.set',
            [
                'next_action' =>
                    'Coordinar reunión de arranque',
            ],
        );

        $this->assertTrue(
            $preview['confirmation_required'],
        );

        $this->assertSame(
            'update',
            $preview['effect'],
        );

        $this->assertSame(
            'Coordinar reunión de arranque',
            $preview['proposed_changes']
                ['next_action'],
        );

        $this->assertSame(
            $before,
            $project->fresh()->toArray(),
        );
    }

    public function test_project_task_creation_can_be_previewed_without_creating_task(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto tarea preview',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $preview = app(
            CentralAgentGateway::class,
        )->previewProjectAction(
            $user,
            $project,
            'project.task.create',
            [
                'title' =>
                    'Preparar cronograma Jarvis',
                'urgency' => 'high',
                'due_date' => '2026-09-08',
            ],
        );

        $this->assertSame(
            'create',
            $preview['effect'],
        );

        $this->assertSame(
            'Preparar cronograma Jarvis',
            $preview['proposed_changes']
                ['task']['title'],
        );

        $this->assertSame(
            'high',
            $preview['proposed_changes']
                ['task']['urgency'],
        );

        $this->assertDatabaseMissing(
            'tasks',
            [
                'project_id' => $project->id,
                'title' =>
                    'Preparar cronograma Jarvis',
            ],
        );
    }

    public function test_service_stage_and_next_action_can_be_previewed_without_mutation(): void
    {
        [$user, $organization] =
            $this->context();

        $client = Client::query()->create([
            'organization_id' =>
                $organization->id,
            'name' => 'Cliente preview',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $order = ServiceOrder::query()->create([
            'organization_id' =>
                $organization->id,
            'client_id' => $client->id,
            'title' =>
                'Servicio preview Jarvis',
            'stage' => 'quotation',
            'currency' => 'PEN',
            'created_by' => $user->id,
        ]);

        $before = $order->fresh()
            ->toArray();

        $gateway = app(
            CentralAgentGateway::class,
        );

        $stage = $gateway
            ->previewServiceOrderAction(
                $user,
                $order,
                'service_order.stage.set',
                [
                    'stage' => 'execution',
                ],
            );

        $next = $gateway
            ->previewServiceOrderAction(
                $user,
                $order,
                'service_order.next_action.set',
                [
                    'next_action' =>
                        'Entregar informe técnico',
                    'next_action_at' =>
                        '2026-09-09 10:00:00',
                ],
            );

        $this->assertSame(
            'execution',
            $stage['proposed_changes']
                ['stage'],
        );

        $this->assertSame(
            'Entregar informe técnico',
            $next['proposed_changes']
                ['next_action'],
        );

        $this->assertTrue(
            $stage['confirmation_required'],
        );

        $this->assertTrue(
            $next['confirmation_required'],
        );

        $this->assertSame(
            $before,
            $order->fresh()->toArray(),
        );
    }

    public function test_invalid_agent_proposal_is_rejected_before_any_write(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' => 'Proyecto inválido',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->expectException(
            ValidationException::class,
        );

        app(CentralAgentGateway::class)
            ->previewProjectAction(
                $user,
                $project,
                'project.status.set',
                [
                    'status' =>
                        'invalid_status',
                ],
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
                    'arpynet-agent-preview-v3',
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

        return [$user, $organization];
    }
}
