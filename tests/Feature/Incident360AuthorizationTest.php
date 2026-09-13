<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Incident360AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_update_incident_from_foreign_organization(): void
    {
        [$user, $organization] = $this->context('local-owner', 'owner');
        [$foreignOwner, $foreign] = $this->context('foreign-owner', 'owner');

        $incident = Incident::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'Incidente ajeno',
            'category' => 'security',
            'severity' => 'high',
            'status' => 'new',
            'source' => 'manual',
            'assigned_to' => $foreignOwner->id,
            'created_by' => $foreignOwner->id,
        ]);

        $this->actingAs($user)
            ->post('/incidentes/'.$incident->id.'/actualizar', [
                'organization_id' => $foreign->id,
                'title' => 'Intento no autorizado',
                'category' => 'security',
                'severity' => 'critical',
                'status' => 'investigating',
                'source' => 'manual',
            ])
            ->assertForbidden();

        $incident->refresh();

        $this->assertSame('Incidente ajeno', $incident->title);
        $this->assertSame($foreign->id, $incident->organization_id);
        $this->assertNotSame($organization->id, $incident->organization_id);
    }

    public function test_existing_incident_cannot_be_moved_between_writable_organizations(): void
    {
        [$user, $first] = $this->context('multi-owner', 'owner');

        $second = Organization::query()->create([
            'name' => 'Segundo ámbito',
            'slug' => 'second-incident-scope',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $second->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => false,
            'is_active' => true,
        ]);

        $incident = Incident::query()->create([
            'organization_id' => $first->id,
            'title' => 'Permanece en ámbito',
            'category' => 'application',
            'severity' => 'medium',
            'status' => 'new',
            'source' => 'manual',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->from('/incidentes?focus=all&incident='.$incident->id)
            ->post('/incidentes/'.$incident->id.'/actualizar', [
                'organization_id' => $second->id,
                'title' => 'No debe moverse',
                'category' => 'application',
                'severity' => 'medium',
                'status' => 'investigating',
                'source' => 'manual',
            ]);

        $response
            ->assertRedirect('/incidentes?focus=all&incident='.$incident->id)
            ->assertSessionHasErrors('organization_id');

        $incident->refresh();

        $this->assertSame($first->id, $incident->organization_id);
        $this->assertSame('Permanece en ámbito', $incident->title);
        $this->assertSame('new', $incident->status);
    }

    public function test_viewer_does_not_receive_front_write_controls(): void
    {
        [$owner, $organization] = $this->context('controls-owner', 'owner');

        $incident = Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Solo lectura',
            'category' => 'other',
            'severity' => 'low',
            'status' => 'new',
            'source' => 'manual',
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
        ]);

        $viewer = User::factory()->create([
            'email' => 'incident-controls-viewer@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/incidentes?focus=all&incident='.$incident->id)
            ->assertOk()
            ->assertSee('Solo lectura')
            ->assertDontSee('+ Nuevo incidente')
            ->assertDontSee('Editar incidente');
    }

    private function context(string $slug, string $role): array
    {
        $user = User::factory()->create([
            'email' => $slug.'@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'Ámbito '.$slug,
            'slug' => $slug,
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
