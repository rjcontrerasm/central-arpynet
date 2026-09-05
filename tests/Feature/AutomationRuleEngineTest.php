<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Support\AutomationRuleCatalog;
use App\Support\AutomationRuleEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AutomationRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_catalog_is_closed_and_manual_execution_is_limited(): void
    {
        $contract = app(AutomationRuleCatalog::class)->contract();

        $this->assertFalse($contract['public_api']);
        $this->assertFalse($contract['network_calls']);
        $this->assertFalse($contract['delete_actions']);
        $this->assertTrue($contract['execution_enabled']);
        $this->assertTrue($contract['manual_execution_enabled']);
        $this->assertTrue($contract['scheduler_enabled']);
        $this->assertFalse($contract['external_channels']);
        $this->assertFalse($contract['subject_mutations_enabled']);
        $this->assertTrue($contract['confirmed_subject_mutations_enabled']);
        $this->assertTrue($contract['preview_read_only']);
    }

    public function test_invalid_trigger_action_pair_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AutomationRuleCatalog::class)->validate(
            'task.overdue',
            'service.create_collection_reminder',
            'preview',
        );
    }

    public function test_overdue_task_preview_is_read_only(): void
    {
        CarbonImmutable::setTestNow('2026-09-03 10:00:00');

        [$user, $organization] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea vencida automation',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => '2026-09-02 17:00:00',
            'created_by' => $user->id,
        ]);

        $before = $task->fresh()->getAttributes();

        $rule = AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Preview tarea vencida',
            'trigger_key' => 'task.overdue',
            'action_key' => 'task.raise_attention',
            'mode' => 'preview',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $matches = app(AutomationRuleEngine::class)->preview(
            $rule,
            CarbonImmutable::now(),
        );

        $this->assertCount(1, $matches);
        $this->assertSame($task->id, $matches->first()['subject_id']);
        $this->assertSame($before, $task->fresh()->getAttributes());
        $this->assertSame(0, AutomationRuleRun::query()->count());
    }

    public function test_invoice_overdue_preview_matches_only_unpaid_invoice(): void
    {
        CarbonImmutable::setTestNow('2026-09-03 10:00:00');

        [$user, $organization, $client] = $this->context(true);

        $overdue = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Factura vencida',
            'stage' => 'invoiced',
            'invoice_number' => 'F001-001',
            'invoice_amount' => 500,
            'invoice_due_date' => '2026-09-01',
            'currency' => 'PEN',
            'includes_tax' => true,
            'created_by' => $user->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Factura pagada',
            'stage' => 'paid',
            'invoice_number' => 'F001-002',
            'invoice_amount' => 300,
            'invoice_due_date' => '2026-09-01',
            'paid_date' => '2026-09-02',
            'currency' => 'PEN',
            'includes_tax' => true,
            'created_by' => $user->id,
        ]);

        $rule = AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Preview cobranza',
            'trigger_key' => 'service.invoice_overdue',
            'action_key' => 'service.create_collection_reminder',
            'mode' => 'automatic',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $matches = app(AutomationRuleEngine::class)->preview(
            $rule,
            CarbonImmutable::now(),
        );

        $this->assertCount(1, $matches);
        $this->assertSame($overdue->id, $matches->first()['subject_id']);
        $this->assertSame(0, AutomationRuleRun::query()->count());
    }

    private function context(bool $withClient = false): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-automation',
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

        if (! $withClient) {
            return [$user, $organization];
        }

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente Automation',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return [$user, $organization, $client];
    }
}
