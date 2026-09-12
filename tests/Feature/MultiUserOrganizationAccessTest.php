<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiUserOrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_manage_their_organizations(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $admin = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true]);
        $viewer = User::factory()->create(['is_active' => true]);

        $organization = Organization::query()->create([
            'name' => 'Casa Andina',
            'slug' => 'casa-andina-test',
            'category' => 'company',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);
        $organization->users()->attach($admin->id, [
            'role' => 'admin',
            'is_default' => true,
            'is_active' => true,
        ]);
        $organization->users()->attach($member->id, [
            'role' => 'member',
            'is_default' => true,
            'is_active' => true,
        ]);
        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertSame([$organization->id], $owner->manageableOrganizationIds());
        $this->assertSame([$organization->id], $admin->manageableOrganizationIds());
        $this->assertSame([], $member->manageableOrganizationIds());
        $this->assertSame([], $viewer->manageableOrganizationIds());
        $this->assertTrue($owner->canManageTeam());
        $this->assertTrue($admin->canManageTeam());
        $this->assertFalse($member->canManageTeam());
        $this->assertFalse($viewer->canManageTeam());
    }

    public function test_inactive_memberships_do_not_grant_team_management(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-test',
            'category' => 'company',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => false,
        ]);

        $this->assertSame([], $user->manageableOrganizationIds());
        $this->assertFalse($user->canManageTeam());
    }

    public function test_inactive_users_cannot_manage_any_organization(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-inactive-user-test',
            'category' => 'company',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertSame([], $user->manageableOrganizationIds());
        $this->assertFalse($user->canManageTeam());
    }

    public function test_role_catalog_contains_the_initial_access_levels(): void
    {
        $this->assertSame([
            'owner' => 'Owner',
            'admin' => 'Admin',
            'member' => 'Miembro',
            'viewer' => 'Solo lectura',
        ], User::organizationRoleOptions());
    }
}
