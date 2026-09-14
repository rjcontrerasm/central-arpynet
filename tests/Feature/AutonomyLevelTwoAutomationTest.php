<?php

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
