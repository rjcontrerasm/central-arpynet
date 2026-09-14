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
