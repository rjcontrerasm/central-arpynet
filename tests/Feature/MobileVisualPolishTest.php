<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileVisualPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_page_contains_mobile_kpi_and_compact_fab_rules(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee(
                'central-assets/pages/daily-ops.css?v=',
                false,
            );

        $css = (string) file_get_contents(
            public_path(
                'central-assets/pages/daily-ops.css',
            ),
        );

        $this->assertStringContainsString(
            'repeat(3, minmax(0, 1fr))',
            $css,
        );
        $this->assertStringContainsString(
            'min-width: 164px',
            $css,
        );
        $this->assertStringContainsString(
            'scrollbar-width: none',
            $css,
        );
    }

    public function test_capture_uses_non_overlapping_mobile_submit_button(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/captura')
            ->assertOk()
            ->assertSee(
                'central-assets/pages/quick-capture.css?v=2.39.1',
                false,
            )
            ->assertSee(
                'Guardando tarea…',
            );

        $css = (string) file_get_contents(
            public_path(
                'central-assets/pages/quick-capture.css',
            ),
        );

        $this->assertStringContainsString(
            'position: static',
            $css,
        );
        $this->assertStringContainsString(
            'min-height: 52px',
            $css,
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
