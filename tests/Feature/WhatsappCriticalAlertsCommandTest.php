<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappCriticalAlertsCommandTest extends TestCase
{
    use RefreshDatabase;
    private const WA_ID = '51999999999';

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_command_is_disabled_by_default(): void
    {
        config()->set('central.critical_whatsapp.enabled', false);
        Http::fake();
        $this->assertSame(0, Artisan::call('alerts:whatsapp-critical'));
        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_critical_alert_states', 0);
    }

    public function test_critical_task_sends_template(): void
    {
        $this->enableChannel();
        [$user, $organization] = $this->context();
        $this->criticalTask($user, $organization);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.critical.1']]], 200)]);
        $this->assertSame(0, Artisan::call('alerts:whatsapp-critical'));
        $this->assertDatabaseHas('whatsapp_critical_alert_states', [
            'user_id' => $user->id,
            'subject_type' => 'task',
            'last_level' => 'critical',
            'last_message_id' => 'wamid.critical.1',
            'sent_count' => 1,
            'failed_count' => 0,
        ]);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request['type'] === 'template'
                && data_get($request->data(), 'template.name') === 'central_critical_alert'
                && data_get($request->data(), 'template.language.code') === 'es_PE'
                && count(data_get($request->data(), 'template.components.0.parameters', [])) === 6;
        });
    }

    public function test_same_state_is_deduplicated_inside_cooldown(): void
    {
        $this->enableChannel();
        [$user, $organization] = $this->context();
        $this->criticalTask($user, $organization);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.critical.1']]], 200)]);
        $this->assertSame(0, Artisan::call('alerts:whatsapp-critical'));
        $this->assertSame(0, Artisan::call('alerts:whatsapp-critical'));
        Http::assertSentCount(1);
        $this->assertDatabaseHas('whatsapp_critical_alert_states', ['sent_count' => 1, 'failed_count' => 0]);
    }

    public function test_resolved_item_is_marked_resolved(): void
    {
        $this->enableChannel();
        [$user, $organization] = $this->context();
        $task = $this->criticalTask($user, $organization);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.critical.1']]], 200)]);
        Artisan::call('alerts:whatsapp-critical');
        $task->forceFill(['status' => 'completed'])->save();
        $this->assertSame(0, Artisan::call('alerts:whatsapp-critical'));
        $this->assertDatabaseHas('whatsapp_critical_alert_states', [
            'subject_type' => 'task',
            'subject_id' => $task->id,
            'last_level' => 'resolved',
        ]);
        Http::assertSentCount(1);
    }

    private function enableChannel(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 12:00:00', 'America/Lima'));
        config()->set('central.critical_whatsapp.enabled', true);
        config()->set('central.critical_whatsapp.user_email', 'rcontreras@arpynet.com');
        config()->set('central.critical_whatsapp.template', 'central_critical_alert');
        config()->set('central.critical_whatsapp.language', 'es_PE');
        config()->set('central.critical_whatsapp.cooldown_minutes', 360);
        config()->set('central.critical_whatsapp.retry_minutes', 60);
        config()->set('central.critical_whatsapp.max_items', 5);
        config()->set('whatsapp.allowed_wa_ids', [self::WA_ID]);
        config()->set('whatsapp.outbound_enabled', true);
        config()->set('whatsapp.access_token', 'test-access-token');
        config()->set('whatsapp.phone_number_id', 'phone-test');
        config()->set('whatsapp.graph_version', 'v26.0');
    }

    private function context(): array
    {
        $user = User::factory()->create(['email' => 'rcontreras@arpynet.com']);
        $organization = Organization::query()->create([
            'name' => 'ARPYNET', 'slug' => 'arpynet', 'category' => 'company',
            'timezone' => 'America/Lima', 'is_active' => true, 'created_by' => $user->id,
        ]);
        $organization->users()->attach($user->id, ['role' => 'owner', 'is_default' => true, 'is_active' => true]);
        $user->forceFill(['current_organization_id' => $organization->id])->save();
        return [$user, $organization];
    }

    private function criticalTask(User $user, Organization $organization): Task
    {
        $task = new Task();
        $task->forceFill([
            'organization_id' => $organization->id,
            'title' => 'Renovar certificado crítico',
            'status' => 'pending', 'urgency' => 'critical', 'impact' => 'high',
            'priority_score' => 100, 'priority_band' => 'critical',
            'due_at' => CarbonImmutable::now('America/Lima')->subDay(),
            'source' => 'manual', 'assigned_to' => $user->id, 'created_by' => $user->id,
        ])->save();
        return $task;
    }
}
