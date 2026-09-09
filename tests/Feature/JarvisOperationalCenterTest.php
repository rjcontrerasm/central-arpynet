<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\CentralAgentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JarvisOperationalCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_jarvis_center_shows_safety_summary_and_operational_context(): void
    {
        [$user, $organization] =
            $this->context();

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea abierta Jarvis Center',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto Jarvis Center',
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
                        'Definir siguiente paso',
                ],
                'Proyecto sin siguiente acción concreta.',
            );

        $this->actingAs($user)
            ->get(
                '/jarvis?scope='
                .$organization->id,
            )
            ->assertOk()
            ->assertSee(
                'Centro de control para propuestas y contexto operativo.',
            )
            ->assertSee(
                'Confirmación humana',
            )
            ->assertSee(
                'Autonomía: deshabilitada',
            )
            ->assertSee(
                'Red externa: deshabilitada',
            )
            ->assertSee(
                'central-agent-contract-v5',
            )
            ->assertSee(
                'Contexto operativo',
            )
            ->assertSee(
                'Tarea abierta Jarvis Center',
            )
            ->assertSee(
                'REQUIEREN DECISIÓN',
            )
            ->assertSee(
                'Accesos rápidos',
            );
    }

    public function test_jarvis_center_uses_current_organization_when_no_scope_is_selected(): void
    {
        [$user, $organization] =
            $this->context();

        $this->actingAs($user)
            ->get('/jarvis')
            ->assertOk()
            ->assertSee(
                $organization->name,
            )
            ->assertSee(
                'Abrir vista 360',
            );
    }

    public function test_jarvis_center_counts_approved_and_executed_proposals(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto métricas Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' => 'Antes',
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
                    'Después',
            ],
        );

        $this->actingAs($user)
            ->post(
                '/jarvis/propuestas/'
                .$proposal->id
                .'/aprobar',
            )
            ->assertRedirect();

        $this->actingAs($user)
            ->get(
                '/jarvis?status=approved&scope='
                .$organization->id,
            )
            ->assertOk()
            ->assertSee(
                'LISTAS PARA EJECUTAR',
            )
            ->assertSee(
                'Ejecutar cambio',
            );

        $this->actingAs($user)
            ->post(
                '/jarvis/propuestas/'
                .$proposal->id
                .'/ejecutar',
                [
                    'confirm_execution' => 1,
                ],
            )
            ->assertRedirect();

        $this->actingAs($user)
            ->get(
                '/jarvis?status=executed&scope='
                .$organization->id,
            )
            ->assertOk()
            ->assertSee(
                'EJECUTADAS',
            )
            ->assertSee(
                'EJECUTADAS · 7 DÍAS',
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
                'name' =>
                    'ARPYNET Jarvis Center',
                'slug' =>
                    'arpynet-jarvis-center-v6',
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
