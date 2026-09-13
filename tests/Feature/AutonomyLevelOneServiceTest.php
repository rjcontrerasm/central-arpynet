<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\AutomationRule;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\AutonomyLevelOneService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutonomyLevelOneServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_level_one_prepares_pending_start_proposal_without_mutating_task(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-13 10:00:00',
        );

        [$user, $organization] = $this->context();
        $task = $this->criticalTask(
            $user,
            $organization,
        );
        $before = $task->fresh()->getAttributes();

        $result = app(
            AutonomyLevelOneService::class,
        )->prepare(
            $user,
            $task,
            null,
            CarbonImmutable::now(),
        );

        $this->assertTrue($result['created']);
        $this->assertTrue($result['subject_unchanged']);
        $this->assertFalse($result['approval_performed']);
        $this->assertFalse($result['execution_performed']);
        $this->assertSame(
            'pending',
            $result['proposal']->status,
        );
        $this->assertSame(
            'start',
            $result['proposal']->action_key,
        );
        $this->assertSame(
            $before,
            $task->fresh()->getAttributes(),
        );
    }

    public function test_level_one_reuses_identical_pending_proposal(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-13 10:00:00',
        );

        [$user, $organization] = $this->context();
        $task = $this->criticalTask(
            $user,
            $organization,
        );
        $service = app(
            AutonomyLevelOneService::class,
        );

        $first = $service->prepare(
            $user,
            $task,
            null,
            CarbonImmutable::now(),
        );
        $second = $service->prepare(
            $user,
            $task->fresh(),
            null,
            CarbonImmutable::now(),
        );

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(
            $first['proposal']->id,
            $second['proposal']->id,
        );
        $this->assertSame(
            1,
            AgentActionProposal::query()->count(),
        );
    }

    public function test_level_one_blocks_viewer_even_without_authenticated_request_context(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-13 10:00:00',
        );

        [$owner, $organization] = $this->context();
        $task = $this->criticalTask(
            $owner,
            $organization,
        );

        $viewer = User::factory()->create([
            'email' => 'viewer@arpynet.com',
        ]);

        $organization->users()->attach(
            $viewer->id,
            [
                'role' => 'viewer',
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $this->expectException(
            AuthorizationException::class,
        );

        app(AutonomyLevelOneService::class)
            ->prepare(
                $viewer,
                $task,
                null,
                CarbonImmutable::now(),
            );
    }

    public function test_level_one_revalidates_current_task_and_rule_actor(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-13 10:00:00',
        );

        [$user, $organization] = $this->context();
        $task = $this->criticalTask(
            $user,
            $organization,
        );

        $rule = AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Autonomía L1',
            'trigger_key' => 'task.overdue',
            'action_key' => 'task.raise_attention',
            'mode' => 'preview',
            'is_active' => false,
            'created_by' => $user->id,
        ]);

        $task->forceFill([
            'status' => 'in_progress',
        ])->save();

        try {
            app(AutonomyLevelOneService::class)
                ->prepare(
                    $user,
                    $task->fresh(),
                    $rule,
                    CarbonImmutable::now(),
                );

            $this->fail(
                'Autonomía L1 debió rechazar una tarea que ya está en curso.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'autonomy',
                $exception->errors(),
            );
        }

        $this->assertDatabaseCount(
            'agent_action_proposals',
            0,
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-autonomy-l1',
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

    private function criticalTask(
        User $user,
        Organization $organization,
    ): Task {
        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea crítica vencida',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'critical',
            'due_at' => '2026-09-12 17:00:00',
            'next_action' => null,
            'created_by' => $user->id,
        ]);
    }
}
