<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\JarvisDailyPlan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JarvisDailyPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_plan_classifies_each_priority_once_in_a_deterministic_order(): void
    {
        $plan = app(
            JarvisDailyPlan::class,
        )->build(
            [
                'pressure_score' => 82,
                'organizations' => [
                    [
                        'id' => 1,
                        'name' => 'Casa Andina',
                        'pressure_score' => 90,
                    ],
                    [
                        'id' => 2,
                        'name' => 'SUNARP',
                        'pressure_score' => 70,
                    ],
                ],
                'top_priorities' => [
                    $this->priority(
                        1,
                        'Casa Andina',
                        'Incidente inmediato',
                        'task',
                        'Tarea',
                        96,
                        'critical',
                        'Tarea vencida',
                        'Resolver el estado de la tarea.',
                    ),
                    $this->priority(
                        2,
                        'SUNARP',
                        'Seguimiento proveedor',
                        'task',
                        'Tarea',
                        72,
                        'attention',
                        'Seguimiento de espera vencido',
                        'Resolver el seguimiento de espera.',
                    ),
                    $this->priority(
                        1,
                        'Casa Andina',
                        'Proyecto del día',
                        'project',
                        'Proyecto',
                        75,
                        'attention',
                        'Sin siguiente acción',
                        'Definir una siguiente acción concreta.',
                    ),
                    $this->priority(
                        2,
                        'SUNARP',
                        'Proyecto para después',
                        'project',
                        'Proyecto',
                        45,
                        'watch',
                        'Proyecto estancado',
                        'Revisar el proyecto.',
                    ),
                ],
            ],
            CarbonImmutable::parse(
                '2026-09-09 08:00:00',
                'America/Lima',
            ),
        );

        $this->assertSame(
            4,
            $plan['total_items'],
        );

        $this->assertSame(
            'Incidente inmediato',
            $plan['sections']
                ['immediate']
                ['items'][0]
                ['title'],
        );

        $this->assertSame(
            'Proyecto del día',
            $plan['sections']
                ['today']
                ['items'][0]
                ['title'],
        );

        $this->assertSame(
            'Seguimiento proveedor',
            $plan['sections']
                ['follow_up']
                ['items'][0]
                ['title'],
        );

        $this->assertSame(
            'Proyecto para después',
            $plan['sections']
                ['capacity']
                ['items'][0]
                ['title'],
        );

        $sequences = collect(
            $plan['sections'],
        )
            ->flatMap(
                fn (array $section): array =>
                    array_column(
                        $section['items'],
                        'sequence',
                    ),
            )
            ->values()
            ->all();

        $this->assertSame(
            [1, 2, 3, 4],
            $sequences,
        );

        $this->assertTrue(
            $plan['read_only'],
        );
    }

    public function test_jarvis_get_shows_daily_plan_across_scopes_without_creating_proposals(): void
    {
        $user = User::factory()->create();

        $first = $this->organization(
            $user,
            'Casa Andina Plan',
            'casa-andina-plan-v11',
        );

        $second = $this->organization(
            $user,
            'SUNARP Plan',
            'sunarp-plan-v11',
        );

        Task::query()->create([
            'organization_id' =>
                $first->id,
            'title' =>
                'Tarea crítica del plan',
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
                'Proyecto sin siguiente acción del plan',
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
                'Plan Diario Jarvis',
            )
            ->assertSee(
                'Prioridad inmediata',
            )
            ->assertSee(
                'Atender hoy',
            )
            ->assertSee(
                'Seguimientos',
            )
            ->assertSee(
                'Si queda capacidad',
            )
            ->assertSee(
                'Tarea crítica del plan',
            )
            ->assertSee(
                'no reserva horas',
                false,
            );

        $this->assertSame(
            $before,
            AgentActionProposal::query()
                ->count(),
        );
    }

    public function test_empty_daily_plan_is_safe_and_read_only(): void
    {
        $plan = app(
            JarvisDailyPlan::class,
        )->build(
            [
                'pressure_score' => 0,
                'organizations' => [],
                'top_priorities' => [],
            ],
        );

        $this->assertSame(
            0,
            $plan['total_items'],
        );

        $this->assertSame(
            [],
            $plan['focus_organizations'],
        );

        $this->assertTrue(
            $plan['read_only'],
        );
    }

    private function priority(
        int $organizationId,
        string $organization,
        string $title,
        string $type,
        string $typeLabel,
        int $rank,
        string $level,
        string $why,
        string $move,
    ): array {
        return [
            'organization_id' =>
                $organizationId,
            'organization' =>
                $organization,
            'organization_pressure' =>
                80,
            'type' => $type,
            'type_label' =>
                $typeLabel,
            'id' =>
                random_int(100, 9999),
            'title' => $title,
            'rank' => $rank,
            'level' => $level,
            'level_label' =>
                ucfirst($level),
            'why' => $why,
            'suggested_move' =>
                $move,
            'url' => '/jarvis',
        ];
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
