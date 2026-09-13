<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Incident360ActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_front_can_create_and_update_incident_without_admin(): void
    {
        [$user, $organization] = $this->context('member');

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente Incident 360',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $create = $this->actingAs($user)->post(
            '/incidentes',
            [
                'organization_id' => $organization->id,
                'title' => 'API degradada',
                'category' => 'availability',
                'severity' => 'high',
                'status' => 'new',
                'source' => 'manual',
                'assigned_to' => $user->id,
                'client_id' => $client->id,
                'affected_service' => 'API reservas',
                'description' => 'Latencia elevada.',
                'response_due_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'resolution_due_at' => now()->addHours(4)->format('Y-m-d H:i:s'),
                'next_action' => 'Revisar logs',
                'next_action_at' => now()->addMinutes(30)->format('Y-m-d H:i:s'),
            ],
        );

        $create->assertRedirect();

        $incident = Incident::query()
            ->where('title', 'API degradada')
            ->firstOrFail();

        $this->assertSame('high', $incident->severity);
        $this->assertSame('new', $incident->status);
        $this->assertSame($client->id, $incident->client_id);

        $update = $this->actingAs($user)->post(
            '/incidentes/'.$incident->id.'/actualizar',
            [
                'organization_id' => $organization->id,
                'title' => 'API degradada',
                'category' => 'availability',
                'severity' => 'critical',
                'status' => 'monitoring',
                'source' => 'manual',
                'assigned_to' => $user->id,
                'client_id' => $client->id,
                'affected_service' => 'API reservas',
                'description' => 'Latencia estabilizada.',
                'next_action' => 'Observar métricas',
                'next_action_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'root_cause' => 'Pool saturado',
                'resolution_summary' => 'Se amplió capacidad.',
            ],
        );

        $update->assertRedirect();

        $incident->refresh();

        $this->assertSame('critical', $incident->severity);
        $this->assertSame('monitoring', $incident->status);
        $this->assertSame('Pool saturado', $incident->root_cause);
        $this->assertNotNull($incident->acknowledged_at);
        $this->assertNotNull($incident->mitigated_at);

        $this->actingAs($user)
            ->get('/incidentes?focus=all&incident='.$incident->id)
            ->assertOk()
            ->assertSee('API degradada')
            ->assertSee('Editar incidente')
            ->assertDontSee('/admin/incidentes', false);
    }

    public function test_viewer_can_read_but_cannot_create_or_update_incidents(): void
    {
        [$owner, $organization] = $this->context('owner');

        $incident = Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Incidente visible',
            'category' => 'application',
            'severity' => 'medium',
            'status' => 'new',
            'source' => 'manual',
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
        ]);

        $viewer = User::factory()->create([
            'email' => 'viewer-incident@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/incidentes?focus=all')
            ->assertOk()
            ->assertSee('Incidente visible');

        $payload = [
            'organization_id' => $organization->id,
            'title' => 'No permitido',
            'category' => 'application',
            'severity' => 'medium',
            'status' => 'new',
            'source' => 'manual',
        ];

        $this->actingAs($viewer)
            ->post('/incidentes', $payload)
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/incidentes/'.$incident->id.'/actualizar', array_merge(
                $payload,
                ['title' => 'Intento editar'],
            ))
            ->assertForbidden();

        $this->assertSame(
            'Incidente visible',
            $incident->fresh()->title,
        );
    }

    public function test_cross_organization_relations_are_rejected(): void
    {
        [$user, $organization] = $this->context('member');

        $foreign = Organization::query()->create([
            'name' => 'Empresa ajena',
            'slug' => 'incident-foreign',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $foreignClient = Client::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'Cliente ajeno',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->from('/incidentes')
            ->post('/incidentes', [
                'organization_id' => $organization->id,
                'title' => 'Relación inválida',
                'category' => 'other',
                'severity' => 'low',
                'status' => 'new',
                'source' => 'manual',
                'client_id' => $foreignClient->id,
            ]);

        $response
            ->assertRedirect('/incidentes')
            ->assertSessionHasErrors('client_id');

        $this->assertDatabaseMissing('incidents', [
            'title' => 'Relación inválida',
        ]);
    }

    public function test_incident_front_respects_organization_visibility(): void
    {
        [$user, $organization] = $this->context('owner');

        Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Visible para usuario',
            'category' => 'security',
            'severity' => 'high',
            'status' => 'investigating',
            'source' => 'aws',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $foreign = Organization::query()->create([
            'name' => 'Oculta',
            'slug' => 'incident-hidden',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        Incident::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'No debe aparecer',
            'category' => 'security',
            'severity' => 'critical',
            'status' => 'new',
            'source' => 'aws',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/incidentes?focus=all')
            ->assertOk()
            ->assertSee('Visible para usuario')
            ->assertDontSee('No debe aparecer');
    }

    private function context(string $role): array
    {
        $user = User::factory()->create([
            'email' => $role.'-incident@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Incident',
            'slug' => 'incident-'.$role,
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => $role,
            'is_default' => true,
            'is_active' => true,
        ]);

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
