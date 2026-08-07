<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_critical_incident_is_flagged_and_milestones_are_synced(): void
    {
        Carbon::setTestNow('2026-08-06 23:30:00');

        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
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

        $this->actingAs($user);

        $incident = Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Servicio web no disponible',
            'severity' => 'critical',
            'status' => 'new',
            'category' => 'availability',
        ]);

        $this->assertSame(
            'Crítico abierto',
            $incident->attention_label,
        );

        $incident->update([
            'status' => 'resolved',
            'resolution_summary' => 'Servicio restablecido.',
        ]);

        $incident->refresh();

        $this->assertNotNull($incident->acknowledged_at);
        $this->assertNotNull($incident->mitigated_at);
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame(
            'Resuelto',
            $incident->attention_label,
        );
    }

    public function test_overdue_response_sla_has_priority(): void
    {
        Carbon::setTestNow('2026-08-06 23:30:00');

        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $incident = Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Alerta de prueba',
            'severity' => 'high',
            'status' => 'new',
            'response_due_at' => now()->subMinute(),
            'created_by' => $user->id,
        ]);

        $this->assertSame(
            'SLA respuesta vencido',
            $incident->attention_label,
        );
    }

    public function test_external_incident_identifier_is_unique_per_source_and_organization(): void
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Casa Andina',
            'slug' => 'casa-andina',
            'category' => 'employment',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Monitor',
            'source' => 'monitor',
            'external_id' => 'web-health-001',
        ]);

        $this->assertDatabaseCount('incidents', 1);

        $this->expectException(
            \Illuminate\Database\QueryException::class,
        );

        Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Monitor duplicado',
            'source' => 'monitor',
            'external_id' => 'web-health-001',
        ]);
    }

    public function test_client_must_belong_to_same_organization(): void
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organizationA = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organizationB = Organization::query()->create([
            'name' => 'Otra empresa',
            'slug' => 'otra-empresa',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $client = Client::query()->create([
            'organization_id' => $organizationB->id,
            'name' => 'Cliente externo',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->expectException(
            \Illuminate\Validation\ValidationException::class,
        );

        Incident::query()->create([
            'organization_id' => $organizationA->id,
            'client_id' => $client->id,
            'title' => 'Relación inválida',
        ]);
    }
}
