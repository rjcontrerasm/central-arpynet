<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialOrganizationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_organizations_are_created_and_assigned(): void
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $this->seed(InitialOrganizationsSeeder::class);

        $this->assertDatabaseCount('organizations', 8);

        $this->assertDatabaseHas('organizations', [
            'slug' => 'arpynet',
            'tax_id' => '20600708067',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('organization_user', [
            'user_id' => $user->id,
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertNotNull(
            $user->fresh()->current_organization_id,
        );
    }

    public function test_root_redirects_to_admin(): void
    {
        $this->get('/')
            ->assertRedirect('/admin');
    }
}
