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
            $response = $this->actingAs($user)
                ->get($page)
                ->assertOk();

            $css = $this->compactCss($response->getContent());

            $this->assertStringContainsString(
                'width:min(100%,1200px)',
                $css,
                'El shell operativo debe conservar el ancho desktop común en '.$page,
            );

            $this->assertStringContainsString(
                '--central-primary:#245fd7',
                $css,
                'La página debe cargar el tema operacional compartido en '.$page,
            );
        }
    }

    public function test_specialized_pages_keep_intentional_inner_widths(): void
    {
        [$user] = $this->context();

        $capture = $this->actingAs($user)
            ->get('/captura')
            ->assertOk();

        $this->assertStringContainsString(
            'width:min(100%,760px)',
            $this->compactCss($capture->getContent()),
        );

        $notifications = $this->actingAs($user)
            ->get('/notificaciones')
            ->assertOk();

        $this->assertStringContainsString(
            'width:min(100%,860px)',
            $this->compactCss($notifications->getContent()),
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
                ->assertOk();

            $css = $this->compactCss($response->getContent());

            $this->assertStringContainsString(
                'repeat(2,minmax(0,1fr))',
                $css,
                'La lista debe conservar dos columnas estables en '.$page,
            );

            $this->assertStringNotContainsString(
                'auto-fit',
                $response->getContent(),
            );
        }
    }

    private function compactCss(string $html): string
    {
        return preg_replace('/\s+/', '', $html) ?? $html;
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
