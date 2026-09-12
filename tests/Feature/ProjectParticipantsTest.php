<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectParticipantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_member_can_assign_participants_from_the_same_organization(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true]);
        $participant = User::factory()->create(['is_active' => true]);

        $organization = $this->createOrganization($owner, 'ARPYNET', 'arpynet-project-test');

        $organization->users()->attach($member->id, [
            'role' => 'member',
            'is_default' => true,
            'is_active' => true,
        ]);
        $organization->users()->attach($participant->id, [
            'role' => 'member',
            'is_default' => false,
            'is_active' => true,
        ]);

        $project = $this->createProject($organization, $owner);

        $this->actingAs($member);
        $project->syncParticipants([$participant->id]);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $participant->id,
        ]);
    }

    public function test_project_participation_never_grants_cross_organization_access(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true]);
        $outsider = User::factory()->create(['is_active' => true]);

        $organization = $this->createOrganization($owner, 'Casa Andina', 'casa-andina-project-test');
        $otherOrganization = $this->createOrganization($owner, 'Otra empresa', 'otra-empresa-project-test');

        $organization->users()->attach($member->id, [
            'role' => 'member',
            'is_default' => true,
            'is_active' => true,
        ]);
        $otherOrganization->users()->attach($outsider->id, [
            'role' => 'member',
            'is_default' => true,
            'is_active' => true,
        ]);

        $project = $this->createProject($organization, $owner);

        $this->actingAs($member);
        $this->expectException(ValidationException::class);

        $project->syncParticipants([$outsider->id]);
    }

    public function test_viewer_cannot_change_project_participants(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $viewer = User::factory()->create(['is_active' => true]);
        $participant = User::factory()->create(['is_active' => true]);

        $organization = $this->createOrganization($owner, 'ARPYNET', 'arpynet-project-viewer-test');

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => true,
            'is_active' => true,
        ]);
        $organization->users()->attach($participant->id, [
            'role' => 'member',
            'is_default' => false,
            'is_active' => true,
        ]);

        $project = $this->createProject($organization, $owner);

        $this->actingAs($viewer);
        $this->expectException(AuthorizationException::class);

        $project->syncParticipants([$participant->id]);
    }

    private function createOrganization(
        User $creator,
        string $name,
        string $slug,
    ): Organization {
        return Organization::query()->create([
            'name' => $name,
            'slug' => $slug,
            'category' => 'company',
            'is_active' => true,
            'created_by' => $creator->id,
        ]);
    }

    private function createProject(
        Organization $organization,
        User $creator,
    ): Project {
        return Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto multiusuario',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'currency' => 'PEN',
            'created_by' => $creator->id,
        ]);
    }
}
