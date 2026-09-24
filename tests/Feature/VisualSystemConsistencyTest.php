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

        $sharedCss = $this->compactCss(
            (string) file_get_contents(
                public_path(
                    'central-assets/operational.css',
                ),
            ),
        );

        $this->assertStringContainsString(
            '--central-primary:#245fd7',
            $sharedCss,
        );

        foreach ([
            '/mi-dia' => 'daily-ops',
            '/captura' => 'quick-capture',
            '/servicios' => 'service-orders-ops',
            '/vencimientos' => null,
            '/seguimiento' => null,
            '/resumen' => null,
            '/notificaciones' => null,
            '/historial' => null,
        ] as $page => $asset) {
            $response = $this->actingAs($user)
                ->get($page)
                ->assertOk()
                ->assertSee(
                    'central-assets/operational.css?v=2.39.0',
                    false,
                );

            if ($asset !== null) {
                $response->assertSee(
                    "central-assets/pages/{$asset}.css?v=2.39.1",
                    false,
                );
            }

            $css = $this->pageCss(
                $response->getContent(),
                $asset,
            );

            $this->assertStringContainsString(
                'width:min(100%,1200px)',
                $css,
                'El shell operativo debe conservar el ancho desktop común en '.$page,
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
            $this->pageCss(
                $capture->getContent(),
                'quick-capture',
            ),
        );

        $notifications = $this->actingAs($user)
            ->get('/notificaciones')
            ->assertOk();

        $this->assertStringContainsString(
            'width:min(100%,860px)',
            $this->pageCss(
                $notifications->getContent(),
                null,
            ),
        );
    }

    public function test_operational_lists_use_stable_two_column_geometry_on_desktop(): void
    {
        [$user] = $this->context();

        foreach ([
            '/servicios' => 'service-orders-ops',
            '/vencimientos' => null,
            '/seguimiento' => null,
        ] as $page => $asset) {
            $response = $this->actingAs($user)
                ->get($page)
                ->assertOk();

            $css = $this->pageCss(
                $response->getContent(),
                $asset,
            );

            $this->assertStringContainsString(
                'repeat(2,minmax(0,1fr))',
                $css,
                'La lista debe conservar dos columnas estables en '.$page,
            );

            $this->assertStringNotContainsString(
                'auto-fit',
                $css,
            );
        }
    }

    private function pageCss(
        string $html,
        ?string $asset,
    ): string {
        if ($asset === null) {
            return $this->compactCss($html);
        }

        $contents = file_get_contents(
            public_path(
                "central-assets/pages/{$asset}.css",
            ),
        );

        $this->assertIsString($contents);

        return $this->compactCss($contents);
    }

    private function compactCss(string $css): string
    {
        return preg_replace('/\s+/', '', $css) ?? $css;
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
