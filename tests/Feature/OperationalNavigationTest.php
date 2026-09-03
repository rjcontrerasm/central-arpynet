<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_pages_share_navigation(): void
    {
        [$user] = $this->context();

        $pages = [
            '/mi-dia',
            '/captura',
            '/servicios',
            '/vencimientos',
            '/seguimiento',
            '/revision-diaria',
            '/decisiones',
            '/resumen',
            '/notificaciones',
            '/historial',
        ];

        foreach ($pages as $page) {
            $this->actingAs($user)
                ->get($page)
                ->assertOk()
                ->assertSee(
                    'data-operational-nav',
                    false,
                )
                ->assertSee('Mi día')
                ->assertSee('Captura')
                ->assertSee('Panel administrativo');
        }
    }

    public function test_mobile_navigation_exposes_compact_more_menu(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Más')
            ->assertSee('Seguimiento')
            ->assertSee('Revisión diaria')
            ->assertSee('Decisiones')
            ->assertSee('Resumen')
            ->assertSee('Historial');
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
