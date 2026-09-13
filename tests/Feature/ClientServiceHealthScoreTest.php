<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\ClientHealthScoreBuilder;
use App\Support\ServiceHealthScore;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServiceHealthScoreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_service_score_is_deterministic_and_overdue_collection_is_critical(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();
        $client = $this->client($user, $organization, 'Cliente salud');

        $healthy = $this->order($user, $organization, $client, [
            'title' => 'Servicio saludable',
            'stage' => 'execution',
            'amount' => 1000,
            'next_action' => 'Continuar implementación',
            'next_action_at' => CarbonImmutable::now()->addDays(5),
            'last_activity_at' => CarbonImmutable::now(),
        ]);

        $overdue = $this->order($user, $organization, $client, [
            'title' => 'Servicio con cobranza vencida',
            'stage' => 'invoiced',
            'amount' => 1000,
            'invoice_number' => 'F001-100',
            'invoice_amount' => 1000,
            'invoice_due_date' => '2026-09-03',
            'next_action' => 'Cobrar factura',
            'next_action_at' => CarbonImmutable::now()->addDays(2),
            'last_activity_at' => CarbonImmutable::now(),
        ]);

        $healthyScore = ServiceHealthScore::evaluate(
            $healthy,
            CarbonImmutable::now(),
        );
        $overdueScore = ServiceHealthScore::evaluate(
            $overdue,
            CarbonImmutable::now(),
        );

        $this->assertSame(97, $healthyScore['score']);
        $this->assertSame('healthy', $healthyScore['status']);
        $this->assertSame('Saludable', $healthyScore['label']);

        $this->assertSame(27, $overdueScore['score']);
        $this->assertSame('critical', $overdueScore['status']);
        $this->assertSame('Crítico', $overdueScore['label']);
        $this->assertContains('Cobro vencido', $overdueScore['operational']['reasons']);
        $this->assertTrue(
            collect($overdueScore['reasons'])
                ->contains(fn (string $reason): bool => str_contains(
                    $reason,
                    'Cobranza vencida',
                )),
        );
    }

    public function test_client_score_exposes_worst_service_without_hiding_risk(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();
        $client = $this->client($user, $organization, 'Cliente mixto');
        $healthyClient = $this->client($user, $organization, 'Cliente sano');

        $this->order($user, $organization, $client, [
            'title' => 'Servicio estable',
            'stage' => 'execution',
            'amount' => 1000,
            'next_action' => 'Continuar',
            'next_action_at' => CarbonImmutable::now()->addDays(5),
            'last_activity_at' => CarbonImmutable::now(),
        ]);

        $this->order($user, $organization, $client, [
            'title' => 'Factura crítica',
            'stage' => 'invoiced',
            'invoice_number' => 'F001-200',
            'invoice_amount' => 500,
            'invoice_due_date' => '2026-09-03',
            'next_action' => 'Cobrar',
            'next_action_at' => CarbonImmutable::now()->addDays(2),
            'last_activity_at' => CarbonImmutable::now(),
        ]);

        $this->order($user, $organization, $healthyClient, [
            'title' => 'Servicio sano',
            'stage' => 'execution',
            'amount' => 800,
            'next_action' => 'Continuar',
            'next_action_at' => CarbonImmutable::now()->addDays(5),
            'last_activity_at' => CarbonImmutable::now(),
        ]);

        $clients = app(ClientHealthScoreBuilder::class)->build(
            collect([$organization->id]),
            null,
            CarbonImmutable::now(),
        );

        $mixed = $clients->firstWhere('client_name', 'Cliente mixto');
        $healthy = $clients->firstWhere('client_name', 'Cliente sano');

        $this->assertNotNull($mixed);
        $this->assertNotNull($healthy);
        $this->assertSame(53, $mixed['score']);
        $this->assertSame('risk', $mixed['status']);
        $this->assertSame(1, $mixed['critical_count']);
        $this->assertSame(1, $mixed['overdue_count']);
        $this->assertSame('Factura crítica', $mixed['worst_service']['title']);
        $this->assertGreaterThan($mixed['score'], $healthy['score']);
        $this->assertSame('Cliente mixto', $clients->first()['client_name']);
    }

    public function test_client_health_respects_authorized_organizations(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();
        $visible = $this->client($user, $organization, 'Visible');

        $this->order($user, $organization, $visible, [
            'stage' => 'execution',
            'amount' => 100,
            'next_action' => 'Continuar',
            'next_action_at' => CarbonImmutable::now()->addDays(5),
            'last_activity_at' => CarbonImmutable::now(),
        ]);

        $foreign = Organization::query()->create([
            'name' => 'Ajena',
            'slug' => 'health-foreign',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $hidden = $this->client($user, $foreign, 'Oculto');

        $this->order($user, $foreign, $hidden, [
            'stage' => 'invoiced',
            'invoice_number' => 'HIDDEN-1',
            'invoice_amount' => 9999,
            'invoice_due_date' => '2026-06-01',
        ]);

        $clients = app(ClientHealthScoreBuilder::class)->build(
            collect([$organization->id]),
            $organization->id,
            CarbonImmutable::now(),
        );

        $this->assertSame(['Visible'], $clients->pluck('client_name')->all());
    }

    public function test_service_and_executive_pages_render_health_scores(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();
        $client = $this->client($user, $organization, 'Cliente visible health');

        $this->order($user, $organization, $client, [
            'title' => 'Servicio visible health',
            'stage' => 'execution',
            'amount' => 1200,
            'next_action' => 'Continuar',
            'next_action_at' => CarbonImmutable::now()->addDays(5),
            'last_activity_at' => CarbonImmutable::now(),
        ]);

        $this->actingAs($user)
            ->get('/servicios?focus=all')
            ->assertOk()
            ->assertSee('Health')
            ->assertSee('97/100')
            ->assertSee('Saludable');

        $this->actingAs($user)
            ->get('/resumen')
            ->assertOk()
            ->assertSee('Health Score de clientes')
            ->assertSee('Cliente visible health')
            ->assertSee('97/100');
    }

    private function order(
        User $user,
        Organization $organization,
        Client $client,
        array $overrides = [],
    ): ServiceOrder {
        return ServiceOrder::query()->create(array_merge([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio health',
            'stage' => 'execution',
            'currency' => 'PEN',
            'includes_tax' => true,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ], $overrides));
    }

    private function client(
        User $user,
        Organization $organization,
        string $name,
    ): Client {
        return Client::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'health-score',
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

        return [$user, $organization];
    }
}
