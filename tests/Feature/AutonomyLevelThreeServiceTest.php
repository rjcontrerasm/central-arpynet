<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Support\AutonomyLevelThreeService;
use App\Support\GlobalUndoService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutonomyLevelThreeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_level_three_creates_one_reversible_collection_task_without_mutating_service(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$user, $organization, $client] = $this->context();
        $order = $this->order($user, $organization, $client);
        $rule = $this->rule($user, $organization);
        $before = $order->fresh()->getAttributes();

        $result = app(AutonomyLevelThreeService::class)->execute(
            $user,
            $order,
            $rule,
            CarbonImmutable::now(),
        );

        $this->assertTrue($result['executed']);
        $this->assertNotNull($result['undo_action_id']);
        $this->assertSame($before, $order->fresh()->getAttributes());

        $task = Task::query()->sole();
        $this->assertSame($organization->id, $task->organization_id);
        $this->assertSame('pending', $task->status);
        $this->assertSame('high', $task->urgency);
        $this->assertSame('high', $task->impact);
        $this->assertSame($user->id, $task->assigned_to);
        $this->assertSame('automation', $task->source);
        $this->assertSame(
            AutonomyLevelThreeService::EXTERNAL_SYSTEM,
            $task->external_system,
        );
        $this->assertSame((string) $order->id, $task->external_id);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'event' => 'autonomy.level3.executed',
            'subject_type' => 'service_order',
            'subject_id' => $order->id,
        ]);

        $undo = app(GlobalUndoService::class)->undo(
            $user,
            $result['undo_action_id'],
        );

        $this->assertTrue($undo['ok']);
        $this->assertTrue(
            Task::withTrashed()->findOrFail($task->id)->trashed(),
        );
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_level_three_revalidates_source_and_blocks_existing_next_action(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$user, $organization, $client] = $this->context();
        $order = $this->order($user, $organization, $client);
        $rule = $this->rule($user, $organization);

        $order->forceFill([
            'next_action' => 'Llamar al cliente hoy',
        ])->save();

        try {
            app(AutonomyLevelThreeService::class)->execute(
                $user,
                $order,
                $rule,
                CarbonImmutable::now(),
            );
            $this->fail('L3 debió bloquear la creación.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('tasks', 0);
        }
    }

    public function test_level_three_requires_exact_active_rule_owner_with_write_permission(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$owner, $organization, $client] = $this->context();
        $order = $this->order($owner, $organization, $client);

        $viewer = User::factory()->create([
            'email' => 'viewer-l3@arpynet.com',
        ]);
        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $rule = $this->rule($viewer, $organization);

        $this->expectException(AuthorizationException::class);

        app(AutonomyLevelThreeService::class)->execute(
            $viewer,
            $order,
            $rule,
            CarbonImmutable::now(),
        );
    }

    public function test_level_three_stops_after_two_successful_executions_per_organization_day(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$user, $organization, $client] = $this->context();
        $order = $this->order($user, $organization, $client);
        $rule = $this->rule($user, $organization);

        foreach ([1, 2] as $id) {
            AuditLog::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'event' => 'autonomy.level3.executed',
                'subject_type' => 'service_order',
                'subject_id' => $id,
                'subject_label' => 'Cobranza previa '.$id,
                'source' => 'central_autonomy_level_three',
                'changes' => [],
                'occurred_at' => CarbonImmutable::now(),
            ]);
        }

        $this->expectException(ValidationException::class);

        try {
            app(AutonomyLevelThreeService::class)->execute(
                $user,
                $order,
                $rule,
                CarbonImmutable::now(),
            );
        } finally {
            $this->assertDatabaseCount('tasks', 0);
        }
    }

    public function test_undo_does_not_allow_autonomous_recreation_of_same_collection_task(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$user, $organization, $client] = $this->context();
        $order = $this->order($user, $organization, $client);
        $rule = $this->rule($user, $organization);

        $first = app(AutonomyLevelThreeService::class)->execute(
            $user,
            $order,
            $rule,
            CarbonImmutable::now(),
        );

        app(GlobalUndoService::class)->undo(
            $user,
            $first['undo_action_id'],
        );

        $this->expectException(ValidationException::class);

        app(AutonomyLevelThreeService::class)->execute(
            $user,
            $order,
            $rule,
            CarbonImmutable::now(),
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-autonomy-l3',
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

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente L3',
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
            'title' => 'Servicio con factura vencida L3',
            'stage' => 'invoiced',
            'invoice_number' => 'F001-233',
            'invoice_date' => '2026-08-20',
            'invoice_due_date' => '2026-09-10',
            'invoice_amount' => 2500,
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
    ): AutomationRule {
        return AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Autonomía L3 cobranza',
            'trigger_key' => 'decision.level3_invoice_collection',
            'action_key' => 'decision.create_collection_task',
            'mode' => 'automatic',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
    }
}
