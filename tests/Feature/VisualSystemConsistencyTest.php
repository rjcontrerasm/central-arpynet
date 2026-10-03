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

        foreach ([
            '--central-primary:#024883',
            '--central-primary-hover:#004371',
            '--central-brand-orange:#f9a02c',
            '--central-bg:#eef4f9',
            '--central-font:Inter',
            '--central-radius-md:12px',
            '--central-radius-lg:15px',
            '--central-control-height:44px',
            '--central-icon-sm:16px',
            '--central-link:#024883',
        ] as $brandToken) {
            $this->assertStringContainsString(
                $brandToken,
                $sharedCss,
            );
        }

        foreach ([
            '/mi-dia' => ['daily-ops', null],
            '/captura' => ['quick-capture', null],
            '/servicios' => ['service-orders-ops', null],
            '/vencimientos' => ['obligations-ops', null],
            '/seguimiento' => ['global-tracking', null],
            '/resumen' => ['executive-summary', null],
            '/notificaciones' => ['notification-center', null],
            '/historial' => ['audit-history', null],
        ] as $page => [$asset, $version]) {
            $response = $this->actingAs($user)
                ->get($page)
                ->assertOk()
                ->assertSee(
                    'central-assets/operational.css?v=',
                    false,
                );

            $response->assertSee(
                $version === null
                    ? "central-assets/pages/{$asset}.css?v="
                    : "central-assets/pages/{$asset}.css?v={$version}",
                false,
            );

            $response
                ->assertSee('class="topbar"', false)
                ->assertSee('class="brand"', false)
                ->assertSee('class="hero"', false);

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

    public function test_mi_dia_visual_contract_exposes_common_controls_and_navigation(): void
    {
        $css = $this->compactCss(
            (string) file_get_contents(
                public_path('central-assets/operational.css'),
            ),
        );

        foreach ([
            '.topbar.op-nav-link.is-active',
            'inset0-2px0var(--central-brand-orange)',
            '.heroh1',
            'color:var(--central-brand-blue-dark)!important',
            '.primary-link',
            '.secondary-link',
            '.scope.active',
            '.priority-filter.active',
            '.chip.active',
        ] as $contractRule) {
            $this->assertStringContainsString(
                $contractRule,
                $css,
            );
        }
    }

    public function test_visual_hotfix_guards_known_regressions(): void
    {
        $shared = $this->compactCss(
            (string) file_get_contents(
                public_path('central-assets/operational.css'),
            ),
        );

        foreach ([
            'accent-color:var(--central-brand-blue)',
            '.topbar.op-nav-link.is-active',
            'background:transparent!important',
            '.topbar.op-nav-menua{min-height:34px',
            '.paginationsvg',
            'width:16px!important',
        ] as $rule) {
            $this->assertStringContainsString($rule, $shared);
        }

        $capture = $this->compactCss(
            (string) file_get_contents(
                public_path(
                    'central-assets/pages/quick-capture.css',
                ),
            ),
        );

        $this->assertStringContainsString(
            '.chips>.chip{min-height:0!important;padding:0!important;border:0!important',
            $capture,
        );

        $projects = $this->compactCss(
            (string) file_get_contents(
                public_path(
                    'central-assets/pages/projects-ops.css',
                ),
            ),
        );

        $this->assertStringContainsString(
            'details.quick{margin-top:12px',
            $projects,
        );
        $this->assertStringContainsString(
            'background:#024883!important',
            $projects,
        );

        $history = (string) file_get_contents(
            resource_path('views/audit-history.blade.php'),
        );

        $this->assertStringNotContainsString(
            '$changes->links()',
            $history,
        );
        $this->assertStringContainsString(
            'audit-pagination',
            $history,
        );

        foreach ([
            'clients-ops.css',
            'weekly-review.css',
            'decision-inbox.css',
            'agent-proposals.css',
        ] as $asset) {
            $css = $this->compactCss(
                (string) file_get_contents(
                    public_path(
                        'central-assets/pages/'.$asset,
                    ),
                ),
            );

            $this->assertStringContainsString(
                '#024883',
                $css,
                $asset.' debe usar azul ARPYNET.',
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
                'notification-center',
            ),
        );
    }

    public function test_operational_lists_use_stable_two_column_geometry_on_desktop(): void
    {
        [$user] = $this->context();

        foreach ([
            '/servicios' => 'service-orders-ops',
            '/vencimientos' => 'obligations-ops',
            '/seguimiento' => 'global-tracking',
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
