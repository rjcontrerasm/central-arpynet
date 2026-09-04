<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Support\AutomationRuleExecutor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationRuleExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_automatic_collection_rule_creates_one_internal_notification_and_deduplicates(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-04 10:00:00',
        );

        [
            $user,
            $organization,
            $client,
        ] = $this->context();

        $order = ServiceOrder::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'client_id' =>
                    $client->id,
                'title' =>
                    'Factura vencida segura',
                'stage' => 'invoiced',
                'invoice_number' =>
                    'F001-900',
                'invoice_amount' =>
                    1200,
                'invoice_due_date' =>
                    '2026-09-01',
                'currency' => 'PEN',
                'includes_tax' => true,
                'created_by' =>
                    $user->id,
            ]);

        $before =
            $order->fresh()
                ->getAttributes();

        $rule = AutomationRule::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Cobranza interna',
                'trigger_key' =>
                    'service.invoice_overdue',
                'action_key' =>
                    'service.create_collection_reminder',
                'mode' => 'automatic',
                'is_active' => true,
                'created_by' =>
                    $user->id,
            ]);

        $executor = app(
            AutomationRuleExecutor::class,
        );

        $first = $executor->runRule(
            $rule,
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(
            1,
            $first['executed'],
        );

        $this->assertSame(
            1,
            $user->notifications()
                ->count(),
        );

        $this->assertDatabaseHas(
            'automation_rule_runs',
            [
                'automation_rule_id' =>
                    $rule->id,
                'outcome' =>
                    'executed',
            ],
        );

        $this->assertSame(
            $before,
            $order->fresh()
                ->getAttributes(),
        );

        $second = $executor->runRule(
            $rule->fresh(),
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(
            1,
            $second['duplicates'],
        );

        $this->assertSame(
            1,
            $user->notifications()
                ->count(),
        );

        $this->assertSame(
            1,
            AutomationRuleRun::query()
                ->count(),
        );
    }

    public function test_confirmation_rule_only_creates_pending_confirmation_and_does_not_mutate_task(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-04 10:00:00',
        );

        [
            $user,
            $organization,
        ] = $this->context(
            false,
        );

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea vencida confirmación',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' =>
                '2026-09-03 17:00:00',
            'created_by' => $user->id,
        ]);

        $before =
            $task->fresh()
                ->getAttributes();

        $rule = AutomationRule::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Atención requiere confirmación',
                'trigger_key' =>
                    'task.overdue',
                'action_key' =>
                    'task.raise_attention',
                'mode' =>
                    'confirmation',
                'is_active' => true,
                'created_by' =>
                    $user->id,
            ]);

        $result = app(
            AutomationRuleExecutor::class,
        )->runRule(
            $rule,
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(
            1,
            $result[
                'pending_confirmation'
            ],
        );

        $this->assertSame(
            0,
            $user->notifications()
                ->count(),
        );

        $this->assertSame(
            $before,
            $task->fresh()
                ->getAttributes(),
        );

        $this->assertDatabaseHas(
            'automation_rule_runs',
            [
                'automation_rule_id' =>
                    $rule->id,
                'outcome' =>
                    'pending_confirmation',
            ],
        );
    }

    public function test_preview_rule_logs_preview_without_notification_or_subject_mutation(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-04 10:00:00',
        );

        [
            $user,
            $organization,
            $client,
        ] = $this->context();

        $order = ServiceOrder::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'client_id' =>
                    $client->id,
                'title' =>
                    'Conformidad preview',
                'stage' =>
                    'conformity',
                'currency' => 'PEN',
                'includes_tax' => true,
                'created_by' =>
                    $user->id,
            ]);

        $before =
            $order->fresh()
                ->getAttributes();

        $rule = AutomationRule::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Facturación preview',
                'trigger_key' =>
                    'service.conformity_ready',
                'action_key' =>
                    'service.create_billing_reminder',
                'mode' => 'preview',
                'is_active' => true,
                'created_by' =>
                    $user->id,
            ]);

        $result = app(
            AutomationRuleExecutor::class,
        )->runRule(
            $rule,
            100,
            CarbonImmutable::now(),
        );

        $this->assertSame(
            1,
            $result['previewed'],
        );

        $this->assertSame(
            0,
            $user->notifications()
                ->count(),
        );

        $this->assertSame(
            $before,
            $order->fresh()
                ->getAttributes(),
        );
    }

    private function context(
        bool $withClient = true,
    ): array {
        $user = User::factory()->create([
            'email' =>
                'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' =>
                    'arpynet-automation-execution',
                'category' =>
                    'company',
                'timezone' =>
                    'America/Lima',
                'is_active' => true,
                'created_by' =>
                    $user->id,
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

        if (! $withClient) {
            return [
                $user,
                $organization,
            ];
        }

        $client = Client::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Cliente Automation 2.13B',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return [
            $user,
            $organization,
            $client,
        ];
    }
}
