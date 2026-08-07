<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_project_progress_uses_completed_tasks(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $user = User::factory()->create(['email' => 'rcontreras@arpynet.com']);
        $organization = Organization::query()->create([
            'name' => 'Personal', 'slug' => 'personal', 'category' => 'personal',
            'timezone' => 'America/Lima', 'is_active' => true, 'created_by' => $user->id,
        ]);
        $organization->users()->attach($user->id, [
            'role' => 'owner', 'is_default' => true, 'is_active' => true,
        ]);
        $this->actingAs($user);
        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Preparar viaje a Italia',
            'type' => 'trip', 'horizon' => 'medium', 'status' => 'active',
        ]);
        Task::query()->create([
            'organization_id' => $organization->id, 'project_id' => $project->id,
            'title' => 'Renovar pasaporte', 'status' => 'completed',
        ]);
        Task::query()->create([
            'organization_id' => $organization->id, 'project_id' => $project->id,
            'title' => 'Comprar pasajes', 'status' => 'pending',
        ]);
        $project = Project::query()->withCount([
            'tasks',
            'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed'),
        ])->findOrFail($project->id);
        $this->assertSame(50, $project->progress_percent);
    }

    public function test_project_becomes_stagnated_after_30_days(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $project = new Project([
            'status' => 'active',
            'last_activity_at' => now()->subDays(31),
        ]);
        $this->assertSame('Estancado', $project->stagnation_label);
    }
}
