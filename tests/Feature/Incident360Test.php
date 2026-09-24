<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use App\Support\Incident360State;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Incident360Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_state_prioritizes_critical_incident_with_breached_slas(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();

        $incident = $this->incident($user, $organization, [
            'title' => 'API crítica',
            'severity' => 'critical',
            'status' => 'new',
            'detected_at' => '2026-09-13 08:00:00',
            'response_due_at' => '2026-09-13 08:30:00',
            'resolution_due_at' => '2026-09-13 09:30:00',
            'next_action' => 'Revisar CloudTrail',
            'next_action_at' => '2026-09-13 09:50:00',
            'last_activity_at' => '2026-09-13 09:40:00',
        ]);

        $state = Incident360State::evaluate(
            $incident,
            CarbonImmutable::now(),
        );

        $this->assertTrue($state['active']);
        $this->assertSame(300, $state['rank']);
        $this->assertSame('breached', $state['response_sla']['status']);
        $this->assertSame('breached', $state['resolution_sla']['status']);
        $this->assertSame('overdue', $state['next_action']['status']);
        $this->assertSame(120, $state['open_minutes']);
        $this->assertSame('2 h', $state['open_duration']);
        $this->assertTrue($state['needs_attention']);
        $this->assertContains('SLA de respuesta vencido', $state['reasons']->all());
        $this->assertContains('SLA de resolución vencido', $state['reasons']->all());
    }

    public function test_resolved_incident_preserves_sla_result_and_timeline(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 12:00:00');

        [$user, $organization] = $this->context();

        $incident = $this->incident($user, $organization, [
            'title' => 'Incidente resuelto',
            'severity' => 'high',
            'status' => 'resolved',
            'detected_at' => '2026-09-13 08:00:00',
            'response_due_at' => '2026-09-13 08:15:00',
            'acknowledged_at' => '2026-09-13 08:10:00',
            'resolution_due_at' => '2026-09-13 09:00:00',
            'mitigated_at' => '2026-09-13 08:40:00',
            'resolved_at' => '2026-09-13 09:30:00',
        ]);

        $state = Incident360State::evaluate(
            $incident,
            CarbonImmutable::now(),
        );

        $this->assertTrue($state['terminal']);
        $this->assertSame(0, $state['rank']);
        $this->assertSame('met', $state['response_sla']['status']);
        $this->assertSame('breached', $state['resolution_sla']['status']);
        $this->assertSame(90, $state['open_minutes']);
        $this->assertSame(
            ['Detectado', 'Reconocido', 'Mitigado', 'Resuelto'],
            collect($state['timeline'])->pluck('label')->all(),
        );
    }

    public function test_incident_360_page_renders_priority_sla_and_context(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();

        $this->incident($user, $organization, [
            'title' => 'Incidente visible 360',
            'severity' => 'critical',
            'status' => 'new',
            'affected_service' => 'Portal de reservas',
            'external_id' => 'ALARM-001',
            'detected_at' => '2026-09-13 08:00:00',
            'response_due_at' => '2026-09-13 08:30:00',
            'resolution_due_at' => '2026-09-13 09:30:00',
            'next_action' => 'Validar origen',
            'next_action_at' => '2026-09-13 09:50:00',
        ]);

        $this->actingAs($user)
            ->get('/incidentes?focus=all')
            ->assertOk()
            ->assertSee('Incidentes 360')
            ->assertSee('Foco operativo')
            ->assertSee('Incidente visible 360')
            ->assertSee('SLA respuesta vencido')
            ->assertSee('SLA solución vencido')
            ->assertSee('Portal de reservas')
            ->assertSee('ALARM-001')
            ->assertSee('Validar origen');
    }

    public function test_incident_360_respects_organization_isolation(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();

        $this->incident($user, $organization, [
            'title' => 'Incidente visible',
        ]);

        $foreign = Organization::query()->create([
            'name' => 'Empresa ajena',
            'slug' => 'incident-foreign',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $foreignUser = User::factory()->create([
            'email' => 'foreign-incident@arpynet.test',
            'is_active' => true,
        ]);

        $foreign->users()->attach($foreignUser->id, [
            'role' => 'member',
            'is_default' => true,
            'is_active' => true,
        ]);

        $hidden = $this->incident($foreignUser, $foreign, [
            'title' => 'Incidente oculto',
            'severity' => 'critical',
        ]);

        $this->actingAs($user)
            ->get('/incidentes?focus=all')
            ->assertOk()
            ->assertSee('Incidente visible')
            ->assertDontSee('Incidente oculto');

        $this->actingAs($user)
            ->get('/incidentes?scope='.$foreign->id)
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/incidentes?focus=all&incident='.$hidden->id)
            ->assertNotFound();
    }

    private function incident(
        User $user,
        Organization $organization,
        array $overrides = [],
    ): Incident {
        return Incident::query()->create(array_merge([
            'organization_id' => $organization->id,
            'title' => 'Incidente de prueba',
            'category' => 'availability',
            'severity' => 'medium',
            'status' => 'new',
            'source' => 'manual',
            'detected_at' => CarbonImmutable::now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'last_activity_at' => CarbonImmutable::now(),
            'is_private' => false,
        ], $overrides));
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'incident-360',
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
}
