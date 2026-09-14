#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(
            f"{path}: esperaba 1 coincidencia y encontré {count}"
        )
    file.write_text(text.replace(old, new, 1))


# Catalog: explicit L2 trigger/action and bounded autonomy contract.
replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """        'project.create_alert',
        'decision.prepare_task_start_proposal',
    ];""",
    """        'project.create_alert',
        'decision.prepare_task_start_proposal',
        'decision.execute_task_start',
    ];""",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'decision.level1_task' => [
                'label' =>
                    'Autonomía L1: decisión crítica de tarea',
                'subject' => 'task',
                'actions' => [
                    'decision.prepare_task_start_proposal',
                ],
            ],
        ];""",
    """            'decision.level1_task' => [
                'label' =>
                    'Autonomía L1: decisión crítica de tarea',
                'subject' => 'task',
                'actions' => [
                    'decision.prepare_task_start_proposal',
                ],
            ],

            'decision.level2_task_start' => [
                'label' =>
                    'Autonomía L2: iniciar tarea crítica pendiente',
                'subject' => 'task',
                'actions' => [
                    'decision.execute_task_start',
                ],
            ],
        ];""",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'decision.prepare_task_start_proposal' =>
                $this->buildAction(
                    'Autonomía L1: preparar propuesta “En curso”',
                    [
                        'preview',
                        'automatic',
                    ],
                    true,
                    false,
                    'pending_agent_proposal',
                ),
        ];""",
    """            'decision.prepare_task_start_proposal' =>
                $this->buildAction(
                    'Autonomía L1: preparar propuesta “En curso”',
                    [
                        'preview',
                        'automatic',
                    ],
                    true,
                    false,
                    'pending_agent_proposal',
                ),

            'decision.execute_task_start' =>
                $this->buildAction(
                    'Autonomía L2: iniciar tarea crítica',
                    [
                        'preview',
                        'automatic',
                    ],
                    true,
                    true,
                    'bounded_reversible_task_start',
                ),
        ];""",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'automatic_execution_scope' =>
                'internal_notifications_and_pending_proposals',
            'automatic_pending_proposals_enabled' =>
                true,
            'autonomy_level_one_enabled' =>
                true,
            'autonomous_subject_mutations_enabled' =>
                false,
            'subject_mutations_enabled' =>
                false,""",
    """            'automatic_execution_scope' =>
                'internal_notifications_pending_proposals_and_bounded_task_start',
            'automatic_pending_proposals_enabled' =>
                true,
            'autonomy_level_one_enabled' =>
                true,
            'autonomy_level_two_enabled' =>
                true,
            'autonomous_subject_mutations_enabled' =>
                true,
            'autonomous_subject_mutation_scope' => [
                'task.start',
            ],
            'autonomous_subject_mutation_daily_limit' =>
                AutonomyLevelTwoPolicy::DAILY_EXECUTION_LIMIT,
            'subject_mutations_enabled' =>
                false,""",
)

# Engine: L2 candidates are stricter than L1 and only pending tasks qualify.
replace_once(
    "app/Support/AutomationRuleEngine.php",
    """            'decision.level1_task' => $this->levelOneTasks($rule, $now),
        };""",
    """            'decision.level1_task' => $this->levelOneTasks($rule, $now),
            'decision.level2_task_start' => $this->levelTwoTasks($rule, $now),
        };""",
)

replace_once(
    "app/Support/AutomationRuleEngine.php",
    """    private function candidate(
        AutomationRule $rule,""",
    """    private function levelTwoTasks(
        AutomationRule $rule,
        CarbonImmutable $now,
    ): Collection {
        $decisionEngine = app(
            DecisionEngine::class,
        );
        $policy = app(
            AutonomyLevelTwoPolicy::class,
        );

        return Task::query()
            ->with('organization')
            ->where(
                'organization_id',
                $rule->organization_id,
            )
            ->where('status', 'pending')
            ->limit(250)
            ->get()
            ->map(function (Task $task) use (
                $rule,
                $now,
                $decisionEngine,
                $policy,
            ): ?array {
                $item = GlobalTrackingItemFactory::task(
                    $task,
                    $now,
                );

                $decision = collect(
                    $decisionEngine->evaluate(
                        [$item],
                    )['decisions'] ?? [],
                )->first();

                if (! is_array($decision)) {
                    return null;
                }

                $autonomy = $policy->evaluate(
                    $decision,
                    (string) $task->status,
                );

                if (! $autonomy['eligible']) {
                    return null;
                }

                return $this->candidate(
                    $rule,
                    'task',
                    $task->id,
                    $task->title,
                    'Autonomía L2 · '
                        .($decision['why_now']
                            ?? 'decisión crítica vigente'),
                    $task->updated_at,
                ) + [
                    'decision_score' =>
                        $decision['decision_score'],
                    'decision_band' =>
                        $decision['decision_band'],
                    'evidence_quality' =>
                        $decision['evidence_quality'],
                    'autonomy_policy_version' =>
                        $autonomy['policy_version'],
                    'autonomous_mutation' =>
                        'task.start',
                ];
            })
            ->filter()
            ->take(
                AutonomyLevelTwoPolicy::DAILY_EXECUTION_LIMIT,
            )
            ->values();
    }

    private function candidate(
        AutomationRule $rule,""",
)

# Executor: route only the explicit L2 action to the L2 service.
replace_once(
    "app/Support/AutomationRuleExecutor.php",
    """        private readonly AutomationRuleEngine $engine,
        private readonly AutonomyLevelOneService $autonomyLevelOne,
    ) {""",
    """        private readonly AutomationRuleEngine $engine,
        private readonly AutonomyLevelOneService $autonomyLevelOne,
        private readonly AutonomyLevelTwoService $autonomyLevelTwo,
    ) {""",
)

replace_once(
    "app/Support/AutomationRuleExecutor.php",
    """        if (
            $rule->action_key
            === 'decision.prepare_task_start_proposal'
        ) {
            return $this->executeAutonomyLevelOne(
                $rule,
                $candidate,
            );
        }

        $recipient =""",
    """        if (
            $rule->action_key
            === 'decision.prepare_task_start_proposal'
        ) {
            return $this->executeAutonomyLevelOne(
                $rule,
                $candidate,
            );
        }

        if (
            $rule->action_key
            === 'decision.execute_task_start'
        ) {
            return $this->executeAutonomyLevelTwo(
                $rule,
                $candidate,
            );
        }

        $recipient =""",
)

replace_once(
    "app/Support/AutomationRuleExecutor.php",
    """    private function resolveRecipient(
        AutomationRule $rule,
    ): ?User {""",
    """    private function executeAutonomyLevelTwo(
        AutomationRule $rule,
        array $candidate,
    ): string {
        if (
            ($candidate['subject_type'] ?? null)
                !== 'task'
        ) {
            return 'blocked';
        }

        $actorId = (int) (
            $rule->created_by ?? 0
        );

        if ($actorId < 1) {
            return 'blocked';
        }

        $actor = User::query()->find(
            $actorId,
        );

        if (
            ! $actor
            || ! $actor->is_active
            || ! $actor->canWriteToOrganization(
                (int) $rule->organization_id,
            )
        ) {
            return 'blocked';
        }

        $task = Task::query()->find(
            (int) (
                $candidate['subject_id']
                ?? 0
            ),
        );

        if (
            ! $task
            || (int) $task->organization_id
                !== (int) $rule->organization_id
        ) {
            return 'blocked';
        }

        try {
            $result = $this->autonomyLevelTwo->execute(
                $actor,
                $task,
                $rule,
            );
        } catch (
            \\Illuminate\\Auth\\Access\\AuthorizationException
            | \\Illuminate\\Validation\\ValidationException
            | \\Illuminate\\Database\\Eloquent\\ModelNotFoundException
        ) {
            return 'blocked';
        }

        return ($result['executed'] ?? false)
            ? 'executed'
            : 'blocked';
    }

    private function resolveRecipient(
        AutomationRule $rule,
    ): ?User {""",
)

# UI: explain the L1/L2 boundary explicitly.
replace_once(
    "resources/views/automation-center.blade.php",
    """            <p>Reglas internas, controladas y auditables. El scheduler evalúa reglas activas; los canales externos no forman parte de la ejecución automática.</p>""",
    """            <p>Reglas internas, controladas y auditables. L1 prepara propuestas; L2 solo puede iniciar tareas críticas pendientes bajo límites y undo. Los canales externos permanecen fuera de la autonomía.</p>""",
)

replace_once(
    "resources/views/automation-center.blade.php",
    """                Las reglas nuevas nacen inactivas. Autonomía L1 puede preparar una propuesta pendiente para una tarea crítica inequívoca, pero nunca la aprueba ni ejecuta. Las mutaciones de entidades y la creación de tareas entre módulos mantienen confirmación humana.""",
    """                Las reglas nuevas nacen inactivas. Autonomía L1 solo prepara propuestas. Autonomía L2 requiere opt-in explícito y puede ejecutar únicamente pending → in_progress en tareas críticas con evidencia alta, score ≥92, máximo 3 veces por organización y día y siempre con undo. Completar, reprogramar, crear entidades, proyectos, servicios, obligaciones y canales externos siguen fuera de L2.""",
)

# Scheduler safety contract now recognizes the single bounded L2 mutation.
replace_once(
    "tests/Feature/AutomationSchedulerSafetyTest.php",
    """        $this->assertFalse(
            $contract['autonomous_subject_mutations_enabled'],
        );""",
    """        $this->assertTrue(
            $contract['autonomous_subject_mutations_enabled'],
        );

        $this->assertTrue(
            $contract['autonomy_level_two_enabled'],
        );

        $this->assertSame(
            ['task.start'],
            $contract['autonomous_subject_mutation_scope'],
        );

        $this->assertSame(
            3,
            $contract['autonomous_subject_mutation_daily_limit'],
        );""",
)

replace_once(
    "tests/Feature/AutomationSchedulerSafetyTest.php",
    """        $this->assertSame(
            'internal_notifications_and_pending_proposals',
            $contract['automatic_execution_scope'],
        );""",
    """        $this->assertSame(
            'internal_notifications_pending_proposals_and_bounded_task_start',
            $contract['automatic_execution_scope'],
        );

        $this->assertFalse(
            $contract['automatic_cross_module_task_creation_enabled'],
        );

        $this->assertFalse(
            $contract['external_channels'],
        );""",
)

# Integration test is created only after runtime wiring exists.
integration_test = r'''<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\AutomationRuleExecutor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutonomyLevelTwoAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_automatic_level_two_rule_executes_bounded_reversible_task_start(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$user, $organization] = $this->context();
        $task = $this->criticalTask($user, $organization);
        $rule = $this->rule($user, $organization, 'automatic', true);

        $result = app(AutomationRuleExecutor::class)->runRule(
            $rule,
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(1, $result['matches']);
        $this->assertSame(1, $result['executed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame('in_progress', $task->fresh()->status);

        $proposal = AgentActionProposal::query()->sole();
        $this->assertSame('executed', $proposal->status);
        $this->assertSame('start', $proposal->action_key);
        $this->assertNotNull($proposal->undo_action_id);

        $this->assertDatabaseHas('automation_rule_runs', [
            'automation_rule_id' => $rule->id,
            'subject_type' => 'task',
            'subject_id' => $task->id,
            'outcome' => 'executed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'event' => 'autonomy.level2.executed',
            'subject_type' => 'task',
            'subject_id' => $task->id,
        ]);
    }

    public function test_preview_level_two_rule_never_mutates_task(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$user, $organization] = $this->context();
        $task = $this->criticalTask($user, $organization);
        $before = $task->fresh()->getAttributes();
        $rule = $this->rule($user, $organization, 'preview', true);

        $result = app(AutomationRuleExecutor::class)->runRule(
            $rule,
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(1, $result['previewed']);
        $this->assertSame($before, $task->fresh()->getAttributes());
        $this->assertDatabaseCount('agent_action_proposals', 0);
        $this->assertDatabaseHas('automation_rule_runs', [
            'automation_rule_id' => $rule->id,
            'outcome' => 'previewed',
        ]);
    }

    public function test_level_two_rule_owned_by_viewer_is_blocked(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$owner, $organization] = $this->context();
        $task = $this->criticalTask($owner, $organization);

        $viewer = User::factory()->create([
            'email' => 'viewer-l2-auto@arpynet.com',
        ]);
        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $rule = $this->rule($viewer, $organization, 'automatic', true);

        $result = app(AutomationRuleExecutor::class)->runRule(
            $rule,
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(1, $result['blocked']);
        $this->assertSame('pending', $task->fresh()->status);
        $this->assertDatabaseCount('agent_action_proposals', 0);
    }

    public function test_level_two_can_be_created_from_front_but_rule_starts_inactive(): void
    {
        [$user, $organization] = $this->context();

        $this->actingAs($user)->post(
            route('automation-center.store'),
            [
                'organization_id' => $organization->id,
                'name' => 'L2 crítica ARPYNET',
                'trigger_key' => 'decision.level2_task_start',
                'action_key' => 'decision.execute_task_start',
                'mode' => 'automatic',
            ],
        )->assertRedirect(route('automation-center.index'));

        $this->assertDatabaseHas('automation_rules', [
            'organization_id' => $organization->id,
            'name' => 'L2 crítica ARPYNET',
            'mode' => 'automatic',
            'is_active' => 0,
        ]);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);
        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-autonomy-l2-auto',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);
        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        return [$user, $organization];
    }

    private function criticalTask(
        User $user,
        Organization $organization,
    ): Task {
        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea crítica automática L2',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'critical',
            'due_at' => '2026-09-12 17:00:00',
            'next_action' => null,
            'created_by' => $user->id,
        ]);
    }

    private function rule(
        User $user,
        Organization $organization,
        string $mode,
        bool $active,
    ): AutomationRule {
        return AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Autonomía L2 automática',
            'trigger_key' => 'decision.level2_task_start',
            'action_key' => 'decision.execute_task_start',
            'mode' => $mode,
            'is_active' => $active,
            'created_by' => $user->id,
        ]);
    }
}
'''

test_path = Path(
    "tests/Feature/AutonomyLevelTwoAutomationTest.php"
)
if test_path.exists():
    raise SystemExit(
        "tests/Feature/AutonomyLevelTwoAutomationTest.php ya existe"
    )
test_path.write_text(integration_test)

print("2.32 Autonomía L2 integrada correctamente")
