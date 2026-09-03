<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringTaskRule;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_project_conversion_links_original_task(): void
    {
        [$user, , $task] = $this->context();

        $this->actingAs($user)
            ->post("/tareas/{$task->id}/convertir", ['target' => 'project'])
            ->assertRedirect('/mi-dia');

        $project = Project::query()->where('name', $task->title)->firstOrFail();

        $this->assertSame($project->id, $task->fresh()->project_id);
        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_service_conversion_requires_same_scope_client(): void
    {
        [$user, $organization, $task] = $this->context();

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente prueba',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post("/tareas/{$task->id}/convertir", [
                'target' => 'service',
                'client_id' => $client->id,
            ])
            ->assertRedirect('/mi-dia');

        $service = ServiceOrder::query()->where('title', $task->title)->firstOrFail();

        $this->assertSame($client->id, $service->client_id);
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame('service:'.$service->id, $task->fresh()->external_id);
    }

    public function test_recurring_conversion_starts_next_occurrence(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 10:00:00');

        [$user, , $task] = $this->context();

        $this->actingAs($user)
            ->post("/tareas/{$task->id}/convertir", [
                'target' => 'recurring',
                'frequency' => 'weekly',
            ])
            ->assertRedirect('/mi-dia');

        $rule = RecurringTaskRule::query()->where('title', $task->title)->firstOrFail();

        $this->assertSame('2026-09-09', $rule->anchor_date?->format('Y-m-d'));
        $this->assertSame('pending', $task->fresh()->status);
        $this->assertDatabaseCount('recurring_task_runs', 0);
    }

    public function test_waiting_conversion_moves_due_date_to_followup(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 10:00:00');

        [$user, , $task] = $this->context();

        $this->actingAs($user)
            ->post("/tareas/{$task->id}/convertir", [
                'target' => 'waiting',
                'waiting_until' => '2026-09-05',
                'waiting_reason' => 'Esperando aprobación',
            ])
            ->assertRedirect('/mi-dia');

        $task->refresh();

        $this->assertSame('waiting', $task->status);
        $this->assertSame('2026-09-05', $task->waiting_until?->format('Y-m-d'));
        $this->assertNull($task->due_at);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
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

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea convertible',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'high',
            'next_action' => 'Llamar al cliente',
            'due_at' => now()->addDays(2),
            'created_by' => $user->id,
        ]);

        return [$user, $organization, $task];
    }
}
