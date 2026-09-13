<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\Client;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringObligation;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Support\AutomationConfirmationService;
use App\Support\AutomationRuleCatalog;
use App\Support\AutomationRuleEngine;
use App\Support\AutomationRuleExecutor;
use App\Support\GlobalUndoService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AutomationCrossModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_cross_module_task_actions_only_allow_confirmation(): void
    {
        $catalog = app(AutomationRuleCatalog::class);

        foreach ([
            ['project.no_next_action', 'project.create_task'],
            ['project.blocked', 'project.create_task'],
            ['service.conformity_ready', 'service.create_task'],
            ['service.invoice_overdue', 'service.create_task'],
            ['obligation.due_soon', 'obligation.create_task'],
        ] as [$trigger, $action]) {
            $catalog->validate($trigger, $action, 'confirmation');

            $definition = $catalog->definition($action);

            $this->assertSame(
                ['confirmation'],
                $definition['allowed_modes'],
            );
            $this->assertTrue($definition['execution_supported']);
            $this->assertTrue($definition['undo_required']);
            $this->assertFalse($definition['network_calls']);
            $this->assertFalse($definition['external']);
            $this->assertSame(
                'confirmed_cross_module_task_create',
                $definition['effect'],
            );
        }

        $this->assertTrue(
            $catalog->contract()['confirmed_cross_module_task_creation_enabled'],
        );
        $this->assertFalse(
            $catalog->contract()['automatic_cross_module_task_creation_enabled'],
        );
    }

    public function test_cross_module_task_action_rejects_automatic_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AutomationRuleCatalog::class)->validate(
            'project.no_next_action',
            'project.create_task',
            'automatic',
        );
    }

    public function test_project_signal_creates_task_only_after_confirmation_and_is_undoable(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 09:00:00');

        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto sin siguiente acción',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' => null,
            'created_by' => $user->id,
        ]);

        $rule = $this->rule(
            $organization,
            $user,
            'project.no_next_action',
            'project.create_task',
        );

        $preview = app(AutomationRuleEngine::class)->preview($rule);

        $this->assertCount(1, $preview);
        $this->assertDatabaseCount('tasks', 0);

        $summary = app(AutomationRuleExecutor::class)->runRule($rule);

        $this->assertSame(1, $summary['pending_confirmation']);
        $this->assertDatabaseCount('tasks', 0);

        $run = AutomationRuleRun::query()->firstOrFail();

        app(AutomationConfirmationService::class)->confirm($user, $run);

        $task = Task::query()->firstOrFail();

        $this->assertSame($organization->id, $task->organization_id);
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame('automation', $task->source);
        $this->assertSame($user->id, $task->assigned_to);
        $this->assertSame('executed', $run->fresh()->outcome);
        $this->assertSame(
            $task->id,
            data_get($run->fresh()->payload, 'cross_module_task.task_id'),
        );

        $second = app(AutomationRuleExecutor::class)->runRule($rule);

        $this->assertSame(1, $second['duplicates']);
        $this->assertDatabaseCount('tasks', 1);

        $undo = app(GlobalUndoService::class)->current($user);
        $this->assertNotNull($undo);

        $result = app(GlobalUndoService::class)->undo($user, $undo->id);
        $this->assertTrue($result['ok']);
        $this->assertTrue(
            Task::withTrashed()->findOrFail($task->id)->trashed(),
        );
    }

    public function test_service_signal_creates_same_organization_task_with_external_reference(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 09:00:00');

        [$user, $organization] = $this->context();

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente cross module',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio con conformidad',
            'stage' => 'conformity',
            'currency' => 'PEN',
            'created_by' => $user->id,
        ]);

        $rule = $this->rule(
            $organization,
            $user,
            'service.conformity_ready',
            'service.create_task',
        );

        app(AutomationRuleExecutor::class)->runRule($rule);

        $run = AutomationRuleRun::query()->firstOrFail();

        app(AutomationConfirmationService::class)->confirm($user, $run);

        $task = Task::query()->firstOrFail();

        $this->assertSame($organization->id, $task->organization_id);
        $this->assertNull($task->project_id);
        $this->assertSame('automation:service_order', $task->external_system);
        $this->assertSame((string) $service->id, $task->external_id);
        $this->assertStringContainsString('Facturar', $task->title);
    }

    public function test_obligation_signal_creates_task_and_rejection_creates_nothing(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 09:00:00');

        [$user, $organization] = $this->context();

        $obligation = RecurringObligation::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Renovación dominio',
            'category' => 'domain',
            'frequency' => 'annual',
            'anchor_date' => '2026-09-15',
            'currency' => 'PEN',
            'reminder_days_before' => 7,
            'is_critical' => true,
            'is_active' => false,
            'created_by' => $user->id,
        ]);

        $occurrence = ObligationOccurrence::query()->create([
            'recurring_obligation_id' => $obligation->id,
            'organization_id' => $organization->id,
            'due_date' => '2026-09-15',
            'status' => 'pending',
            'currency' => 'PEN',
        ]);

        $rule = $this->rule(
            $organization,
            $user,
            'obligation.due_soon',
            'obligation.create_task',
            ['days' => 7],
        );

        app(AutomationRuleExecutor::class)->runRule($rule);

        $run = AutomationRuleRun::query()->firstOrFail();

        app(AutomationConfirmationService::class)->reject($user, $run);

        $this->assertSame('rejected', $run->fresh()->outcome);
        $this->assertDatabaseCount('tasks', 0);

        $rule2 = $this->rule(
            $organization,
            $user,
            'obligation.due_soon',
            'obligation.create_task',
            ['days' => 7],
            'Atender vencimiento 2',
        );

        app(AutomationRuleExecutor::class)->runRule($rule2);

        $run2 = AutomationRuleRun::query()
            ->where('automation_rule_id', $rule2->id)
            ->firstOrFail();

        app(AutomationConfirmationService::class)->confirm($user, $run2);

        $task = Task::query()->firstOrFail();

        $this->assertSame('automation:obligation_occurrence', $task->external_system);
        $this->assertSame((string) $occurrence->id, $task->external_id);
        $this->assertStringContainsString('Renovación dominio', $task->title);
    }

    public function test_stale_source_is_not_converted_into_task(): void
    {
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto stale',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $rule = $this->rule(
            $organization,
            $user,
            'project.no_next_action',
            'project.create_task',
        );

        app(AutomationRuleExecutor::class)->runRule($rule);

        $run = AutomationRuleRun::query()->firstOrFail();

        $project->forceFill([
            'next_action' => 'Ya definida por una persona',
        ])->save();

        $result = app(AutomationConfirmationService::class)
            ->confirm($user, $run);

        $this->assertFalse($result['ok']);
        $this->assertSame('stale', $run->fresh()->outcome);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_read_only_member_cannot_confirm_cross_module_task_creation(): void
    {
        [$owner, $organization] = $this->context();

        $viewer = User::factory()->create();
        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto protegido',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $rule = $this->rule(
            $organization,
            $owner,
            'project.no_next_action',
            'project.create_task',
        );

        app(AutomationRuleExecutor::class)->runRule($rule);

        $run = AutomationRuleRun::query()->firstOrFail();

        $this->expectException(AuthorizationException::class);

        app(AutomationConfirmationService::class)
            ->confirm($viewer, $run);
    }

    private function rule(
        Organization $organization,
        User $user,
        string $trigger,
        string $action,
        array $triggerConfig = [],
        string $name = 'Cross module',
    ): AutomationRule {
        return AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'trigger_key' => $trigger,
            'action_key' => $action,
            'trigger_config' => $triggerConfig,
            'action_config' => [],
            'mode' => 'confirmation',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'automation-cross-module',
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
}
