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

    public function test_recurring_conversion_uses_explicit_next_occurrence_and_links_source_task(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 10:00:00');

        [$user, , $task] = $this->context();

        $this->actingAs($user)
            ->post("/tareas/{$task->id}/convertir", [
                'target' => 'recurring',
                'frequency' => 'monthly',
                'anchor_date' => '2026-10-05',
                'create_days_before' => 3,
                'due_time' => '17:00',
            ])
            ->assertRedirect('/mi-dia');

        $rule = RecurringTaskRule::query()
            ->where('title', $task->title)
            ->firstOrFail();

        $this->assertSame(
            '2026-10-05',
            $rule->anchor_date?->format('Y-m-d'),
        );
        $this->assertSame(
            3,
            $rule->create_days_before,
        );
        $this->assertSame(
            'monthly',
            $rule->frequency,
        );
        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );

        $this->assertDatabaseHas(
            'recurring_task_runs',
            [
                'recurring_task_rule_id' =>
                    $rule->id,
                'task_id' => $task->id,
            ],
        );
    }

    public function test_recurring_conversion_can_generate_next_task_with_anticipation_without_duplicate(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 10:00:00');

        [$user, , $task] = $this->context();

        $payload = [
            'target' => 'recurring',
            'frequency' => 'monthly',
            'anchor_date' => '2026-09-09',
            'create_days_before' => 7,
            'due_time' => '17:00',
        ];

        $this->actingAs($user)
            ->post(
                "/tareas/{$task->id}/convertir",
                $payload,
            )
            ->assertRedirect('/mi-dia');

        $rule = RecurringTaskRule::query()
            ->where('title', $task->title)
            ->firstOrFail();

        $externalId =
            'rule:'.$rule->id
            .':2026-09-09';

        $this->assertDatabaseHas(
            'tasks',
            [
                'organization_id' =>
                    $task->organization_id,
                'title' => $task->title,
                'source' => 'recurring',
                'external_system' =>
                    'central_recurring_task',
                'external_id' => $externalId,
            ],
        );

        app(
            \App\Support\RecurringTaskGenerator::class,
        )->generateFor(
            $rule->fresh(),
            now(),
        );

        $this->assertSame(
            1,
            Task::query()
                ->where(
                    'external_id',
                    $externalId,
                )
                ->count(),
        );
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
