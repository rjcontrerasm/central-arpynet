<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\ControlledDelegationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ControlledDelegationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_overdue_task_delegation_creates_pending_proposal_without_mutating_task(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-13 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Resolver incidente vencido',
            'next_action' => 'Validar causa',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                CarbonImmutable::now()->subDay(),
            'last_activity_at' =>
                CarbonImmutable::now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $before = [
            'status' => $task->status,
            'due_at' =>
                $task->due_at?->toIso8601String(),
            'completed_at' =>
                $task->completed_at?->toIso8601String(),
        ];

        $result = app(
            ControlledDelegationService::class,
        )->delegate(
            $user,
            'task',
            $task->id,
            'today',
        );

        $proposal = $result['proposal'];
        $freshTask = $task->fresh();

        $this->assertTrue($result['created']);
        $this->assertTrue($result['entity_unchanged']);
        $this->assertFalse($result['execution_performed']);
        $this->assertSame('pending', $proposal->status);
        $this->assertSame('task', $proposal->subject_type);
        $this->assertSame('today', $proposal->action_key);
        $this->assertStringContainsString(
            'Delegación controlada 2.30',
            (string) $proposal->rationale,
        );
        $this->assertStringContainsString(
            'no se ejecutó ningún cambio',
            mb_strtolower((string) $proposal->rationale),
        );
        $this->assertSame(
            $before,
            [
                'status' => $freshTask->status,
                'due_at' =>
                    $freshTask->due_at?->toIso8601String(),
                'completed_at' =>
                    $freshTask->completed_at?->toIso8601String(),
            ],
        );
    }

    public function test_identical_controlled_delegation_reuses_pending_proposal(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-13 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea vencida deduplicable',
            'next_action' => 'Revisar',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                CarbonImmutable::now()->subDays(2),
            'last_activity_at' =>
                CarbonImmutable::now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $service = app(
            ControlledDelegationService::class,
        );

        $first = $service->delegate(
            $user,
            'task',
            $task->id,
            'tomorrow',
        );

        $second = $service->delegate(
            $user,
            'task',
            $task->id,
            'tomorrow',
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

    public function test_project_without_next_action_can_prepare_content_proposal_without_mutation(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-13 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto sin siguiente paso',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' => null,
            'last_activity_at' =>
                CarbonImmutable::now(),
            'created_by' => $user->id,
        ]);

        $result = app(
            ControlledDelegationService::class,
        )->delegate(
            $user,
            'project',
            $project->id,
            'project.next_action.set',
            [
                'next_action' =>
                    'Confirmar cronograma con el cliente',
            ],
        );

        $this->assertSame(
            'project.next_action.set',
            $result['proposal']->action_key,
        );
        $this->assertSame(
            'Confirmar cronograma con el cliente',
            $result['proposal']
                ->proposed_changes['next_action'],
        );
        $this->assertNull(
            $project->fresh()->next_action,
        );
        $this->assertSame(
            'pending',
            $result['proposal']->status,
        );
    }

    public function test_delegation_is_rejected_when_decision_is_no_longer_active(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-13 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Plan trimestral estable',
            'next_action' => 'Revisar en diciembre',
            'status' => 'pending',
            'urgency' => 'low',
            'impact' => 'low',
            'due_at' =>
                CarbonImmutable::now()->addMonths(3),
            'last_activity_at' =>
                CarbonImmutable::now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->expectException(
            ValidationException::class,
        );

        app(ControlledDelegationService::class)
            ->delegate(
                $user,
                'task',
                $task->id,
                'today',
            );
    }

    public function test_foreign_organization_cannot_prepare_delegation(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-13 10:00:00',
        );

        [$user] = $this->context();

        $foreignUser = User::factory()->create();
        $foreign = Organization::query()->create([
            'name' => 'Empresa ajena',
            'slug' => 'empresa-ajena-230',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $foreignUser->id,
        ]);

        $foreign->users()->attach(
            $foreignUser->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $task = Task::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'Tarea ajena vencida',
            'next_action' => 'Atender',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                CarbonImmutable::now()->subDay(),
            'last_activity_at' =>
                CarbonImmutable::now(),
            'assigned_to' => $foreignUser->id,
            'created_by' => $foreignUser->id,
        ]);

        $this->expectException(
            AuthorizationException::class,
        );

        app(ControlledDelegationService::class)
            ->delegate(
                $user,
                'task',
                $task->id,
                'today',
            );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'delegation230@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET 2.30',
            'slug' => 'arpynet-230',
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
