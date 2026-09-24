<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\GoogleCalendarConnection;
use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\RecurringTaskRule;
use App\Models\User;
use App\Models\WhatsappOutboundAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SafetyRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        File::delete($this->heartbeatPath());
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

    public function test_scheduler_heartbeat_is_visible_without_database_state(): void
    {
        CarbonImmutable::setTestNow('2026-09-17 12:00:00');
        [$user] = $this->context();

        File::ensureDirectoryExists(
            dirname($this->heartbeatPath()),
        );
        File::put(
            $this->heartbeatPath(),
            "{\"recorded_at\":\"2026-09-17T12:00:00-05:00\"}\n",
        );
        touch(
            $this->heartbeatPath(),
            CarbonImmutable::now()->timestamp,
        );

        $this->actingAs($user)
            ->get(route('safety-recovery.index'))
            ->assertOk()
            ->assertSee('Scheduler activo')
            ->assertSee('Última señal')
            ->assertSee('0 min');
    }

    public function test_recovery_center_reports_missing_recurring_occurrence(): void
    {
        CarbonImmutable::setTestNow('2026-09-17 12:00:00');
        [$user, $organization] = $this->context();

        RecurringTaskRule::withoutEvents(
            fn () => RecurringTaskRule::query()->create([
                'organization_id' => $organization->id,
                'title' => 'Checklist sin generar',
                'frequency' => 'daily',
                'anchor_date' => '2026-09-17',
                'create_days_before' => 0,
                'due_time' => '18:00',
                'urgency' => 'normal',
                'impact' => 'medium',
                'is_private' => false,
                'is_active' => true,
                'assigned_to' => $user->id,
                'created_by' => $user->id,
            ]),
        );

        $this->actingAs($user)
            ->get(route('safety-recovery.index'))
            ->assertOk()
            ->assertSee('Checklist sin generar')
            ->assertSee('Sin generar')
            ->assertSee('Recurrencias sin generar');
    }

    public function test_recovery_center_reports_missing_recurring_obligation(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 10:00:00');
        [$user, $organization] = $this->context();

        RecurringObligation::withoutEvents(
            fn () => RecurringObligation::query()->create([
                'organization_id' => $organization->id,
                'name' => 'Licencia crítica sin ocurrencia',
                'category' => 'license',
                'frequency' => 'monthly',
                'anchor_date' => '2026-09-24',
                'expected_amount' => 100,
                'currency' => 'PEN',
                'reminder_days_before' => 7,
                'is_critical' => true,
                'is_active' => true,
                'created_by' => $user->id,
            ]),
        );

        $this->actingAs($user)
            ->get(route('safety-recovery.index'))
            ->assertOk()
            ->assertSee('Licencia crítica sin ocurrencia')
            ->assertSee('Vencimientos sin generar')
            ->assertSee('Vencimientos recurrentes que requieren revisión');
    }

    public function test_whatsapp_health_is_visible_without_sensitive_failure_details(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 10:00:00');
        [$user] = $this->context();

        config()->set('whatsapp.outbound_enabled', true);

        WhatsappOutboundAttempt::query()->create([
            'purpose' => 'critical_alert',
            'request_kind' => 'template',
            'recipient_sha256' => hash(
                'sha256',
                '51999999999',
            ),
            'status' => 'failed',
            'error_code' => '131000',
            'error_message' => 'SECRET_PROVIDER_DETAIL',
        ]);

        $this->actingAs($user)
            ->get(route('safety-recovery.index'))
            ->assertOk()
            ->assertSee('Último envío WhatsApp falló')
            ->assertSee('Fallas WhatsApp 24 h')
            ->assertDontSee('51999999999')
            ->assertDontSee('SECRET_PROVIDER_DETAIL');
    }

    public function test_automation_health_exposes_counts_without_internal_error(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 10:00:00');
        [$user, $organization] = $this->context();
        $rule = $this->rule($user, $organization);

        AutomationRuleRun::query()->create([
            'automation_rule_id' => $rule->id,
            'organization_id' => $organization->id,
            'subject_type' => 'task',
            'subject_id' => 45,
            'fingerprint' => hash(
                'sha256',
                'observability-run',
            ),
            'outcome' => 'failed',
            'payload' => [
                'title' => 'Revisión observable',
            ],
            'evaluated_at' => now(),
            'error' => 'PRIVATE_AUTOMATION_ERROR',
        ]);

        $this->actingAs($user)
            ->get(route('safety-recovery.index'))
            ->assertOk()
            ->assertSee('Automatizaciones con fallas recientes')
            ->assertSee('Ejecuciones 24 h')
            ->assertDontSee('PRIVATE_AUTOMATION_ERROR');
    }

    public function test_scheduler_heartbeat_command_creates_filesystem_signal(): void
    {
        File::delete($this->heartbeatPath());

        $this->artisan('central:scheduler-heartbeat')
            ->assertSuccessful();

        $this->assertFileExists($this->heartbeatPath());
        $this->assertStringContainsString(
            'recorded_at',
            File::get($this->heartbeatPath()),
        );
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

    private function heartbeatPath(): string
    {
        return storage_path(
            'app/central/scheduler-heartbeat.json',
        );
    }
}
