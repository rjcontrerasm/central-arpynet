<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\AutomationConfirmationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_waiting_return_requires_confirmation_and_is_undoable(): void
    {
        CarbonImmutable::setTestNow('2026-09-05 09:00:00');

        [$user, $organization] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Esperando proveedor',
            'status' => 'waiting',
            'urgency' => 'normal',
            'impact' => 'normal',
            'waiting_since' => '2026-09-01 10:00:00',
            'waiting_reason' => 'Respuesta proveedor',
            'waiting_until' => '2026-09-04 17:00:00',
            'created_by' => $user->id,
        ]);

        $rule = AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Retorno Mi día',
            'trigger_key' => 'waiting.followup_overdue',
            'action_key' => 'waiting.return_to_daily',
            'mode' => 'confirmation',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $run = AutomationRuleRun::query()->create([
            'automation_rule_id' => $rule->id,
            'organization_id' => $organization->id,
            'subject_type' => 'task',
            'subject_id' => $task->id,
            'fingerprint' => hash('sha256', 'confirm-waiting'),
            'outcome' => 'pending_confirmation',
            'payload' => [
                'title' => $task->title,
                'reason' => 'Seguimiento vencido',
            ],
            'evaluated_at' => now(),
        ]);

        app(AutomationConfirmationService::class)
            ->confirm($user, $run);

        $task->refresh();
        $run->refresh();

        $this->assertSame('pending', $task->status);
        $this->assertNull($task->waiting_until);
        $this->assertSame('executed', $run->outcome);
        $this->assertNotNull(
            data_get($run->payload, 'confirmation.actor_id'),
        );

        $undo = app(\App\Support\GlobalUndoService::class)
            ->current($user);

        $this->assertNotNull($undo);

        $result = app(\App\Support\GlobalUndoService::class)
            ->undo($user, $undo->id);

        $this->assertTrue($result['ok']);

        $task->refresh();

        $this->assertSame('waiting', $task->status);
        $this->assertNotNull($task->waiting_until);
    }

    public function test_confirmation_can_be_rejected_without_mutating_subject(): void
    {
        [$user, $organization] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Esperando respuesta',
            'status' => 'waiting',
            'urgency' => 'normal',
            'impact' => 'normal',
            'waiting_until' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $before = $task->fresh()->getAttributes();

        $rule = AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Rechazo',
            'trigger_key' => 'waiting.followup_overdue',
            'action_key' => 'waiting.return_to_daily',
            'mode' => 'confirmation',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $run = AutomationRuleRun::query()->create([
            'automation_rule_id' => $rule->id,
            'organization_id' => $organization->id,
            'subject_type' => 'task',
            'subject_id' => $task->id,
            'fingerprint' => hash('sha256', 'reject-waiting'),
            'outcome' => 'pending_confirmation',
            'payload' => [
                'title' => $task->title,
            ],
            'evaluated_at' => now(),
        ]);

        app(AutomationConfirmationService::class)
            ->reject($user, $run);

        $this->assertSame(
            $before,
            $task->fresh()->getAttributes(),
        );

        $this->assertSame(
            'rejected',
            $run->fresh()->outcome,
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'automation-confirmation',
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

        return [$user, $organization];
    }
}
