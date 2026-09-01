<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringObligation;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_page_is_available(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/seguimiento')
            ->assertOk()
            ->assertSee('Seguimiento')
            ->assertSee(
                'Todo lo que requiere atención en un solo lugar',
            );
    }

    public function test_overdue_task_is_critical(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea vencida global',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/seguimiento')
            ->assertOk()
            ->assertSee('Tarea vencida global')
            ->assertSee('Tarea vencida')
            ->assertSee('Crítico');
    }

    public function test_stagnant_project_requires_attention(): void
    {
        [$user, $organization] = $this->context();

        Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto estancado',
            'type' => 'project',
            'horizon' => 'medium',
            'status' => 'planned',
            'currency' => 'PEN',
            'next_action' => 'Revisar',
            'last_activity_at' => now()->subDays(31),
            'created_by' => $user->id,
            'is_private' => false,
        ]);

        $this->actingAs($user)
            ->get('/seguimiento?type=project')
            ->assertOk()
            ->assertSee('Proyecto estancado')
            ->assertSee('Estancado');
    }

    public function test_type_filter_hides_other_types(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Solo tarea visible',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);

        Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto oculto',
            'type' => 'project',
            'horizon' => 'medium',
            'status' => 'planned',
            'currency' => 'PEN',
            'blockers' => 'Bloqueado',
            'created_by' => $user->id,
            'is_private' => false,
        ]);

        $this->actingAs($user)
            ->get('/seguimiento?type=task')
            ->assertOk()
            ->assertSee('Solo tarea visible')
            ->assertDontSee('Proyecto oculto');
    }

    public function test_service_and_obligation_are_integrated(): void
    {
        [
            $user,
            $organization,
        ] = $this->context();

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente global',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio global',
            'stage' => 'execution',
            'currency' => 'PEN',
            'includes_tax' => true,
            'next_action' => 'Enviar informe',
            'next_action_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $obligation = RecurringObligation::withoutEvents(
            fn () => RecurringObligation::query()->create([
                'organization_id' => $organization->id,
                'name' => 'Obligación global',
                'category' => 'service',
                'frequency' => 'monthly',
                'anchor_date' => now()->toDateString(),
                'expected_amount' => 100,
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
            'organization_id' => $organization->id,
            'due_date' => now()->subDay()->toDateString(),
            'status' => 'pending',
            'expected_amount' => 100,
            'currency' => 'PEN',
        ]);

        $this->actingAs($user)
            ->get('/seguimiento')
            ->assertOk()
            ->assertSee('Servicio global')
            ->assertSee('Obligación global');
    }

    public function test_foreign_scope_is_forbidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Ajena',
            'slug' => 'ajena-global',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(
                '/seguimiento?scope='.$foreign->id,
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
