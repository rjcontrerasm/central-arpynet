<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\JarvisOperationalIntelligence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JarvisOperationalIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_prioritizes_signals_and_suggests_only_compatible_proposal_types(): void
    {
        $analysis = app(
            JarvisOperationalIntelligence::class,
        )->analyze([
            'counts' => [
                'incidents_open' => 1,
            ],
            'attention' => [
                [
                    'type' => 'project',
                    'type_label' => 'Proyecto',
                    'id' => 10,
                    'title' => 'Proyecto crítico',
                    'level' => 'critical',
                    'level_label' => 'Crítico',
                    'rank' => 95,
                    'reasons' => [
                        'Fecha objetivo vencida',
                        'Sin siguiente acción',
                    ],
                    'no_next_action' => true,
                    'stagnant' => true,
                    'url' => '/proyectos?scope=1',
                ],
                [
                    'type' => 'task',
                    'type_label' => 'Tarea',
                    'id' => 20,
                    'title' => 'Tarea vencida',
                    'level' => 'critical',
                    'level_label' => 'Crítico',
                    'rank' => 90,
                    'reasons' => [
                        'Tarea vencida',
                    ],
                    'no_next_action' => false,
                    'stagnant' => false,
                    'url' => '/mi-dia?scope=1',
                ],
            ],
        ]);

        $this->assertGreaterThanOrEqual(
            50,
            $analysis['pressure_score'],
        );

        $this->assertSame(
            'project.next_action.set',
            $analysis['priorities'][0]
                ['proposal_action'],
        );

        $this->assertNull(
            $analysis['priorities'][1]
                ['proposal_action'],
        );

        $this->assertTrue(
            $analysis['read_only'],
        );

        $this->assertTrue(
            $analysis[
                'generated_without_network'
            ],
        );
    }

    public function test_opening_jarvis_intelligence_is_read_only_and_does_not_create_proposals(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto lectura Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'target_date' =>
                now()->subDay()
                    ->toDateString(),
            'next_action' => null,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'project_id' =>
                $project->id,
            'title' =>
                'Tarea crítica para Jarvis',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $before =
            AgentActionProposal::query()
                ->count();

        $this->actingAs($user)
            ->get(
                '/jarvis?scope='
                .$organization->id,
            )
            ->assertOk()
            ->assertSee(
                'Lectura Jarvis',
            )
            ->assertSee(
                'Presión',
            )
            ->assertSee(
                'solo lectura',
                false,
            )
            ->assertSee(
                'Definir una siguiente acción concreta',
            )
            ->assertSee(
                'Jarvis podría preparar: Definir siguiente acción del proyecto',
            )
            ->assertSee(
                'esta lectura no crea propuestas',
                false,
            );

        $this->assertSame(
            $before,
            AgentActionProposal::query()
                ->count(),
        );
    }

    public function test_empty_context_is_controlled_and_safe(): void
    {
        $analysis = app(
            JarvisOperationalIntelligence::class,
        )->analyze(null);

        $this->assertSame(
            0,
            $analysis['pressure_score'],
        );

        $this->assertSame(
            'controlled',
            $analysis['pressure_level'],
        );

        $this->assertSame(
            [],
            $analysis['priorities'],
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
                    'ARPYNET Jarvis Intelligence',
                'slug' =>
                    'arpynet-jarvis-intelligence-v7',
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
