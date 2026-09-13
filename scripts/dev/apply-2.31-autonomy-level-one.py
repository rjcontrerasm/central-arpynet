#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{path}: esperaba 1 coincidencia y encontré {count}")
    file.write_text(text.replace(old, new, 1))


# Automation catalog: explicit L1 trigger/action and contract.
replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """    private const AUTOMATIC_INTERNAL_ACTIONS = [
        'service.create_billing_reminder',
        'service.create_collection_reminder',
        'obligation.create_alert',
        'project.create_alert',
    ];""",
    """    private const AUTOMATIC_INTERNAL_ACTIONS = [
        'service.create_billing_reminder',
        'service.create_collection_reminder',
        'obligation.create_alert',
        'project.create_alert',
        'decision.prepare_task_start_proposal',
    ];""",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'waiting.followup_overdue' => [
                'label' =>
                    'Seguimiento en espera vencido',
                'subject' => 'task',
                'actions' => [
                    'waiting.return_to_daily',
                ],
            ],
        ];""",
    """            'waiting.followup_overdue' => [
                'label' =>
                    'Seguimiento en espera vencido',
                'subject' => 'task',
                'actions' => [
                    'waiting.return_to_daily',
                ],
            ],

            'decision.level1_task' => [
                'label' =>
                    'Autonomía L1: decisión crítica de tarea',
                'subject' => 'task',
                'actions' => [
                    'decision.prepare_task_start_proposal',
                ],
            ],
        ];""",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'waiting.return_to_daily' =>
                $this->buildAction(
                    'Proponer retorno a Mi día',
                    [
                        'preview',
                        'confirmation',
                    ],
                    true,
                    true,
                    'confirmed_task_mutation',
                ),
        ];""",
    """            'waiting.return_to_daily' =>
                $this->buildAction(
                    'Proponer retorno a Mi día',
                    [
                        'preview',
                        'confirmation',
                    ],
                    true,
                    true,
                    'confirmed_task_mutation',
                ),

            'decision.prepare_task_start_proposal' =>
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
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'automatic_execution_scope' =>
                'database_notifications_only',
            'subject_mutations_enabled' =>
                false,""",
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
)

# Engine: derive deterministic L1 candidates from Decision Engine.
replace_once(
    "app/Support/AutomationRuleEngine.php",
    """            'waiting.followup_overdue' => $this->waitingOverdue($rule, $now),
        };""",
    """            'waiting.followup_overdue' => $this->waitingOverdue($rule, $now),
            'decision.level1_task' => $this->levelOneTasks($rule, $now),
        };""",
)

replace_once(
    "app/Support/AutomationRuleEngine.php",
    """    private function candidate(
        AutomationRule $rule,""",
    """    private function levelOneTasks(
        AutomationRule $rule,
        CarbonImmutable $now,
    ): Collection {
        $decisionEngine = app(
            DecisionEngine::class,
        );
        $policy = app(
            AutonomyLevelOnePolicy::class,
        );

        return Task::query()
            ->with('organization')
            ->where(
                'organization_id',
                $rule->organization_id,
            )
            ->whereIn(
                'status',
                ['inbox', 'pending'],
            )
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
                    'Autonomía L1 · '
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
                ];
            })
            ->filter()
            ->take(100)
            ->values();
    }

    private function candidate(
        AutomationRule $rule,""",
)

# Executor: L1 uses the exact rule creator and prepares a pending proposal only.
replace_once(
    "app/Support/AutomationRuleExecutor.php",
    """use App\\Models\\AutomationRule;
use App\\Models\\AutomationRuleRun;
use App\\Models\\User;""",
    """use App\\Models\\AutomationRule;
use App\\Models\\AutomationRuleRun;
use App\\Models\\Task;
use App\\Models\\User;""",
)

replace_once(
    "app/Support/AutomationRuleExecutor.php",
    """    public function __construct(
        private readonly AutomationRuleCatalog $catalog,
        private readonly AutomationRuleEngine $engine,
    ) {
    }""",
    """    public function __construct(
        private readonly AutomationRuleCatalog $catalog,
        private readonly AutomationRuleEngine $engine,
        private readonly AutonomyLevelOneService $autonomyLevelOne,
    ) {
    }""",
)

replace_once(
    "app/Support/AutomationRuleExecutor.php",
    """        $recipient =
            $this->resolveRecipient(
                $rule,
            );""",
    """        if (
            $rule->action_key
            === 'decision.prepare_task_start_proposal'
        ) {
            return $this->executeAutonomyLevelOne(
                $rule,
                $candidate,
            );
        }

        $recipient =
            $this->resolveRecipient(
                $rule,
            );""",
)

replace_once(
    "app/Support/AutomationRuleExecutor.php",
    """    private function resolveRecipient(
        AutomationRule $rule,
    ): ?User {""",
    """    private function executeAutonomyLevelOne(
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
            $this->autonomyLevelOne->prepare(
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

        return 'executed';
    }

    private function resolveRecipient(
        AutomationRule $rule,
    ): ?User {""",
)

