<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringObligation;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Operational360Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_360_requires_login(): void
    {
        $this->get('/360')
            ->assertRedirect('/login');
    }

    public function test_360_connects_operations_and_finances(): void
    {
        CarbonImmutable::setTestNow('2026-09-06 10:00:00');

        [$user, $organization] = $this->context();

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente Vista 360',
            'contact_name' => 'Contacto 360',
            'email' => 'cliente360@example.com',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $service = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio Vista 360',
            'stage' => 'invoiced',
            'amount' => 1000,
            'invoice_number' => 'F001-360',
            'invoice_amount' => 1200,
            'invoice_due_date' => '2026-09-05',
            'currency' => 'PEN',
            'includes_tax' => true,
            'next_action' => 'Cobrar factura',
            'next_action_at' => now(),
            'created_by' => $user->id,
        ]);

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto Vista 360',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'budget' => 5000,
            'currency' => 'PEN',
            'target_date' => '2026-09-10',
            'next_action' => 'Cerrar entregable 360',
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'title' => 'Tarea Vista 360',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => '2026-09-05 17:00:00',
            'created_by' => $user->id,
        ]);

        $obligation = RecurringObligation::withoutEvents(
            fn () => RecurringObligation::query()->create([
                'organization_id' => $organization->id,
                'name' => 'Licencia Vista 360',
                'category' => 'service',
                'frequency' => 'monthly',
                'anchor_date' => '2026-09-05',
                'expected_amount' => 300,
                'currency' => 'PEN',
                'reminder_days_before' => 7,
                'is_critical' => true,
                'is_active' => true,
                'created_by' => $user->id,
            ]),
        );

        ObligationOccurrence::query()->create([
            'recurring_obligation_id' => $obligation->id,
            'organization_id' => $organization->id,
            'due_date' => '2026-09-05',
            'status' => 'pending',
            'expected_amount' => 300,
            'currency' => 'PEN',
        ]);

        Incident::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'service_order_id' => $service->id,
            'project_id' => $project->id,
            'title' => 'Incidente Vista 360',
            'severity' => 'critical',
            'status' => 'investigating',
            'next_action' => 'Mitigar incidente',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get('/360?scope='.$organization->id)
            ->assertOk()
            ->assertSee('Vista 360')
            ->assertSee('Cliente Vista 360')
            ->assertSee('Servicio Vista 360')
            ->assertSee('Proyecto Vista 360')
            ->assertSee('Tarea Vista 360')
            ->assertSee('Licencia Vista 360')
            ->assertSee('Incidente Vista 360')
            ->assertSee('Finanzas consolidadas')
            ->assertSee('PEN 1,200.00')
            ->assertSee('PEN 5,000.00')
            ->assertSee('PEN 300.00')
            ->assertSee('data-operational-nav', false);

        $response->assertSee(
            '/proyectos?scope='.$organization->id,
            false,
        );

        $response->assertSee(
            '/servicios?scope='.$organization->id,
            false,
        );
    }

    public function test_foreign_360_scope_is_forbidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Ámbito ajeno 360',
            'slug' => 'ambito-ajeno-360',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/360?scope='.$foreign->id)
            ->assertForbidden();
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET 360',
            'slug' => 'arpynet-360',
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
            'current_organization_id' => $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
