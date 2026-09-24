<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_login(): void
    {
        $this->get('/buscar')
            ->assertRedirect('/login');
    }

    public function test_search_waits_for_two_characters(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/buscar?q=a')
            ->assertOk()
            ->assertSee(
                'Escribe al menos 2 caracteres',
            );
    }

    public function test_search_finds_operational_entities_in_visible_scope(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Alpha tarea',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Alpha proyecto',
            'type' => 'project',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Alpha cliente',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Alpha servicio',
            'stage' => 'opportunity',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        Incident::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Alpha incidente',
            'category' => 'other',
            'severity' => 'high',
            'status' => 'new',
            'source' => 'manual',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/buscar?q=Alpha')
            ->assertOk()
            ->assertSee('Alpha tarea')
            ->assertSee('Alpha proyecto')
            ->assertSee('Alpha cliente')
            ->assertSee('Alpha servicio')
            ->assertSee('Alpha incidente')
            ->assertSee('5 resultados visibles');
    }

    public function test_search_never_returns_foreign_scope_records(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa fuera de alcance',
            'slug' => 'empresa-fuera-search',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'Alpha secreto',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/buscar?q=Alpha')
            ->assertOk()
            ->assertDontSee('Alpha secreto');
    }

    public function test_shared_client_only_exposes_organization_names_user_can_access(): void
    {
        [$user, $organization] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa confidencial',
            'slug' => 'empresa-confidencial-search',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente Shared Search',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $client->organizations()->syncWithoutDetaching([
            $foreign->id => [
                'is_active' => true,
                'created_by' => $user->id,
            ],
        ]);

        $this->actingAs($user)
            ->get('/buscar?q=Shared')
            ->assertOk()
            ->assertSee('Cliente Shared Search')
            ->assertSee($organization->name)
            ->assertDontSee('Empresa confidencial');
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'global-search@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Search',
            'slug' => 'arpynet-search',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $user->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
