<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBulkOrganizationSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_form_renders_subtle_bulk_organization_selector(): void
    {
        $user = User::factory()->create([
            'email' => 'client-bulk-org-selector@arpynet.test',
            'is_active' => true,
        ]);

        $first = $this->organizationFor($user, 'Empresa A', 'bulk-org-a');
        $this->organizationFor($user, 'Empresa B', 'bulk-org-b');

        $user->forceFill([
            'current_organization_id' => $first->id,
        ])->save();

        $this->actingAs($user)
            ->get('/clientes')
            ->assertOk()
            ->assertSee('Empresas / ámbitos asociados')
            ->assertSee('Seleccionar todas')
            ->assertSee('data-org-selector', false)
            ->assertSee('data-org-toggle', false)
            ->assertSee('data-org-checks', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee(
                'central-assets/pages/clients-ops.js?v=2.39.1',
                false,
            );

        $javascript = file_get_contents(
            public_path(
                'central-assets/pages/clients-ops.js',
            ),
        );

        $this->assertIsString($javascript);
        $this->assertStringContainsString(
            "toggle.textContent = allSelected ? 'Limpiar' : 'Seleccionar todas';",
            $javascript,
        );
    }

    private function organizationFor(
        User $user,
        string $name,
        string $slug,
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
                'role' => 'owner',
                'is_default' => false,
                'is_active' => true,
            ],
        ]);

        return $organization;
    }
}
