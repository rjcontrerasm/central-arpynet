<?php

namespace Tests\Feature;

use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringObligation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_summary_is_available(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/resumen')
            ->assertOk()
            ->assertSee('Resumen de hoy')
            ->assertSee('Atender primero');
    }

    public function test_week_summary_is_available(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/resumen?period=week')
            ->assertOk()
            ->assertSee('Próximos 7 días');
    }

    public function test_overdue_task_appears_in_attention(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Informe atrasado resumen',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/resumen')
            ->assertOk()
            ->assertSee('Informe atrasado resumen')
            ->assertSee('Tarea vencida');
    }

    public function test_waiting_followup_is_in_summary(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Esperar respuesta proveedor',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'waiting_since' => now()->subDay(),
            'waiting_until' => now()->toDateString(),
            'waiting_reason' => 'Esperando confirmación',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/resumen')
            ->assertOk()
            ->assertSee('Esperar respuesta proveedor')
            ->assertSee('Esperando confirmación');
    }

    public function test_week_summary_includes_upcoming_obligation(): void
    {
        [
            $user,
            $organization,
        ] = $this->context();

        $obligation = RecurringObligation::withoutEvents(
            fn () => RecurringObligation::query()->create([
                'organization_id' => $organization->id,
                'name' => 'Licencia semanal',
                'category' => 'service',
                'frequency' => 'monthly',
                'anchor_date' => now()
                    ->addDays(3)
                    ->toDateString(),
                'expected_amount' => 200,
                'currency' => 'PEN',
                'reminder_days_before' => 7,
                'is_critical' => true,
                'is_active' => true,
                'created_by' => $user->id,
            ]),
        );

        ObligationOccurrence::query()->create([
            'recurring_obligation_id' =>
                $obligation->id,
            'organization_id' =>
                $organization->id,
            'due_date' => now()
                ->addDays(3)
                ->toDateString(),
            'status' => 'pending',
            'expected_amount' => 200,
            'currency' => 'PEN',
        ]);

        $this->actingAs($user)
            ->get('/resumen?period=week')
            ->assertOk()
            ->assertSee('Licencia semanal')
            ->assertSee('PEN')
            ->assertSee('200.00');
    }

    public function test_project_stagnation_is_in_week_summary(): void
    {
        [$user, $organization] = $this->context();

        Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' => 'Proyecto revisar resumen',
            'type' => 'project',
            'horizon' => 'medium',
            'status' => 'planned',
            'currency' => 'PEN',
            'last_activity_at' =>
                now()->subDays(20),
            'created_by' => $user->id,
            'is_private' => false,
        ]);

        $this->actingAs($user)
            ->get('/resumen?period=week')
            ->assertOk()
            ->assertSee('Proyecto revisar resumen')
            ->assertSee('Revisar');
    }

    public function test_foreign_scope_is_forbidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Ajena',
            'slug' => 'ajena-resumen',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(
                '/resumen?scope='.$foreign->id,
            )
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

        $organization->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        return [
            $user,
            $organization,
        ];
    }
}
