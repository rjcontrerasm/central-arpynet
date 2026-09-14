<?php

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
