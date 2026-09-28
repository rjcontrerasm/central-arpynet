<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_login_to_operational_front(): void
    {
        [$member] = $this->context('member');

        $this->post('/login', [
            'email' => $member->email,
            'password' => 'Secret123!',
        ])
            ->assertRedirect('/mi-dia');

        $this->assertAuthenticatedAs($member);
    }

    public function test_member_password_is_independent_from_admin_panel_access(): void
    {
        [$member] = $this->context('member');

        $this->assertTrue(
            Hash::check(
                'Secret123!',
                $member->password,
            ),
        );

        $this->assertFalse(
            $member->canManageTeam(),
        );

        $this->assertFalse(
            $member->canAccessPanel(
                filament()->getPanel('admin'),
            ),
        );
    }

    public function test_inactive_user_cannot_login(): void
    {
        [$member] = $this->context('member');

        $member->forceFill([
            'is_active' => false,
        ])->save();

        $this->post('/login', [
            'email' => $member->email,
            'password' => 'Secret123!',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_without_active_organization_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'orphan@arpynet.test',
            'password' => 'Secret123!',
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_invalidates_operational_session(): void
    {
        [$member] = $this->context('member');

        $this->actingAs($member)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    private function context(string $role): array
    {
        $owner = User::factory()->create([
            'email' => 'owner-login@arpynet.test',
            'password' => 'Secret123!',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Login Test',
            'slug' => 'arpynet-login-test',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $member = User::factory()->create([
            'email' => 'member-login@arpynet.test',
            'password' => 'Secret123!',
            'is_active' => true,
        ]);

        $organization->users()->attach($member->id, [
            'role' => $role,
            'is_default' => true,
            'is_active' => true,
        ]);

        $member->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        return [$member, $organization, $owner];
    }
}
