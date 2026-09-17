<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskConversionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_viewer_cannot_open_or_submit_task_conversion(): void
    {
        [$owner, $viewer, $organization, $task] = $this->context();

        $this->actingAs($viewer)
            ->get('/tareas/'.$task->id.'/convertir')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/tareas/'.$task->id.'/convertir', [
                'target' => 'project',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('projects', [
            'organization_id' => $organization->id,
            'name' => $task->title,
        ]);

        $this->actingAs($owner)
            ->get('/tareas/'.$task->id.'/convertir')
            ->assertOk();
    }

    public function test_recurring_conversion_exposes_frequency_specific_anchor_suggestions(): void
    {
        CarbonImmutable::setTestNow('2026-09-09 10:00:00');

        [$owner, , , $task] = $this->context(
            '2026-09-09 18:00:00',
        );

        $response = $this->actingAs($owner)
            ->get('/tareas/'.$task->id.'/convertir?target=recurring');

        $response
            ->assertOk()
            ->assertSee('id="conversion-frequency"', false)
            ->assertSee('id="conversion-anchor"', false)
            ->assertSee('"daily":"2026-09-10"', false)
            ->assertSee('"monthly":"2026-10-09"', false)
            ->assertSee(
                "frequency.addEventListener('change', refreshAnchor);",
                false,
            );
    }

    private function context(
        ?string $dueAt = null,
    ): array {
        $owner = User::factory()->create([
            'email' => 'owner-conversion-hardening@arpynet.test',
            'is_active' => true,
        ]);

        $viewer = User::factory()->create([
            'email' => 'viewer-conversion-hardening@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Conversion Hardening',
            'slug' => 'conversion-hardening',
            'category' => 'company',
            'timezone' => 'America/Lima',
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

        $owner->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        $viewer->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea protegida para conversión',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'high',
            'next_action' => 'Validar conversión',
            'due_at' => $dueAt ?? now()->addDays(2),
            'created_by' => $owner->id,
            'assigned_to' => $owner->id,
        ]);

        return [$owner, $viewer, $organization, $task];
    }
}
