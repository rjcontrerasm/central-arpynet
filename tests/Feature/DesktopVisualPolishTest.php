<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopVisualPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_navigation_does_not_duplicate_current_page_in_more_label(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/servicios')
            ->assertOk()
            ->assertDontSee('Más · Servicios')
            ->assertSee('Más');

        $this->actingAs($user)
            ->get('/vencimientos')
            ->assertOk()
            ->assertDontSee('Más · Vencimientos')
            ->assertSee('Más');
    }

    public function test_tracking_filters_have_clear_groups(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/seguimiento')
            ->assertOk()
            ->assertSee('Ámbito')
            ->assertSee('Estado')
            ->assertSee('Módulo')
            ->assertSee('Todos los módulos');
    }

    public function test_service_and_obligation_filters_have_clear_groups(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/servicios')
            ->assertOk()
            ->assertSee('Ámbito')
            ->assertSee('Estado y etapa')
            ->assertSee('Finanzas');

        $this->actingAs($user)
            ->get('/vencimientos')
            ->assertOk()
            ->assertSee('Ámbito')
            ->assertSee('Estado');
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' => 'arpynet',
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
