<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\Organization;
use App\Models\User;
use App\Support\AutomationRuleCatalog;
use App\Support\AutomationRuleExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationSchedulerSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_enables_scheduler_but_keeps_external_and_automatic_mutations_disabled(): void
    {
        $contract = app(
            AutomationRuleCatalog::class,
        )->contract();

        $this->assertTrue(
            $contract['scheduler_enabled'],
        );

        $this->assertFalse(
            $contract['external_channels'],
        );

        $this->assertFalse(
            $contract['subject_mutations_enabled'],
        );

        $this->assertTrue(
            $contract['confirmed_subject_mutations_enabled'],
        );

        $this->assertTrue(
            $contract['confirmation_execution_enabled'],
        );

        $this->assertSame(
            'database_notifications_only',
            $contract['automatic_execution_scope'],
        );
    }

    public function test_run_active_isolates_invalid_rule_failure(): void
    {
        [$user, $organization] = $this->context();

        AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Regla inválida aislada',
            'trigger_key' => 'invalid.trigger',
            'action_key' => 'invalid.action',
            'mode' => 'automatic',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $results = app(
            AutomationRuleExecutor::class,
        )->runActive();

        $this->assertCount(1, $results);
        $this->assertSame(
            1,
            $results->first()['failed'],
        );
        $this->assertArrayHasKey(
            'rule_error',
            $results->first(),
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'automation-scheduler-safety',
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
