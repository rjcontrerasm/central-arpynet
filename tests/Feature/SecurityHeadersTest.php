<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_front_response_has_defensive_headers(): void
    {
        $user = $this->userWithOrganization();

        $this->actingAs($user)
            ->get(route('safety-recovery.index'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader(
                'Referrer-Policy',
                'strict-origin-when-cross-origin',
            )
            ->assertHeader(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=()',
            )
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_https_response_adds_hsts(): void
    {
        $user = $this->userWithOrganization();

        $this->actingAs($user)
            ->withServerVariables(['HTTPS' => 'on'])
            ->get(route('safety-recovery.index'))
            ->assertOk()
            ->assertHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
    }

    private function userWithOrganization(): User
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Headers',
            'slug' => 'arpynet-security-headers',
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

        return $user;
    }
}
