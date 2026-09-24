<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\ServiceOrders\ServiceOrderResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Client;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_and_admin_can_access_advanced_admin(): void
    {
        $organization = $this->organization('Admin roles', 'admin-roles');
        $users = [];

        foreach (['owner', 'admin', 'member', 'viewer'] as $role) {
            $user = User::factory()->create([
                'is_active' => true,
            ]);

            $organization->users()->attach($user->id, [
                'role' => $role,
                'is_default' => true,
                'is_active' => true,
            ]);

            $users[$role] = $user;
        }

        $panel = Filament::getPanel('admin');

        $this->assertTrue(
            $users['owner']->canAccessPanel($panel),
        );
        $this->assertTrue(
            $users['admin']->canAccessPanel($panel),
        );
        $this->assertFalse(
            $users['member']->canAccessPanel($panel),
        );
        $this->assertFalse(
            $users['viewer']->canAccessPanel($panel),
        );
    }

    public function test_admin_resources_ignore_viewer_only_organizations(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $managed = $this->organization(
            'Empresa administrada',
            'empresa-administrada',
            $user,
        );
        $viewerOnly = $this->organization(
            'Empresa solo lectura',
            'empresa-solo-lectura',
            $user,
            'viewer',
        );

        $managedTask = Task::withoutEvents(
            fn () => Task::query()->create([
                'organization_id' => $managed->id,
                'title' => 'Tarea administrable',
                'status' => 'pending',
                'urgency' => 'normal',
                'impact' => 'normal',
                'source' => 'manual',
            ]),
        );

        Task::withoutEvents(
            fn () => Task::query()->create([
                'organization_id' => $viewerOnly->id,
                'title' => 'Tarea solo lectura',
                'status' => 'pending',
                'urgency' => 'normal',
                'impact' => 'normal',
                'source' => 'manual',
            ]),
        );

        $this->actingAs($user);

        $this->assertSame(
            [$managed->id],
            OrganizationResource::getEloquentQuery()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );

        $this->assertSame(
            [$managedTask->id],
            TaskResource::getEloquentQuery()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );
    }

    public function test_shared_client_hides_unmanaged_links_in_admin_and_front(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $managed = $this->organization(
            'Empresa visible',
            'empresa-visible',
            $user,
        );
        $hidden = $this->organization(
            'Empresa confidencial externa',
            'empresa-confidencial-externa',
        );

        $shared = $this->client(
            'Cliente compartido seguro',
            $managed,
            $user,
            [$managed, $hidden],
        );
        $managedOnly = $this->client(
            'Cliente administrable',
            $managed,
            $user,
            [$managed],
        );
        $this->client(
            'Cliente externo oculto',
            $hidden,
            $user,
            [$hidden],
        );

        $this->actingAs($user);

        $adminClients = ClientResource::getEloquentQuery()->get();

        $this->assertEqualsCanonicalizing(
            [$shared->id, $managedOnly->id],
            $adminClients->pluck('id')->all(),
        );

        $adminShared = $adminClients->firstWhere('id', $shared->id);

        $this->assertNotNull($adminShared);
        $this->assertSame(
            [$managed->id],
            $adminShared->organizations
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );
        $this->assertFalse(ClientResource::canEdit($shared));
        $this->assertTrue(ClientResource::canEdit($managedOnly));

        $this->get(route('client-ops.index', [
            'client' => $shared->id,
        ]))
            ->assertOk()
            ->assertSee('Cliente compartido seguro')
            ->assertSee('Empresa visible')
            ->assertDontSee('Empresa confidencial externa');

        $serviceOptions =
            ServiceOrderResource::clientOptionsForOrganization(
                $managed->id,
            );

        $this->assertArrayHasKey($shared->id, $serviceOptions);
        $this->assertArrayHasKey($managedOnly->id, $serviceOptions);
        $this->assertSame(
            [],
            ServiceOrderResource::clientOptionsForOrganization(
                $hidden->id,
            ),
        );

        $incidentOptions =
            IncidentResource::clientOptionsForOrganization(
                $managed->id,
            );

        $this->assertArrayHasKey($shared->id, $incidentOptions);
        $this->assertArrayHasKey($managedOnly->id, $incidentOptions);
    }

    public function test_incident_accepts_shared_client_in_secondary_organization(): void
    {
        $creator = User::factory()->create([
            'is_active' => true,
        ]);
        $first = $this->organization(
            'Empresa origen',
            'empresa-origen',
            $creator,
        );
        $secondOwner = User::factory()->create([
            'is_active' => true,
        ]);
        $second = $this->organization(
            'Empresa secundaria',
            'empresa-secundaria',
            $secondOwner,
        );

        $client = $this->client(
            'Cliente multisociedad',
            $first,
            $creator,
            [$first, $second],
        );

        $this->actingAs($secondOwner);

        $incident = Incident::query()->create([
            'organization_id' => $second->id,
            'client_id' => $client->id,
            'title' => 'Incidente en empresa secundaria',
            'severity' => 'high',
            'status' => 'new',
            'category' => 'availability',
        ]);

        $this->assertSame(
            $client->id,
            (int) $incident->client_id,
        );
        $this->assertSame(
            $second->id,
            (int) $incident->organization_id,
        );
    }

    private function organization(
        string $name,
        string $slug,
        ?User $user = null,
        string $role = 'owner',
    ): Organization {
        $organization = Organization::query()->create([
            'name' => $name,
            'slug' => $slug,
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user?->id,
        ]);

        if ($user) {
            $organization->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => $role,
                    'is_default' => true,
                    'is_active' => true,
                ],
            ]);

            if ($role === 'owner') {
                $user->forceFill([
                    'current_organization_id' => $organization->id,
                ])->save();
            }
        }

        return $organization;
    }

    private function client(
        string $name,
        Organization $legacyOrganization,
        User $creator,
        array $organizations,
    ): Client {
        $client = Client::withoutEvents(
            fn () => Client::query()->create([
                'organization_id' => $legacyOrganization->id,
                'name' => $name,
                'is_active' => true,
                'created_by' => $creator->id,
            ]),
        );

        foreach ($organizations as $organization) {
            $client->organizations()->attach(
                $organization->id,
                [
                    'is_active' => true,
                    'created_by' => $creator->id,
                ],
            );
        }

        return $client;
    }
}
