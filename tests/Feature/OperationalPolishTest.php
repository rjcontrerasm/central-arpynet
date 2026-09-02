<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_pages_include_shared_interactions(): void
    {
        [$user] = $this->context();

        foreach ([
            '/mi-dia',
            '/captura',
            '/servicios',
            '/vencimientos',
            '/seguimiento',
            '/resumen',
            '/notificaciones',
            '/historial',
        ] as $page) {
            $this->actingAs($user)
                ->get($page)
                ->assertOk()
                ->assertSee(
                    'aria-busy',
                    false,
                )
                ->assertSee(
                    'data-operational-nav',
                    false,
                );
        }
    }

    public function test_capture_has_mobile_busy_feedback(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/captura')
            ->assertOk()
            ->assertSee(
                'Guardando tarea…',
            )
            ->assertSee(
                'prefers-reduced-motion',
                false,
            );
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
