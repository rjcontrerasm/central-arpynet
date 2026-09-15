<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SharedClientOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_client_creation_creates_shared_link(): void
    {
        [$user, $organization] = $this->context('owner');

        $this->actingAs($user);

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente legado',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('client_organization', [
            'client_id' => $client->id,
            'organization_id' => $organization->id,
            'is_active' => 1,
        ]);
    }

    public function test_front_can_share_one_client_across_two_writable_organizations(): void
    {
        [$user, $first] = $this->context('owner');
        $second = $this->organizationFor($user, 'Empresa B', 'empresa-b');

        $response = $this->actingAs($user)->post('/clientes', [
            'organization_ids' => [$first->id, $second->id],
            'scope' => $first->id,
            'name' => 'Cliente Compartido',
            'legal_name' => 'Cliente Compartido SAC',
            'tax_id' => '20999999991',
            'is_active' => '1',
        ]);

        $response->assertRedirect();

        $client = Client::query()
            ->where('name', 'Cliente Compartido')
            ->sole();

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('client_organization', [
            'client_id' => $client->id,
            'organization_id' => $first->id,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('client_organization', [
            'client_id' => $client->id,
            'organization_id' => $second->id,
            'is_active' => 1,
        ]);

        $this->actingAs($user)
            ->get('/clientes?scope='.$first->id)
            ->assertOk()
            ->assertSee('Cliente Compartido');

        $this->actingAs($user)
            ->get('/clientes?scope='.$second->id)
            ->assertOk()
            ->assertSee('Cliente Compartido');
    }

    public function test_foreign_user_cannot_see_shared_client(): void
    {
        [$owner, $first] = $this->context('owner');
        $second = $this->organizationFor($owner, 'Empresa B', 'empresa-b-hidden');

        $this->actingAs($owner);

        $client = Client::query()->create([
            'organization_id' => $first->id,
            'name' => 'Cliente restringido',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $client->organizations()->syncWithoutDetaching([
            $second->id => [
                'is_active' => true,
                'created_by' => $owner->id,
            ],
        ]);

        $foreign = User::factory()->create([
            'email' => 'foreign-shared-client@arpynet.test',
            'is_active' => true,
        ]);

        $foreignOrganization = $this->organizationFor(
            $foreign,
            'Empresa ajena',
            'empresa-ajena-shared-client',
        );

        $this->actingAs($foreign)
            ->get('/clientes?scope='.$foreignOrganization->id)
            ->assertOk()
            ->assertDontSee('Cliente restringido');
    }

    public function test_service_order_accepts_client_linked_to_secondary_organization(): void
    {
        [$user, $first] = $this->context('owner');
        $second = $this->organizationFor($user, 'Empresa B', 'empresa-b-service');

        $this->actingAs($user);

        $client = Client::query()->create([
            'organization_id' => $first->id,
            'name' => 'Cliente multiempresa',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $client->organizations()->syncWithoutDetaching([
            $second->id => [
                'is_active' => true,
                'created_by' => $user->id,
            ],
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $second->id,
            'client_id' => $client->id,
            'title' => 'Servicio desde Empresa B',
            'stage' => 'opportunity',
            'created_by' => $user->id,
        ]);

        $this->assertSame($second->id, $service->organization_id);
        $this->assertSame($client->id, $service->client_id);
    }

    public function test_service_order_rejects_client_not_linked_to_organization(): void
    {
        [$user, $first] = $this->context('owner');
        $second = $this->organizationFor($user, 'Empresa B', 'empresa-b-reject');

        $this->actingAs($user);

        $client = Client::query()->create([
            'organization_id' => $first->id,
            'name' => 'Cliente solo Empresa A',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->expectException(ValidationException::class);

        ServiceOrder::query()->create([
            'organization_id' => $second->id,
            'client_id' => $client->id,
            'title' => 'Servicio inválido',
            'stage' => 'opportunity',
            'created_by' => $user->id,
        ]);
    }

    public function test_front_cannot_detach_organization_with_existing_service(): void
    {
        [$user, $first] = $this->context('owner');
        $second = $this->organizationFor($user, 'Empresa B', 'empresa-b-detach');

        $this->actingAs($user);

        $client = Client::query()->create([
            'organization_id' => $first->id,
            'name' => 'Cliente con servicio',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $client->organizations()->syncWithoutDetaching([
            $second->id => [
                'is_active' => true,
                'created_by' => $user->id,
            ],
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $second->id,
            'client_id' => $client->id,
            'title' => 'Servicio vigente',
            'stage' => 'execution',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->from('/clientes?client='.$client->id)
            ->post('/clientes/'.$client->id.'/actualizar', [
                'organization_ids' => [$first->id],
                'name' => 'Cliente con servicio',
                'is_active' => '1',
            ]);

        $response
            ->assertRedirect('/clientes?client='.$client->id)
            ->assertSessionHasErrors('organization_ids');

        $this->assertTrue(
            $client->fresh()->isLinkedToOrganization($second->id),
        );
    }

    public function test_legacy_update_does_not_remove_existing_shared_links(): void
    {
        [$user, $first] = $this->context('owner');
        $second = $this->organizationFor($user, 'Empresa B', 'empresa-b-legacy');

        $this->actingAs($user);

        $client = Client::query()->create([
            'organization_id' => $first->id,
            'name' => 'Cliente legado compartido',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $client->organizations()->syncWithoutDetaching([
            $second->id => [
                'is_active' => true,
                'created_by' => $user->id,
            ],
        ]);

        $this->actingAs($user)
            ->post('/clientes/'.$client->id.'/actualizar', [
                'organization_id' => $first->id,
                'name' => 'Cliente legado actualizado',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $client->refresh();

        $this->assertSame('Cliente legado actualizado', $client->name);
        $this->assertTrue($client->isLinkedToOrganization($first->id));
        $this->assertTrue($client->isLinkedToOrganization($second->id));
    }

    private function context(string $role): array
    {
        $user = User::factory()->create([
            'email' => $role.'-shared-client@arpynet.test',
            'is_active' => true,
        ]);

        $organization = $this->organizationFor(
            $user,
            'Empresa A',
            'empresa-a-'.$role,
            $role,
        );

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        return [$user, $organization];
    }

    private function organizationFor(
        User $user,
        string $name,
        string $slug,
        string $role = 'owner',
    ): Organization {
        $organization = Organization::query()->create([
            'name' => $name,
            'slug' => $slug,
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'is_default' => false,
                'is_active' => true,
            ],
        ]);

        return $organization;
    }
}
