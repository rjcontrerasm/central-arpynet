<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecisionInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_decision_inbox_surfaces_task_without_next_action(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 10:00:00');

        [$user, , $task] = $this->context();

        $this->actingAs($user)
            ->get('/decisiones')
            ->assertOk()
            ->assertSee('Decisiones')
            ->assertSee($task->title)
            ->assertSee('Definir próxima acción')
            ->assertSee('Resolver ahora');
    }

    public function test_decision_action_uses_safe_layer(): void
    {
        [$user, $organization, $task] = $this->context();

        $this->actingAs($user)
            ->post("/decisiones/tareas/{$task->id}/accion", [
                'action' => 'complete',
                'scope' => $organization->id,
                'type' => 'task',
            ])
            ->assertRedirect(route('decision-inbox.index', [
                'scope' => $organization->id,
                'type' => 'task',
            ]));

        $this->assertSame('completed', $task->fresh()->status);
    }

    public function test_far_future_task_does_not_become_a_decision(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 10:00:00');

        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Plan futuro sin decisión',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => CarbonImmutable::now()->addMonths(3),
            'last_activity_at' => CarbonImmutable::now()->subDays(20),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/decisiones')
            ->assertOk()
            ->assertDontSee('Plan futuro sin decisión');
    }

    public function test_foreign_scope_is_forbidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Ajena',
            'slug' => 'ajena-decisions',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/decisiones?scope='.$foreign->id)
            ->assertForbidden();
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
            'title' => 'Implementación sin próximo paso',
            'status' => 'in_progress',
            'urgency' => 'normal',
            'impact' => 'normal',
            'next_action' => null,
            'last_activity_at' => now()->subDays(8),
            'created_by' => $user->id,
        ]);

        return [$user, $organization, $task];
    }
}
