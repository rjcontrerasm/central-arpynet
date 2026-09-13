<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\RecurringTaskRule;
use App\Models\RecurringTaskRun;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mi_dia_requires_login(): void
    {
        $this->get('/mi-dia')
            ->assertRedirect('/login');
    }

    public function test_user_can_open_mi_dia(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Mi día')
            ->assertSee('Captura rápida');
    }

    public function test_mi_dia_shows_user_task(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea visible',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Tarea visible');
    }

    public function test_mi_dia_shows_recurring_cycle_and_next_occurrence(): void
    {
        \Carbon\CarbonImmutable::setTestNow(
            '2026-09-06 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Enviar consumos de luz y agua a Noemi',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => '2026-09-05 17:00:00',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $rule = RecurringTaskRule::withoutEvents(
            fn () => RecurringTaskRule::query()
                ->create([
                    'organization_id' =>
                        $organization->id,
                    'title' =>
                        'Enviar consumos de luz y agua a Noemi',
                    'frequency' => 'monthly',
                    'anchor_date' =>
                        '2026-09-05',
                    'create_days_before' => 3,
                    'due_time' => '17:00',
                    'urgency' => 'normal',
                    'impact' => 'normal',
                    'is_active' => true,
                    'assigned_to' => $user->id,
                    'created_by' => $user->id,
                ]),
        );

        RecurringTaskRun::query()->create([
            'recurring_task_rule_id' =>
                $rule->id,
            'organization_id' =>
                $organization->id,
            'scheduled_for' =>
                '2026-09-05',
            'task_id' => $task->id,
            'generated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee(
                'Enviar consumos de luz y agua a Noemi',
            )
            ->assertSee('Mensual')
            ->assertSee('próxima')
            ->assertSee('05/10/2026')
            ->assertSee(
                'Administrar recurrencia',
            );
    }

    public function test_normal_task_offers_direct_recurring_conversion(): void
    {
        [$user, $organization] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea mensual por convertir',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee(
                'Tarea mensual por convertir',
            )
            ->assertSee('Hacer recurrente')
            ->assertSee(
                '/tareas/'.$task->id
                .'/convertir?target=recurring',
                false,
            );
    }

    public function test_foreign_scope_task_is_hidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa ajena',
            'slug' => 'empresa-ajena',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'No visible',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertDontSee('No visible');
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
