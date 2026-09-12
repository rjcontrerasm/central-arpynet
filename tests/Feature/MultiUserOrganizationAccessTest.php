<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiUserOrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_and_member_can_write_but_viewer_is_read_only(): void
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

        $this->assertTrue($owner->canWriteToOrganization($organization->id));
        $this->assertTrue($admin->canWriteToOrganization($organization->id));
        $this->assertTrue($member->canWriteToOrganization($organization->id));
        $this->assertFalse($viewer->canWriteToOrganization($organization->id));

        $this->assertTrue($owner->canManageTeam());
        $this->assertTrue($admin->canManageTeam());
        $this->assertFalse($member->canManageTeam());
        $this->assertFalse($viewer->canManageTeam());
    }

    public function test_viewer_cannot_create_an_organization_scoped_record(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $viewer = User::factory()->create(['is_active' => true]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-viewer-guard-test',
            'category' => 'company',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($viewer);

        $this->expectException(AuthorizationException::class);

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'No debe crearse',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'source' => 'manual',
        ]);
    }

    public function test_member_can_create_an_organization_scoped_record(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-member-write-test',
            'category' => 'company',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $organization->users()->attach($member->id, [
            'role' => 'member',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($member);

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea permitida',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'source' => 'manual',
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'organization_id' => $organization->id,
            'created_by' => $member->id,
        ]);
    }

    public function test_viewer_cannot_be_assigned_operational_work(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $viewer = User::factory()->create(['is_active' => true]);

        $organization = Organization::query()->create([
            'name' => 'Casa Andina',
            'slug' => 'casa-andina-assignee-test',
            'category' => 'company',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($owner);

        $this->expectException(ValidationException::class);

        Task::query()->create([
            'organization_id' => $organization->id,
            'assigned_to' => $viewer->id,
            'title' => 'Asignación inválida',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'source' => 'manual',
        ]);
    }

    public function test_inactive_memberships_do_not_grant_team_management_or_write_access(): void
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
        $this->assertSame([], $user->writableOrganizationIds());
        $this->assertFalse($user->canManageTeam());
        $this->assertFalse($user->canWriteToOrganization($organization->id));
    }

    public function test_inactive_users_cannot_manage_or_write_any_organization(): void
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
        $this->assertSame([], $user->writableOrganizationIds());
        $this->assertFalse($user->canManageTeam());
        $this->assertFalse($user->canWriteToOrganization($organization->id));
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
