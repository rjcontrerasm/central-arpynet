<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyVisualGeometryTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_desktop_prioritizes_tasks_before_supporting_context(): void
    {
        [$user] = $this->context();

        $response = $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk();

        $response
            ->assertSee(
                'central-assets/pages/daily-ops.css?v=2.39.3',
                false,
            )
            ->assertSee(
                'aria-label="Contexto operativo"',
                false,
            );

        $css = (string) file_get_contents(
            public_path(
                'central-assets/pages/daily-ops.css',
            ),
        );

        $this->assertStringContainsString(
            '.two-column > aside',
            $css,
        );
        $this->assertStringNotContainsString(
            'order: -1',
            $css,
        );
        $this->assertStringContainsString(
            'margin-top: 24px',
            $css,
        );
        $this->assertStringContainsString(
            '.two-column > main > .section > .list',
            $css,
        );
        $this->assertStringContainsString(
            'repeat(2, minmax(0, 1fr))',
            $css,
        );
        $this->assertStringNotContainsString(
            'minmax(0, 1.35fr)',
            $css,
        );
    }

    public function test_daily_mobile_rules_remain_available(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee(
                'central-assets/pages/daily-ops.css?v=2.39.3',
                false,
            );

        $css = (string) file_get_contents(
            public_path(
                'central-assets/pages/daily-ops.css',
            ),
        );

        $this->assertStringContainsString(
            '@media (max-width: 719px)',
            $css,
        );
        $this->assertStringContainsString(
            'repeat(3, minmax(0, 1fr))',
            $css,
        );
        $this->assertStringContainsString(
            'min-width: 164px',
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
