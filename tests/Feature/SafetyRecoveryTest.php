<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\GoogleCalendarConnection;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafetyRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_recovery_center_requires_authentication(): void
    {
        $this->get(route('safety-recovery.index'))
            ->assertRedirect(route('login'));
    }

    public function test_recovery_center_shows_sanitized_operational_failure(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');
        [$user, $organization] = $this->context();
        $rule = $this->rule($user, $organization);

        AutomationRuleRun::query()->create([
            'automation_rule_id' => $rule->id,
            'organization_id' => $organization->id,
            'subject_type' => 'task',
            'subject_id' => 10,
            'fingerprint' => hash('sha256', 'recovery-failed'),
            'outcome' => 'failed',
            'payload' => [
                'title' => 'Cobranza que requiere revisión',
            ],
            'evaluated_at' => now(),
            'error' => 'SECRET_TOKEN=must-never-render',
        ]);

        $response = $this->actingAs($user)
            ->get(route('safety-recovery.index'));

        $response->assertOk()
            ->assertSee('Estado y recuperación')
            ->assertSee('Cobranza que requiere revisión')
            ->assertSee('detalle técnico interno')
            ->assertDontSee('SECRET_TOKEN')
            ->assertDontSee('must-never-render');
    }

    public function test_recovery_center_never_leaks_other_organization_runs(): void
    {
        [$user, $organization] = $this->context();
        $foreignUser = User::factory()->create();
        $foreign = Organization::query()->create([
            'name' => 'Otra organización',
            'slug' => 'foreign-recovery',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $foreignUser->id,
        ]);
        $foreign->users()->attach($foreignUser->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $ownRule = $this->rule($user, $organization);
        $foreignRule = $this->rule($foreignUser, $foreign, 'Foreign rule');

        AutomationRuleRun::query()->create([
            'automation_rule_id' => $ownRule->id,
            'organization_id' => $organization->id,
            'subject_type' => 'task',
            'subject_id' => 11,
            'fingerprint' => hash('sha256', 'own-run'),
            'outcome' => 'blocked',
            'payload' => ['title' => 'Visible para ARPYNET'],
            'evaluated_at' => now(),
        ]);

        AutomationRuleRun::query()->create([
            'automation_rule_id' => $foreignRule->id,
            'organization_id' => $foreign->id,
            'subject_type' => 'task',
            'subject_id' => 12,
            'fingerprint' => hash('sha256', 'foreign-run'),
            'outcome' => 'failed',
            'payload' => ['title' => 'NO DEBE VERSE'],
            'evaluated_at' => now(),
            'error' => 'foreign secret',
        ]);

        $this->actingAs($user)
            ->get(route('safety-recovery.index'))
            ->assertOk()
            ->assertSee('Visible para ARPYNET')
            ->assertDontSee('NO DEBE VERSE')
            ->assertDontSee('foreign secret');
    }

    public function test_calendar_failure_is_visible_without_error_details(): void
    {
        [$user] = $this->context();

        GoogleCalendarConnection::query()->create([
            'user_id' => $user->id,
            'calendar_id' => 'primary',
            'calendar_summary' => 'Principal',
            'token_data' => ['access_token' => 'encrypted-by-cast'],
            'scopes' => ['calendar'],
            'connected_at' => now()->subDay(),
            'last_sync_at' => now()->subHour(),
            'last_error_at' => now(),
            'last_error' => 'oauth-secret-detail',
        ]);

        $this->actingAs($user)
            ->get(route('safety-recovery.index'))
            ->assertOk()
            ->assertSee('Google Calendar requiere revisión')
            ->assertDontSee('oauth-secret-detail');
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'recovery@arpynet.com',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-recovery',
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

        return [$user, $organization];
    }

    private function rule(
        User $user,
        Organization $organization,
        string $name = 'Recovery rule',
    ): AutomationRule {
        return AutomationRule::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'trigger_key' => 'task.overdue',
            'action_key' => 'task.raise_attention',
            'mode' => 'preview',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
    }
}
