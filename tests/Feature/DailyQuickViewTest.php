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
                ],
            );

        $response->assertRedirect(
            route(
                'daily-ops.show',
                ['view' => 'mine'],
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
