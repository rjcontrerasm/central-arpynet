<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyQuickViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_view_shows_compact_mode_controls_and_all_overdue_tasks(): void
    {
        [$user, $organization] = $this->context();

        foreach (range(1, 10) as $index) {
            Task::query()->create([
                'organization_id' => $organization->id,
                'title' => sprintf(
                    'Vencida rápida %02d',
                    $index,
                ),
                'status' => 'pending',
                'urgency' => 'normal',
                'impact' => 'normal',
                'due_at' => now()
                    ->subDays(20 - $index),
                'assigned_to' => $user->id,
                'created_by' => $user->id,
            ]);
        }

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee(
                'data-daily-display-mode="quick"',
                false,
            )
            ->assertDontSee('Vencida rápida 10');

        $this->actingAs($user)
            ->get('/mi-dia?display=quick')
            ->assertOk()
            ->assertSee('daily-mode-quick', false)
            ->assertSee('Vencida rápida 10')
            ->assertSee(
                'data-daily-quick-row',
                false,
            )
            ->assertSee(
                '/mi-dia/tareas/',
                false,
            )
            ->assertSee(
                '/prioridad',
                false,
            );
    }

    public function test_quick_view_orders_by_review_priority_then_due_date(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Normal más antigua',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => now()->subDays(12),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Alta intermedia',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'normal',
            'due_at' => now()->subDays(7),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Crítica más reciente',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'critical',
            'due_at' => now()->subDays(2),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia?display=quick')
            ->assertOk()
            ->assertSeeInOrder([
                'Crítica más reciente',
                'Alta intermedia',
                'Normal más antigua',
            ])
            ->assertSee(
                'priority-level-critical',
                false,
            )
            ->assertSee(
                'priority-level-high',
                false,
            )
            ->assertSee(
                'priority-level-normal',
                false,
            );

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSeeInOrder([
                'Normal más antigua',
                'Alta intermedia',
                'Crítica más reciente',
            ]);
    }

    public function test_user_can_update_task_priority_from_quick_view(): void
    {
        [$user, $organization] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Priorizar desde lista',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post(
                '/mi-dia/tareas/'
                .$task->id
                .'/prioridad',
                [
                    'urgency' => 'critical',
                    'impact' => 'high',
                    'view' => 'mine',
                    'display' => 'quick',
                ],
            );

        $response->assertRedirect(
            route(
                'daily-ops.show',
                [
                    'view' => 'mine',
                    'display' => 'quick',
                ],
            ),
        );

        $task->refresh();

        $this->assertSame(
            'critical',
            $task->urgency,
        );
        $this->assertSame(
            'high',
            $task->impact,
        );
        $this->assertSame(
            'Priorizar desde lista',
            $task->title,
        );
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

        $organization->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        return [$user, $organization];
    }
}
