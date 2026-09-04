<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AutomationCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_command_exposes_safe_contract(): void
    {
        $exitCode = Artisan::call('central:automation-catalog');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"execution_enabled": false', $output);
        $this->assertStringContainsString('"scheduler_enabled": false', $output);
        $this->assertStringContainsString('"preview_read_only": true', $output);
    }

    public function test_preview_command_does_not_write_runs(): void
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-automation-command',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea preview command',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $rule = AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Regla preview command',
            'trigger_key' => 'task.overdue',
            'action_key' => 'task.raise_attention',
            'mode' => 'preview',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $exitCode = Artisan::call(
            'central:automation-preview',
            ['--rule' => $rule->id],
        );

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"preview_only": true', $output);
        $this->assertStringContainsString('"writes": 0', $output);
        $this->assertStringContainsString('"matches": 1', $output);
        $this->assertDatabaseCount('automation_rule_runs', 0);
    }
}
