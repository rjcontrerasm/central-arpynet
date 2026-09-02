<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyVisualGeometryTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_desktop_uses_stable_context_and_task_grids(): void
    {
        [$user] = $this->context();

        $response = $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk();

        $response
            ->assertSee(
                '.two-column > aside',
                false,
            )
            ->assertSee(
                'order: -1',
                false,
            )
            ->assertSee(
                '.two-column > main > .section > .list',
                false,
            )
            ->assertSee(
                'repeat(2, minmax(0, 1fr))',
                false,
            )
            ->assertSee(
                'aria-label="Contexto operativo"',
                false,
            );

        $this->assertStringNotContainsString(
            'minmax(0, 1.35fr)',
            $response->getContent(),
        );
    }

    public function test_daily_mobile_rules_remain_available(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee(
                '@media (max-width: 719px)',
                false,
            )
            ->assertSee(
                'repeat(3, minmax(0, 1fr))',
                false,
            )
            ->assertSee(
                'min-width: 164px',
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
