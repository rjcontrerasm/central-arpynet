<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\CentralAgentGateway;
use App\Support\JarvisExecutivePrioritization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JarvisExecutivePrioritizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_prioritization_ranks_organizations_and_global_priorities(): void
    {
        $user = User::factory()->create();

        $criticalOrg =
            $this->organization(
                $user,
                'Casa crítica v10',
                'casa-critica-v10',
            );

        $watchOrg =
            $this->organization(
                $user,
                'Ámbito controlado v10',
                'ambito-controlado-v10',
            );

        Task::query()->create([
            'organization_id' =>
                $criticalOrg->id,
            'title' =>
                'Tarea vencida transversal',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->subDays(2),
            'created_by' => $user->id,
        ]);

        Project::query()->create([
            'organization_id' =>
                $watchOrg->id,
            'name' =>
                'Proyecto sin siguiente acción v10',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $gateway =
            app(CentralAgentGateway::class);

        $analysis = app(
            JarvisExecutivePrioritization::class,
        )->analyze([
            $gateway->organizationContext(
                $user,
                $criticalOrg,
            ),
            $gateway->organizationContext(
                $user,
                $watchOrg,
            ),
        ]);

        $this->assertSame(
            2,
            $analysis['organization_count'],
        );

        $this->assertSame(
            'Casa crítica v10',
            $analysis['organizations'][0]
                ['name'],
        );

        $this->assertSame(
            'Tarea vencida transversal',
            $analysis['top_priorities'][0]
                ['title'],
        );

        $this->assertGreaterThan(
            0,
            $analysis['pressure_score'],
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

    public function test_jarvis_get_shows_global_priority_even_when_one_scope_is_selected_without_writes(): void
    {
        $user = User::factory()->create();

        $first =
            $this->organization(
                $user,
                'Ámbito Uno v10',
                'ambito-uno-v10',
            );

        $second =
            $this->organization(
                $user,
                'Ámbito Dos v10',
                'ambito-dos-v10',
            );

        Task::query()->create([
            'organization_id' =>
                $first->id,
            'title' =>
                'Prioridad global Uno',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        Project::query()->create([
            'organization_id' =>
                $second->id,
            'name' =>
                'Proyecto global Dos',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $user->forceFill([
            'current_organization_id' =>
                $second->id,
        ])->save();

        $before =
            AgentActionProposal::query()
                ->count();

        $this->actingAs($user)
            ->get(
                '/jarvis?scope='
                .$second->id,
            )
            ->assertOk()
            ->assertSee(
                'Prioridad ejecutiva global',
            )
            ->assertSee(
                'Ámbito Uno v10',
            )
            ->assertSee(
                'Ámbito Dos v10',
            )
            ->assertSee(
                'Prioridad global Uno',
            )
            ->assertSee(
                'Top transversal',
            )
            ->assertSee(
                'solo compara señales ya autorizadas',
                false,
            );

        $this->assertSame(
            $before,
            AgentActionProposal::query()
                ->count(),
        );
    }

    public function test_executive_prioritization_handles_empty_contexts_safely(): void
    {
        $analysis = app(
            JarvisExecutivePrioritization::class,
        )->analyze([]);

        $this->assertSame(
            0,
            $analysis['pressure_score'],
        );

        $this->assertSame(
            0,
            $analysis['organization_count'],
        );

        $this->assertSame(
            [],
            $analysis['top_priorities'],
        );
    }

    private function organization(
        User $user,
        string $name,
        string $slug,
    ): Organization {
        $organization =
            Organization::query()->create([
                'name' => $name,
                'slug' => $slug,
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
                'is_default' => false,
                'is_active' => true,
            ],
        );

        return $organization;
    }
}
