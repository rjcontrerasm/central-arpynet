<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AutomationRunCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_manual_run_command_executes_only_active_safe_internal_rule(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-04 10:00:00',
        );

        $user = User::factory()->create([
            'email' =>
                'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' =>
                    'arpynet-automation-command-213b',
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

        $client = Client::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Cliente command 2.13B',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' =>
                $organization->id,
            'client_id' =>
                $client->id,
            'title' =>
                'Factura command vencida',
            'stage' => 'invoiced',
            'invoice_number' =>
                'F001-901',
            'invoice_amount' => 600,
            'invoice_due_date' =>
                '2026-09-01',
            'currency' => 'PEN',
            'includes_tax' => true,
            'created_by' => $user->id,
        ]);

        $rule = AutomationRule::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Cobranza command',
                'trigger_key' =>
                    'service.invoice_overdue',
                'action_key' =>
                    'service.create_collection_reminder',
                'mode' => 'automatic',
                'is_active' => true,
                'created_by' =>
                    $user->id,
            ]);

        $exitCode = Artisan::call(
            'central:automation-run',
            [
                '--rule' => $rule->id,
                '--limit' => 100,
            ],
        );

        $output = Artisan::output();

        $this->assertSame(
            0,
            $exitCode,
        );

        $this->assertStringContainsString(
            '"scheduler": false',
            $output,
        );

        $this->assertStringContainsString(
            '"external_channels": false',
            $output,
        );

        $this->assertStringContainsString(
            '"subject_mutations": false',
            $output,
        );

        $this->assertStringContainsString(
            '"executed": 1',
            $output,
        );

        $this->assertSame(
            1,
            $user->notifications()
                ->count(),
        );
    }

    public function test_manual_run_rejects_inactive_rule(): void
    {
        $user = User::factory()->create([
            'email' =>
                'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' =>
                    'arpynet-automation-inactive-213b',
                'category' =>
                    'company',
                'timezone' =>
                    'America/Lima',
                'is_active' => true,
                'created_by' =>
                    $user->id,
            ]);

        $rule = AutomationRule::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Regla inactiva',
                'trigger_key' =>
                    'task.overdue',
                'action_key' =>
                    'task.raise_attention',
                'mode' => 'preview',
                'is_active' => false,
                'created_by' =>
                    $user->id,
            ]);

        $exitCode = Artisan::call(
            'central:automation-run',
            [
                '--rule' =>
                    $rule->id,
            ],
        );

        $this->assertSame(
            1,
            $exitCode,
        );
    }
}
