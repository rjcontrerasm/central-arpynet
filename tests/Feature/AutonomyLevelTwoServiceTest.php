<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\AutonomyLevelTwoService;
use App\Support\GlobalUndoService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutonomyLevelTwoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_level_two_executes_only_start_transition_and_creates_reversible_audit_trail(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');

        [$user, $organization] = $this->context();
        $this->actingAs($user);

        $task = $this->criticalTask($user, $organization);
        $rule = $this->levelTwoRule($user, $organization);

        $result = app(AutonomyLevelTwoService::class)
            ->execute(
                $user,
                $task,
                $rule,
                CarbonImmutable::now(),
            );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['executed']);
        $this->assertFalse($result['stale']);
        $this->assertNotNull($result['undo_action_id']);
        $this->assertSame(
            'in_progress',
            $task->fresh()->status,
        );

        $proposal = AgentActionProposal::query()->sole();

        $this->assertSame('executed', $proposal->status);
        $this->assertSame('start', $proposal->action_key);
        $this->assertSame($user->id, $proposal->reviewed_by);
        $this->assertSame($user->id, $proposal->executed_by);
        $this->assertSame(
            $result['undo_action_id'],
            $proposal->undo_action_id,
        );

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'event' => 'agent_proposal.autonomy_approved',
            'source' => 'central_autonomy_level_two',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'event' => 'autonomy.level2.executed',
            'subject_type' => 'task',
            'subject_id' => $task->id,
        ]);

        $undo = app(GlobalUndoService::class)->undo(
            $user,
            (int) $result['undo_action_id'],
        );

        $this->assertTrue($undo['ok']);
        $this->assertSame('pending', $task->fresh()->status);
    }

    public function test_level_two_requires_exact_active_automatic_rule_owned_by_actor(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');

        [$user, $organization] = $this->context();
        $this->actingAs($user);

        $task = $this->criticalTask($user, $organization);
        $rule = $this->levelTwoRule($user, $organization);
        $rule->forceFill(['is_active' => false])->save();

        $this->expectException(AuthorizationException::class);

        app(AutonomyLevelTwoService::class)->execute(
            $user,
            $task,
            $rule,
            CarbonImmutable::now(),
        );
    }

    public function test_level_two_blocks_viewer(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');

        [$owner, $organization] = $this->context();
        $task = $this->criticalTask($owner, $organization);

        $viewer = User::factory()->create([
            'email' => 'viewer-l2@arpynet.com',
        ]);

        $organization->users()->attach(
            $viewer->id,
            [
                'role' => 'viewer',
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $rule = AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Autonomía L2 viewer',
            'trigger_key' => 'decision.level2_task_start',
            'action_key' => 'decision.execute_task_start',
            'mode' => 'automatic',
            'is_active' => true,
            'created_by' => $viewer->id,
        ]);

        $this->expectException(AuthorizationException::class);

        app(AutonomyLevelTwoService::class)->execute(
            $viewer,
            $task,
            $rule,
            CarbonImmutable::now(),
        );
    }

    public function test_level_two_stops_after_three_autonomous_executions_per_organization_per_day(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');

        [$user, $organization] = $this->context();
        $this->actingAs($user);

        $task = $this->criticalTask($user, $organization);
        $rule = $this->levelTwoRule($user, $organization);

        foreach (range(1, 3) as $index) {
            AuditLog::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'event' => 'autonomy.level2.executed',
                'subject_type' => 'task',
                'subject_id' => 1000 + $index,
                'subject_label' => 'Autonomía previa '.$index,
                'source' => 'central_autonomy_level_two',
                'changes' => [],
                'occurred_at' => CarbonImmutable::now()
                    ->subMinutes($index),
            ]);
        }

        try {
            app(AutonomyLevelTwoService::class)->execute(
                $user,
                $task,
                $rule,
                CarbonImmutable::now(),
            );

            $this->fail('Debió bloquearse por límite diario L2.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'autonomy',
                $exception->errors(),
            );
        }

        $this->assertSame('pending', $task->fresh()->status);
        $this->assertDatabaseCount('agent_action_proposals', 0);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-autonomy-l2',
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
            'title' => 'Tarea crítica L2 vencida',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'critical',
            'due_at' => '2026-09-12 17:00:00',
            'next_action' => null,
            'created_by' => $user->id,
        ]);
    }

    private function levelTwoRule(
        User $user,
        Organization $organization,
    ): AutomationRule {
        return AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Autonomía L2 · iniciar críticas',
            'trigger_key' => 'decision.level2_task_start',
            'action_key' => 'decision.execute_task_start',
            'mode' => 'automatic',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
    }
}
