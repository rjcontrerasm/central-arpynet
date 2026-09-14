#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{path}: expected exactly one match, found {count}")
    file.write_text(text.replace(old, new, 1))


# AutomationRuleCatalog: dedicated L3 trigger/action. Generic cross-module
# actions remain confirmation-only.
replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """        'decision.prepare_task_start_proposal',\n        'decision.execute_task_start',\n    ];""",
    """        'decision.prepare_task_start_proposal',\n        'decision.execute_task_start',\n        'decision.create_collection_task',\n    ];""",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'decision.level2_task_start' => [\n                'label' =>\n                    'Autonomía L2: iniciar tarea crítica pendiente',\n                'subject' => 'task',\n                'actions' => [\n                    'decision.execute_task_start',\n                ],\n            ],\n        ];""",
    """            'decision.level2_task_start' => [\n                'label' =>\n                    'Autonomía L2: iniciar tarea crítica pendiente',\n                'subject' => 'task',\n                'actions' => [\n                    'decision.execute_task_start',\n                ],\n            ],\n\n            'decision.level3_invoice_collection' => [\n                'label' =>\n                    'Autonomía L3: factura vencida sin siguiente acción',\n                'subject' => 'service_order',\n                'actions' => [\n                    'decision.create_collection_task',\n                ],\n            ],\n        ];""",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'decision.execute_task_start' =>\n                $this->buildAction(\n                    'Autonomía L2: iniciar tarea crítica',\n                    [\n                        'preview',\n                        'automatic',\n                    ],\n                    true,\n                    true,\n                    'bounded_reversible_task_start',\n                ),\n        ];""",
    """            'decision.execute_task_start' =>\n                $this->buildAction(\n                    'Autonomía L2: iniciar tarea crítica',\n                    [\n                        'preview',\n                        'automatic',\n                    ],\n                    true,\n                    true,\n                    'bounded_reversible_task_start',\n                ),\n\n            'decision.create_collection_task' =>\n                $this->buildAction(\n                    'Autonomía L3: crear tarea interna de cobranza',\n                    [\n                        'preview',\n                        'automatic',\n                    ],\n                    true,\n                    true,\n                    'bounded_reversible_cross_module_task_create',\n                ),\n        ];""",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    "'central-automation-contract-v3'",
    "'central-automation-contract-v4'",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'automatic_execution_scope' =>\n                'internal_notifications_pending_proposals_and_bounded_task_start',""",
    """            'automatic_execution_scope' =>\n                'internal_notifications_pending_proposals_bounded_task_start_and_bounded_collection_task_create',""",
)

replace_once(
    "app/Support/AutomationRuleCatalog.php",
    """            'autonomy_level_two_enabled' =>\n                true,\n            'autonomous_subject_mutations_enabled' =>""",
    """            'autonomy_level_two_enabled' =>\n                true,\n            'autonomy_level_three_enabled' =>\n                true,\n            'bounded_autonomous_cross_module_task_creation_enabled' =>\n                true,\n            'bounded_autonomous_cross_module_scope' => [\n                'service_invoice.collection_task_create',\n            ],\n            'bounded_autonomous_cross_module_daily_limit' =>\n                AutonomyLevelThreePolicy::DAILY_EXECUTION_LIMIT,\n            'autonomous_subject_mutations_enabled' =>""",
)

# AutomationRuleEngine: dedicated L3 candidate path.
replace_once(
    "app/Support/AutomationRuleEngine.php",
    """            'decision.level2_task_start' => $this->levelTwoTasks($rule, $now),\n        };""",
    """            'decision.level2_task_start' => $this->levelTwoTasks($rule, $now),\n            'decision.level3_invoice_collection' => $this->levelThreeInvoices($rule, $now),\n        };""",
)

level_three_engine = r'''
    private function levelThreeInvoices(
        AutomationRule $rule,
        CarbonImmutable $now,
    ): Collection {
        $decisionEngine = app(
            DecisionEngine::class,
        );
        $policy = app(
            AutonomyLevelThreePolicy::class,
        );

        return ServiceOrder::query()
            ->with([
                'organization',
                'client',
            ])
            ->where(
                'organization_id',
                $rule->organization_id,
            )
            ->where('stage', 'invoiced')
            ->whereNull('paid_date')
            ->whereNotNull('invoice_due_date')
            ->whereDate(
                'invoice_due_date',
                '<',
                $now->toDateString(),
            )
            ->where(function ($query): void {
                $query
                    ->whereNull('next_action')
                    ->orWhere('next_action', '');
            })
            ->where(function ($query): void {
                $query
                    ->whereNotNull('invoice_number')
                    ->orWhereNotNull('invoice_date')
                    ->orWhere('invoice_amount', '>', 0);
            })
            ->limit(250)
            ->get()
            ->map(function (ServiceOrder $order) use (
                $rule,
                $now,
                $decisionEngine,
                $policy,
            ): ?array {
                $item = GlobalTrackingItemFactory::serviceOrder(
                    $order,
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
                    $order,
                    $now,
                );

                if (! $autonomy['eligible']) {
                    return null;
                }

                return $this->candidate(
                    $rule,
                    'service_order',
                    $order->id,
                    $order->title,
                    'Autonomía L3 · '
                        .($decision['why_now']
                            ?? 'cobranza vencida sin siguiente acción'),
                    $order->updated_at,
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
                        'cross_module.collection_task_create',
                ];
            })
            ->filter()
            ->take(
                AutonomyLevelThreePolicy::DAILY_EXECUTION_LIMIT,
            )
            ->values();
    }

'''

replace_once(
    "app/Support/AutomationRuleEngine.php",
    "    private function candidate(\n",
    level_three_engine + "    private function candidate(\n",
)

# AutomationRuleExecutor: L3 stays a separate, allowlisted automatic path.
replace_once(
    "app/Support/AutomationRuleExecutor.php",
    """        private readonly AutonomyLevelOneService $autonomyLevelOne,\n        private readonly AutonomyLevelTwoService $autonomyLevelTwo,\n    ) {""",
    """        private readonly AutonomyLevelOneService $autonomyLevelOne,\n        private readonly AutonomyLevelTwoService $autonomyLevelTwo,\n        private readonly AutonomyLevelThreeService $autonomyLevelThree,\n    ) {""",
)

replace_once(
    "app/Support/AutomationRuleExecutor.php",
    """        if (\n            $rule->action_key\n            === 'decision.execute_task_start'\n        ) {\n            return $this->executeAutonomyLevelTwo(\n                $rule,\n                $candidate,\n            );\n        }\n\n        $recipient =""",
    """        if (\n            $rule->action_key\n            === 'decision.execute_task_start'\n        ) {\n            return $this->executeAutonomyLevelTwo(\n                $rule,\n                $candidate,\n            );\n        }\n\n        if (\n            $rule->action_key\n            === 'decision.create_collection_task'\n        ) {\n            return $this->executeAutonomyLevelThree(\n                $rule,\n                $candidate,\n            );\n        }\n\n        $recipient =""",
)

level_three_executor = r'''
    private function executeAutonomyLevelThree(
        AutomationRule $rule,
        array $candidate,
    ): string {
        if (
            ($candidate['subject_type'] ?? null)
                !== 'service_order'
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

        $order = \App\Models\ServiceOrder::query()->find(
            (int) (
                $candidate['subject_id']
                ?? 0
            ),
        );

        if (
            ! $order
            || (int) $order->organization_id
                !== (int) $rule->organization_id
        ) {
            return 'blocked';
        }

        try {
            $result = $this->autonomyLevelThree->execute(
                $actor,
                $order,
                $rule,
            );
        } catch (
            \Illuminate\Auth\Access\AuthorizationException
            | \Illuminate\Validation\ValidationException
            | \Illuminate\Database\Eloquent\ModelNotFoundException
        ) {
            return 'blocked';
        }

        return ($result['executed'] ?? false)
            ? 'executed'
            : 'blocked';
    }

'''

replace_once(
    "app/Support/AutomationRuleExecutor.php",
    "    private function resolveRecipient(\n",
    level_three_executor + "    private function resolveRecipient(\n",
)

# UI: explain the exact boundary; new rules remain inactive via existing store.
replace_once(
    "resources/views/automation-center.blade.php",
    "L1 prepara propuestas; L2 solo puede iniciar tareas críticas pendientes bajo límites y undo. Los canales externos permanecen fuera de la autonomía.",
    "L1 prepara propuestas; L2 puede iniciar tareas críticas pendientes; L3 puede crear una tarea interna de cobranza desde una factura vencida sin siguiente acción. Cada nivel conserva límites, audit y undo; los canales externos permanecen fuera de la autonomía.",
)

replace_once(
    "resources/views/automation-center.blade.php",
    "Las reglas nuevas nacen inactivas. Autonomía L1 solo prepara propuestas. Autonomía L2 requiere opt-in explícito y puede ejecutar únicamente pending → in_progress en tareas críticas con evidencia alta, score ≥92, máximo 3 veces por organización y día y siempre con undo. Completar, reprogramar, crear entidades, proyectos, servicios, obligaciones y canales externos siguen fuera de L2.",
    "Las reglas nuevas nacen inactivas. L1 solo prepara propuestas. L2 requiere opt-in explícito y puede ejecutar únicamente pending → in_progress en tareas críticas con evidencia alta y score ≥92, máximo 3 veces por organización y día. L3 requiere su propio opt-in y solo puede crear una tarea interna de cobranza para un servicio facturado, impago, vencido y sin siguiente acción, con evidencia alta, score ≥95, máximo 2 veces por organización y día. L3 no modifica el servicio ni sus datos financieros. Todos los writes autónomos exigen undo y los canales externos siguen fuera de la autonomía.",
)

# Scheduler safety contract: preserve generic cross-module confirmation-only
# while exposing the dedicated bounded L3 exception.
replace_once(
    "tests/Feature/AutomationSchedulerSafetyTest.php",
    """        $this->assertTrue(\n            $contract['autonomy_level_two_enabled'],\n        );\n\n        $this->assertSame(""",
    """        $this->assertTrue(\n            $contract['autonomy_level_two_enabled'],\n        );\n\n        $this->assertTrue(\n            $contract['autonomy_level_three_enabled'],\n        );\n\n        $this->assertTrue(\n            $contract['bounded_autonomous_cross_module_task_creation_enabled'],\n        );\n\n        $this->assertSame(\n            ['service_invoice.collection_task_create'],\n            $contract['bounded_autonomous_cross_module_scope'],\n        );\n\n        $this->assertSame(\n            2,\n            $contract['bounded_autonomous_cross_module_daily_limit'],\n        );\n\n        $this->assertSame(""",
)

replace_once(
    "tests/Feature/AutomationSchedulerSafetyTest.php",
    """            'internal_notifications_pending_proposals_and_bounded_task_start',\n            $contract['automatic_execution_scope'],""",
    """            'internal_notifications_pending_proposals_bounded_task_start_and_bounded_collection_task_create',\n            $contract['automatic_execution_scope'],""",
)

# End-to-end integration test written only after runtime patches exist.
integration_test = r'''<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Support\AutomationRuleExecutor;
use App\Support\AutonomyLevelThreeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutonomyLevelThreeAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_automatic_level_three_rule_creates_bounded_reversible_collection_task(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$user, $organization, $client] = $this->context();
        $order = $this->order($user, $organization, $client);
        $before = $order->fresh()->getAttributes();
        $rule = $this->rule($user, $organization, 'automatic', true);

        $result = app(AutomationRuleExecutor::class)->runRule(
            $rule,
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(1, $result['matches']);
        $this->assertSame(1, $result['executed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame($before, $order->fresh()->getAttributes());

        $task = Task::query()->sole();
        $this->assertSame('pending', $task->status);
        $this->assertSame($organization->id, $task->organization_id);
        $this->assertSame($user->id, $task->assigned_to);
        $this->assertSame(
            AutonomyLevelThreeService::EXTERNAL_SYSTEM,
            $task->external_system,
        );

        $this->assertDatabaseHas('automation_rule_runs', [
            'automation_rule_id' => $rule->id,
            'subject_type' => 'service_order',
            'subject_id' => $order->id,
            'outcome' => 'executed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'event' => 'autonomy.level3.executed',
            'subject_type' => 'service_order',
            'subject_id' => $order->id,
        ]);
    }

    public function test_preview_level_three_rule_never_creates_task_or_mutates_service(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$user, $organization, $client] = $this->context();
        $order = $this->order($user, $organization, $client);
        $before = $order->fresh()->getAttributes();
        $rule = $this->rule($user, $organization, 'preview', true);

        $result = app(AutomationRuleExecutor::class)->runRule(
            $rule,
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(1, $result['previewed']);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_level_three_rule_owned_by_viewer_is_blocked(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$owner, $organization, $client] = $this->context();
        $order = $this->order($owner, $organization, $client);

        $viewer = User::factory()->create([
            'email' => 'viewer-l3-auto@arpynet.com',
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
        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame('invoiced', $order->fresh()->stage);
    }

    public function test_level_three_rule_can_be_created_from_front_but_starts_inactive(): void
    {
        [$user, $organization] = $this->context(false);

        $this->actingAs($user)->post(
            route('automation-center.store'),
            [
                'organization_id' => $organization->id,
                'name' => 'L3 cobranza ARPYNET',
                'trigger_key' => 'decision.level3_invoice_collection',
                'action_key' => 'decision.create_collection_task',
                'mode' => 'automatic',
            ],
        )->assertRedirect(route('automation-center.index'));

        $this->assertDatabaseHas('automation_rules', [
            'organization_id' => $organization->id,
            'name' => 'L3 cobranza ARPYNET',
            'mode' => 'automatic',
            'is_active' => 0,
        ]);
    }

    private function context(bool $withClient = true): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);
        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-autonomy-l3-auto',
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

        if (! $withClient) {
            return [$user, $organization];
        }

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente L3 Automation',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return [$user, $organization, $client];
    }

    private function order(
        User $user,
        Organization $organization,
        Client $client,
    ): ServiceOrder {
        return ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio cobranza L3 automática',
            'stage' => 'invoiced',
            'invoice_number' => 'F001-333',
            'invoice_date' => '2026-08-20',
            'invoice_due_date' => '2026-09-10',
            'invoice_amount' => 3000,
            'currency' => 'PEN',
            'next_action' => null,
            'paid_date' => null,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
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
            'name' => 'Autonomía L3 automática',
            'trigger_key' => 'decision.level3_invoice_collection',
            'action_key' => 'decision.create_collection_task',
            'mode' => $mode,
            'is_active' => $active,
            'created_by' => $user->id,
        ]);
    }
}
'''

integration_path = Path("tests/Feature/AutonomyLevelThreeAutomationTest.php")
if integration_path.exists():
    raise SystemExit(f"{integration_path}: already exists")
integration_path.write_text(integration_test)

print("2.33 Autonomía L3 integrada correctamente")
