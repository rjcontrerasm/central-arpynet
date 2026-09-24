<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalStaticAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_shell_loads_shared_static_assets_once(): void
    {
        $user = User::factory()->create([
            'email' => 'assets-shell@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Assets',
            'slug' => 'arpynet-assets',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        $response = $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee(
                'central-assets/operational.css?v=2.39.0',
                false,
            )
            ->assertSee(
                'central-assets/operational.js?v=2.39.0',
                false,
            );

        $html = $response->getContent();

        $this->assertSame(
            1,
            substr_count(
                $html,
                'central-assets/operational.css?v=2.39.0',
            ),
        );

        $this->assertSame(
            1,
            substr_count(
                $html,
                'central-assets/operational.js?v=2.39.0',
            ),
        );
    }

    public function test_shared_operational_components_no_longer_embed_style_or_script_blocks(): void
    {
        foreach ([
            resource_path(
                'views/components/operational-theme.blade.php',
            ),
            resource_path(
                'views/components/operational-interactions.blade.php',
            ),
            resource_path(
                'views/components/operational-polish.blade.php',
            ),
            resource_path(
                'views/components/operational-nav.blade.php',
            ),
        ] as $path) {
            $contents = file_get_contents($path);

            $this->assertStringNotContainsString(
                '<style',
                $contents,
                $path,
            );
            $this->assertStringNotContainsString(
                '<script',
                $contents,
                $path,
            );
        }
    }
}