# Front copy: explain the real scheduler and the strict L1 boundary.
replace_once(
    "resources/views/automation-center.blade.php",
    """            <p>Reglas internas, controladas y auditables. El scheduler y los canales externos siguen deshabilitados.</p>""",
    """            <p>Reglas internas, controladas y auditables. El scheduler evalúa reglas activas; los canales externos no forman parte de la ejecución automática.</p>""",
)

replace_once(
    "resources/views/automation-center.blade.php",
    """                Las reglas nuevas nacen inactivas. El modo automático solo está permitido para notificaciones internas seguras. La creación de tareas entre módulos exige siempre confirmación humana.""",
    """                Las reglas nuevas nacen inactivas. Autonomía L1 puede preparar una propuesta pendiente para una tarea crítica inequívoca, pero nunca la aprueba ni ejecuta. Las mutaciones de entidades y la creación de tareas entre módulos mantienen confirmación humana.""",
)

replace_once(
    "tests/Feature/AutomationCenterTest.php",
    """            ->assertSee('El scheduler y los canales externos siguen deshabilitados.');""",
    """            ->assertSee('El scheduler evalúa reglas activas')
            ->assertSee('Autonomía L1');""",
)

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

class AutonomyLevelOneAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_automatic_level_one_rule_prepares_pending_proposal_and_deduplicates(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');
        [$user, $organization] = $this->context();
        $task = $this->criticalTask($user, $organization);
        $before = $task->fresh()->getAttributes();
        $rule = $this->rule($user, $organization, 'automatic');
        $executor = app(AutomationRuleExecutor::class);

        $first = $executor->runRule($rule, 100, CarbonImmutable::now());

        $this->assertSame(1, $first['matches']);
        $this->assertSame(1, $first['executed']);
        $this->assertDatabaseHas('agent_action_proposals', [
            'organization_id' => $organization->id,
            'subject_type' => 'task',
            'subject_id' => $task->id,
            'action_key' => 'start',
            'status' => 'pending',
        ]);
        $this->assertSame($before, $task->fresh()->getAttributes());

        $second = $executor->runRule($rule->fresh(), 100, CarbonImmutable::now());

        $this->assertSame(1, $second['duplicates']);
        $this->assertSame(1, AgentActionProposal::query()->count());
        $this->assertSame(1, AutomationRuleRun::query()->count());
        $this->assertSame($before, $task->fresh()->getAttributes());
    }

    public function test_preview_level_one_rule_never_creates_proposal(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');
        [$user, $organization] = $this->context();
        $task = $this->criticalTask($user, $organization);
        $before = $task->fresh()->getAttributes();
        $rule = $this->rule($user, $organization, 'preview');

        $result = app(AutomationRuleExecutor::class)
            ->runRule($rule, 100, CarbonImmutable::now());

        $this->assertSame(1, $result['previewed']);
        $this->assertDatabaseCount('agent_action_proposals', 0);
        $this->assertSame($before, $task->fresh()->getAttributes());
    }

    public function test_level_one_blocks_rule_creator_who_is_now_viewer(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');
        [$owner, $organization] = $this->context();
        $viewer = User::factory()->create([
            'email' => 'viewer-l1@arpynet.com',
        ]);
        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);
        $task = $this->criticalTask($owner, $organization);
        $before = $task->fresh()->getAttributes();
        $rule = $this->rule($viewer, $organization, 'automatic');

        $result = app(AutomationRuleExecutor::class)
            ->runRule($rule, 100, CarbonImmutable::now());

        $this->assertSame(1, $result['blocked']);
        $this->assertDatabaseCount('agent_action_proposals', 0);
        $this->assertSame($before, $task->fresh()->getAttributes());
    }

    public function test_noncritical_task_is_not_an_autonomy_level_one_candidate(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');
        [$user, $organization] = $this->context();
        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea planificada normal',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => '2026-10-20 17:00:00',
            'next_action' => 'Esperar fecha planificada',
            'created_by' => $user->id,
        ]);
        $rule = $this->rule($user, $organization, 'automatic');

        $result = app(AutomationRuleExecutor::class)
            ->runRule($rule, 100, CarbonImmutable::now());

        $this->assertSame(0, $result['matches']);
        $this->assertSame(0, $result['executed']);
        $this->assertDatabaseCount('agent_action_proposals', 0);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);
        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-autonomy-level-one-automation',
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

    private function criticalTask(User $user, Organization $organization): Task
    {
        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea crítica vencida L1',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'critical',
            'due_at' => '2026-09-12 17:00:00',
            'next_action' => null,
            'created_by' => $user->id,
        ]);
    }

    private function rule(
        User $creator,
        Organization $organization,
        string $mode,
    ): AutomationRule {
        return AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Autonomía L1 tareas críticas',
            'trigger_key' => 'decision.level1_task',
            'action_key' => 'decision.prepare_task_start_proposal',
            'mode' => $mode,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);
    }
}
'''

integration_path = Path(
    "tests/Feature/AutonomyLevelOneAutomationTest.php"
)
if integration_path.exists():
    raise SystemExit(
        f"{integration_path}: ya existe; no sobrescribo"
    )
integration_path.write_text(integration_test)

print("2.31 Autonomía L1 integrada correctamente")
