<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTaskQuickActionsV2Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_start_action_marks_task_in_progress(): void
    {
        [$user, $organization, $task] =
            $this->context();

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'start',
                ],
            )
            ->assertRedirect('/mi-dia');

        $this->assertDatabaseHas(
            'tasks',
            [
                'id' => $task->id,
                'status' =>
                    'in_progress',
            ],
        );
    }

    public function test_today_action_preserves_filters_and_can_be_undone(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [$user, $organization, $task] =
            $this->context();

        $originalDue =
            $task->due_at?->toIso8601String();

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'today',
                    'scope' =>
                        $organization->id,
                    'q' => 'Acción',
                    'priority' => 'week',
                ],
            )
            ->assertRedirect(
                '/mi-dia?scope='
                .$organization->id
                .'&q=Acci%C3%B3n'
                .'&priority=week',
            )
            ->assertSessionHas(
                'daily_action_success',
            );

        $task->refresh();

        $this->assertSame(
            '2026-09-02',
            $task->due_at?->format(
                'Y-m-d',
            ),
        );

        $this->post(
            '/mi-dia/deshacer',
        )
            ->assertRedirect(
                '/mi-dia?scope='
                .$organization->id
                .'&q=Acci%C3%B3n'
                .'&priority=week',
            )
            ->assertSessionHas(
                'daily_action_success',
                'Acción deshecha.',
            );

        $task->refresh();

        $this->assertSame(
            $originalDue,
            $task->due_at
                ?->toIso8601String(),
        );
    }

    public function test_undo_is_authorized_by_current_membership(): void
    {
        [$user, , $task] =
            $this->context();

        $foreign =
            User::factory()->create();

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'complete',
                ],
            )
            ->assertRedirect('/mi-dia');

        $this->actingAs($foreign)
            ->post('/mi-dia/deshacer')
            ->assertForbidden();
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' =>
                'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' => 'arpynet',
                'category' => 'company',
                'timezone' =>
                    'America/Lima',
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

        $user->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Acción de prueba',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                CarbonImmutable::parse(
                    '2026-09-09 17:00:00',
                ),
            'created_by' => $user->id,
        ]);

        return [
            $user,
            $organization,
            $task,
        ];
    }
}
