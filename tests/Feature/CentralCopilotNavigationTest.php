<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralCopilotNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_navigation_exposes_copilot_without_replacing_jarvis(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get(route('daily-ops.show'))
            ->assertOk()
            ->assertSee(route('central-copilot.index'), false)
            ->assertSee('Copilot')
            ->assertSee(route('agent-proposals.index'), false)
            ->assertSee('Jarvis');
    }

    public function test_copilot_marks_its_shared_navigation_state(): void
    {
        [$user, $organization] = $this->context();

        $this->actingAs($user)
            ->get(route('central-copilot.index', [
                'scope' => $organization->id,
            ]))
            ->assertOk()
            ->assertSee('Más · Copilot')
            ->assertSee('Jarvis')
            ->assertSee('CENTRAL Copilot');
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'copilot-navigation@arpynet.test',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Copilot Navigation',
            'slug' => 'arpynet-copilot-navigation',
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

        return [$user, $organization];
    }
}
