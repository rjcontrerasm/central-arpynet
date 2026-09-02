<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisualSystemConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_pages_share_same_outer_desktop_width_and_theme(): void
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
                    'width: min(100%, 1200px)',
                    false,
                )
                ->assertSee(
                    '--central-primary: #2563eb',
                    false,
                );
        }
    }

    public function test_specialized_pages_keep_intentional_inner_widths(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/captura')
            ->assertOk()
            ->assertSee(
                'width: min(100%, 760px)',
                false,
            );

        $this->actingAs($user)
            ->get('/notificaciones')
            ->assertOk()
            ->assertSee(
                'width: min(100%, 860px)',
                false,
            );
    }

    public function test_operational_lists_use_stable_two_column_geometry_on_desktop(): void
    {
        [$user] = $this->context();

        foreach ([
            '/servicios',
            '/vencimientos',
            '/seguimiento',
        ] as $page) {
            $response = $this->actingAs($user)
                ->get($page)
                ->assertOk()
                ->assertSee(
                    'repeat(2, minmax(0, 1fr))',
                    false,
                );

            $this->assertStringNotContainsString(
                'auto-fit',
                $response->getContent(),
            );
        }
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
