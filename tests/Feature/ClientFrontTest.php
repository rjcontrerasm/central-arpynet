<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_front_requires_authentication(): void
    {
        $this->get('/clientes')->assertRedirect('/login');
    }

    public function test_member_can_create_and_update_client_without_admin(): void
    {
        [$user, $organization] = $this->context('member');

        $create = $this->actingAs($user)->post('/clientes', [
            'organization_id' => $organization->id,
            'name' => 'Cliente FRONT',
            'legal_name' => 'Cliente FRONT SAC',
            'tax_id' => '20123456789',
            'contact_name' => 'Contacto Operativo',
            'email' => 'cliente@example.test',
            'phone' => '999111222',
            'drive_url' => 'https://drive.google.com/example',
            'notes' => 'Cliente gestionado desde el front.',
            'is_active' => '1',
        ]);

        $create->assertRedirect();

        $client = Client::query()
            ->where('name', 'Cliente FRONT')
            ->firstOrFail();

        $this->assertSame($organization->id, $client->organization_id);
        $this->assertSame('20123456789', $client->tax_id);
        $this->assertTrue($client->is_active);

        $update = $this->actingAs($user)->post(
            '/clientes/'.$client->id.'/actualizar',
            [
                'organization_id' => $organization->id,
                'name' => 'Cliente FRONT actualizado',
                'legal_name' => 'Cliente FRONT SAC',
                'tax_id' => '20123456789',
                'contact_name' => 'Nuevo contacto',
                'email' => 'nuevo@example.test',
                'phone' => '999333444',
                'drive_url' => 'https://drive.google.com/example',
                'notes' => 'Actualizado desde FRONT.',
                'is_active' => '1',
            ],
        );

        $update->assertRedirect();

        $client->refresh();
        $this->assertSame('Cliente FRONT actualizado', $client->name);
        $this->assertSame('Nuevo contacto', $client->contact_name);

        $this->actingAs($user)
            ->get('/clientes?client='.$client->id)
            ->assertOk()
            ->assertSee('Cliente FRONT actualizado')
            ->assertSee('Guardar cambios')
            ->assertDontSee('/admin/clientes', false);
    }

    public function test_viewer_can_read_clients_but_has_no_write_controls(): void
    {
        [$owner, $organization] = $this->context('owner');

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente solo lectura',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $viewer = User::factory()->create([
            'email' => 'viewer-client-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/clientes?client='.$client->id)
            ->assertOk()
            ->assertSee('Cliente solo lectura')
            ->assertSee('solo lectura')
            ->assertDontSee('Guardar cambios');

        $this->actingAs($viewer)
            ->post('/clientes', [
                'organization_id' => $organization->id,
                'name' => 'No permitido',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/clientes/'.$client->id.'/actualizar', [
                'organization_id' => $organization->id,
                'name' => 'Intento editar',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertSame('Cliente solo lectura', $client->fresh()->name);
    }

    public function test_clients_front_respects_organization_isolation_and_blocks_move(): void
    {
        [$user, $organization] = $this->context('owner');

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente visible',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $second = Organization::query()->create([
            'name' => 'Segundo ámbito',
            'slug' => 'client-front-second',
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

        $move = $this->actingAs($user)
            ->from('/clientes?client='.$client->id)
            ->post('/clientes/'.$client->id.'/actualizar', [
                'organization_id' => $second->id,
                'name' => 'Cliente visible',
                'is_active' => '1',
            ]);

        $move
            ->assertRedirect('/clientes?client='.$client->id)
            ->assertSessionHasErrors('organization_id');

        $this->assertSame($organization->id, $client->fresh()->organization_id);

        $foreignUser = User::factory()->create([
            'email' => 'foreign-client-front@arpynet.test',
            'is_active' => true,
        ]);

        $this->actingAs($foreignUser);

        $foreign = Organization::query()->create([
            'name' => 'Ámbito ajeno',
            'slug' => 'client-front-foreign',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $foreignUser->id,
        ]);

        Client::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'Cliente oculto',
            'is_active' => true,
            'created_by' => $foreignUser->id,
        ]);

        $this->actingAs($user)
            ->get('/clientes')
            ->assertOk()
            ->assertSee('Cliente visible')
            ->assertDontSee('Cliente oculto');
    }

    private function context(string $role): array
    {
        $user = User::factory()->create([
            'email' => $role.'-client-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Clientes FRONT',
            'slug' => 'client-front-'.$role,
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
