<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\AutomationRuleCatalog;
use App\Support\AutomationRuleEngine;
use App\Support\AutomationRuleExecutor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_catalog_supports_safe_project_triggers(): void
    {
        $catalog = app(AutomationRuleCatalog::class);

        foreach ([
            'project.stagnant',
            'project.target_due_soon',
            'project.target_overdue',
            'project.no_next_action',
            'project.blocked',
        ] as $trigger) {
            $catalog->validate(
                $trigger,
                'project.create_alert',
                'automatic',
            );
        }

        $this->assertTrue(
            $catalog->isAutomaticInternal(
                'project.create_alert',
            ),
        );
    }

    public function test_project_triggers_match_expected_projects(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-06 10:00:00',
        );

        [$user, $organization] = $this->context();

        $stagnant = $this->project(
            $organization,
            $user,
            'Proyecto estancado automation',
            [
                'next_action' => 'Revisar avance',
                'last_activity_at' => now()->subDays(20),
            ],
        );

        $dueSoon = $this->project(
            $organization,
            $user,
            'Proyecto próximo automation',
            [
                'next_action' => 'Cerrar entregable',
                'target_date' => '2026-09-09',
            ],
        );

        $overdue = $this->project(
            $organization,
            $user,
            'Proyecto vencido automation',
            [
                'next_action' => 'Reprogramar',
                'target_date' => '2026-09-05',
            ],
        );

        $withoutAction = $this->project(
            $organization,
            $user,
            'Proyecto sin acción automation',
        );

        $blocked = $this->project(
            $organization,
            $user,
            'Proyecto bloqueado automation',
            [
                'next_action' => 'Escalar',
                'blockers' => 'Esperando proveedor',
            ],
        );

        $engine = app(AutomationRuleEngine::class);

        $cases = [
            [
                'project.stagnant',
                [],
                $stagnant->id,
            ],
            [
                'project.target_due_soon',
                ['days' => 7],
                $dueSoon->id,
            ],
            [
                'project.target_overdue',
                [],
                $overdue->id,
            ],
            [
                'project.no_next_action',
                [],
                $withoutAction->id,
            ],
            [
                'project.blocked',
                [],
                $blocked->id,
            ],
        ];

        foreach ($cases as [$trigger, $config, $expectedId]) {
            $rule = $this->rule(
                $organization,
                $user,
                $trigger,
                $config,
            );

            $ids = $engine
                ->preview(
                    $rule,
                    CarbonImmutable::now(),
                )
                ->pluck('subject_id');

            $this->assertTrue(
                $ids->contains($expectedId),
                'No coincidió '.$trigger,
            );
        }
    }

    public function test_automatic_project_alert_is_internal_and_deduplicated(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-06 10:00:00',
        );

        [$user, $organization] = $this->context();

        $project = $this->project(
            $organization,
            $user,
            'Proyecto bloqueo seguro',
            [
                'next_action' => 'Resolver dependencia',
                'blockers' => 'Dependencia externa',
            ],
        );

        $before = $project->fresh()->getAttributes();

        $rule = $this->rule(
            $organization,
            $user,
            'project.blocked',
            [],
            'automatic',
        );

        $executor = app(AutomationRuleExecutor::class);

        $first = $executor->runRule(
            $rule,
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(1, $first['executed']);
        $this->assertSame(
            1,
            $user->notifications()->count(),
        );
        $this->assertSame(
            $before,
            $project->fresh()->getAttributes(),
        );

        $this->assertDatabaseHas(
            'automation_rule_runs',
            [
                'automation_rule_id' => $rule->id,
                'subject_type' => 'project',
                'subject_id' => $project->id,
                'outcome' => 'executed',
            ],
        );

        $second = $executor->runRule(
            $rule->fresh(),
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(1, $second['duplicates']);
        $this->assertSame(
            1,
            $user->notifications()->count(),
        );
        $this->assertSame(
            1,
            AutomationRuleRun::query()
                ->where(
                    'automation_rule_id',
                    $rule->id,
                )
                ->count(),
        );
    }

    private function rule(
        Organization $organization,
        User $user,
        string $trigger,
        array $triggerConfig = [],
        string $mode = 'preview',
    ): AutomationRule {
        return AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Regla '.$trigger,
            'trigger_key' => $trigger,
            'action_key' => 'project.create_alert',
            'trigger_config' => $triggerConfig,
            'action_config' => [],
            'mode' => $mode,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
    }

    private function project(
        Organization $organization,
        User $user,
        string $name,
        array $extra = [],
    ): Project {
        return Project::query()->create(
            array_merge(
                [
                    'organization_id' =>
                        $organization->id,
                    'name' => $name,
                    'type' => 'project',
                    'horizon' => 'short',
                    'status' => 'active',
                    'currency' => 'PEN',
                    'created_by' => $user->id,
                    'is_private' => false,
                ],
                $extra,
            ),
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-project-automation',
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
