<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\RecurringTaskRule;
use App\Models\RecurringTaskRun;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyOpsOverdueRecurringTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_daily_view_groups_overdue_recurring_occurrences_and_keeps_today_visible(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-25 09:00:00',
        );

        [$user, $organization] = $this->context();

        $rule = RecurringTaskRule::withoutEvents(
            fn () => RecurringTaskRule::query()
                ->create([
                    'organization_id' =>
                        $organization->id,
                    'title' =>
                        'Checklist Casa Andina',
                    'frequency' => 'daily',
                    'anchor_date' =>
                        '2026-09-22',
                    'create_days_before' => 0,
                    'due_time' => '08:00',
                    'urgency' => 'normal',
                    'impact' => 'normal',
                    'is_active' => true,
                    'assigned_to' => $user->id,
                    'created_by' => $user->id,
                ]),
        );

        foreach ([
            '2026-09-22',
            '2026-09-23',
            '2026-09-24',
            '2026-09-25',
        ] as $date) {
            $task = Task::query()->create([
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'Checklist Casa Andina',
                'status' => 'pending',
                'urgency' => 'normal',
                'impact' => 'normal',
                'due_at' => $date.' 08:00:00',
                'assigned_to' => $user->id,
                'created_by' => $user->id,
            ]);

            RecurringTaskRun::query()->create([
                'recurring_task_rule_id' =>
                    $rule->id,
                'organization_id' =>
                    $organization->id,
                'scheduled_for' => $date,
                'task_id' => $task->id,
                'generated_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee(
                '3 tareas vencidas requieren',
            )
            ->assertSee('Ver vencidas')
            ->assertSee('Vencidas × 3')
            ->assertSee('3 pendientes vencidas')
            ->assertSee('22/09/2026')
            ->assertSee('24/09/2026')
            ->assertSee(
                'hoy también existe una nueva ocurrencia',
            )
            ->assertSee('Completar más antigua')
            ->assertSee('Ver pendientes')
            ->assertSee(
                'recurring_rule='.$rule->id,
                false,
            )
            ->assertSeeInOrder([
                'Vencidas',
                'Checklist Casa Andina',
                'Hoy',
                'Checklist Casa Andina',
            ]);

        $this->assertSame(
            2,
            substr_count(
                $response->getContent(),
                'Checklist Casa Andina',
            ),
            'La deuda recurrente debe resumirse en una tarjeta más la ocurrencia de hoy.',
        );
    }

    public function test_overdue_filter_reveals_items_hidden_by_default_limit(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-25 09:00:00',
        );

        [$user, $organization] = $this->context();

        foreach (range(1, 9) as $index) {
            Task::query()->create([
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'Vencida '.$index,
                'status' => 'pending',
                'urgency' => 'normal',
                'impact' => 'normal',
                'due_at' =>
                    now()->subDays(
                        10 - $index,
                    ),
                'assigned_to' => $user->id,
                'created_by' => $user->id,
            ]);
        }

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Ver las 9')
            ->assertDontSee('Vencida 9');

        $this->actingAs($user)
            ->get('/mi-dia?priority=overdue')
            ->assertOk()
            ->assertSee('Vencida 1')
            ->assertSee('Vencida 9')
            ->assertSee('Vencidas');
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' =>
                'daily-overdue@arpynet.test',
            'is_active' => true,
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'CASA ANDINA',
                'slug' => 'casa-andina-overdue',
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

        $user->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
