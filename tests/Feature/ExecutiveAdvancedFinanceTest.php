<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\ExecutiveFinanceBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveAdvancedFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_builder_calculates_aging_projection_and_client_concentration(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();
        $clientA = $this->client($user, $organization, 'Cliente A');
        $clientB = $this->client($user, $organization, 'Cliente B');

        $this->order($user, $organization, $clientA, [
            'title' => 'Cobrado',
            'stage' => 'paid',
            'amount' => 1000,
            'invoice_number' => 'F001-1',
            'invoice_amount' => 1000,
            'invoice_due_date' => '2026-09-05',
            'paid_date' => '2026-09-08',
        ]);

        $this->order($user, $organization, $clientA, [
            'title' => 'Vigente',
            'stage' => 'invoiced',
            'amount' => 2000,
            'invoice_number' => 'F001-2',
            'invoice_amount' => 2000,
            'invoice_due_date' => '2026-09-18',
        ]);

        $this->order($user, $organization, $clientA, [
            'title' => 'Vencida 10 días',
            'stage' => 'invoiced',
            'amount' => 1000,
            'invoice_number' => 'F001-3',
            'invoice_amount' => 1000,
            'invoice_due_date' => '2026-09-03',
        ]);

        $this->order($user, $organization, $clientB, [
            'title' => 'Vencida 45 días',
            'stage' => 'invoiced',
            'amount' => 500,
            'invoice_number' => 'F001-4',
            'invoice_amount' => 500,
            'invoice_due_date' => '2026-07-30',
        ]);

        $this->order($user, $organization, $clientB, [
            'title' => 'Por facturar',
            'stage' => 'execution',
            'amount' => 600,
        ]);

        $obligation = $this->obligation(
            $user,
            $organization,
            'Compromisos PEN',
            'PEN',
        );

        $this->occurrence($organization, $obligation, '2026-09-11', 200);
        $this->occurrence($organization, $obligation, '2026-09-18', 700);
        $this->occurrence($organization, $obligation, '2026-10-03', 1000);

        $finance = app(ExecutiveFinanceBuilder::class)->build(
            collect([$organization->id]),
            null,
            CarbonImmutable::now(),
        );

        $pen = $finance['currencies']['PEN'];

        $this->assertSame(600.0, $pen['service']['pending_invoice']);
        $this->assertSame(4500.0, $pen['service']['invoiced']);
        $this->assertSame(1000.0, $pen['service']['collected']);
        $this->assertSame(3500.0, $pen['service']['receivable']);
        $this->assertSame(1500.0, $pen['service']['overdue']);
        $this->assertSame(22.2, $pen['service']['collection_rate']);
        $this->assertSame(42.9, $pen['service']['overdue_rate']);

        $this->assertSame(2000.0, $pen['aging']['current']);
        $this->assertSame(1000.0, $pen['aging']['overdue_1_30']);
        $this->assertSame(500.0, $pen['aging']['overdue_31_60']);
        $this->assertSame(0.0, $pen['aging']['overdue_61_plus']);

        $this->assertSame(2000.0, $pen['schedule']['collections_7d']);
        $this->assertSame(2000.0, $pen['schedule']['collections_30d']);
        $this->assertSame(700.0, $pen['schedule']['obligations_7d']);
        $this->assertSame(1700.0, $pen['schedule']['obligations_30d']);
        $this->assertSame(200.0, $pen['schedule']['obligations_overdue']);
        $this->assertSame(1300.0, $pen['schedule']['net_7d']);
        $this->assertSame(300.0, $pen['schedule']['net_30d']);

        $top = $pen['top_receivable_clients']->first();
        $this->assertSame('Cliente A', $top['name']);
        $this->assertSame(3000.0, $top['amount']);
        $this->assertSame(85.7, $top['share']);
    }

    public function test_builder_never_mixes_currencies(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();
        $client = $this->client($user, $organization, 'Cliente multi moneda');

        $this->order($user, $organization, $client, [
            'currency' => 'PEN',
            'stage' => 'invoiced',
            'invoice_number' => 'PEN-1',
            'invoice_amount' => 100,
            'invoice_due_date' => '2026-09-20',
        ]);

        $this->order($user, $organization, $client, [
            'currency' => 'USD',
            'stage' => 'invoiced',
            'invoice_number' => 'USD-1',
            'invoice_amount' => 200,
            'invoice_due_date' => '2026-09-20',
        ]);

        $penObligation = $this->obligation(
            $user,
            $organization,
            'PEN obligation',
            'PEN',
        );
        $usdObligation = $this->obligation(
            $user,
            $organization,
            'USD obligation',
            'USD',
        );

        $this->occurrence($organization, $penObligation, '2026-09-20', 40);
        $this->occurrence($organization, $usdObligation, '2026-09-20', 50);

        $finance = app(ExecutiveFinanceBuilder::class)->build(
            collect([$organization->id]),
            null,
            CarbonImmutable::now(),
        );

        $this->assertSame(
            ['PEN', 'USD'],
            $finance['currencies']->keys()->values()->all(),
        );
        $this->assertSame(100.0, $finance['currencies']['PEN']['service']['receivable']);
        $this->assertSame(200.0, $finance['currencies']['USD']['service']['receivable']);
        $this->assertSame(60.0, $finance['currencies']['PEN']['schedule']['net_30d']);
        $this->assertSame(150.0, $finance['currencies']['USD']['schedule']['net_30d']);
        $this->assertTrue($finance['cross_currency_totals_disabled']);
    }

    public function test_builder_respects_authorized_organization_ids_and_scope(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();
        $client = $this->client($user, $organization, 'Visible');

        $this->order($user, $organization, $client, [
            'stage' => 'invoiced',
            'invoice_number' => 'VISIBLE-1',
            'invoice_amount' => 250,
            'invoice_due_date' => '2026-09-20',
        ]);

        $foreign = Organization::query()->create([
            'name' => 'Ajena',
            'slug' => 'finance-foreign',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $foreignClient = Client::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'No visible',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->order($user, $foreign, $foreignClient, [
            'stage' => 'invoiced',
            'invoice_number' => 'HIDDEN-1',
            'invoice_amount' => 9999,
            'invoice_due_date' => '2026-09-20',
        ]);

        $finance = app(ExecutiveFinanceBuilder::class)->build(
            collect([$organization->id]),
            $organization->id,
            CarbonImmutable::now(),
        );

        $this->assertSame(250.0, $finance['currencies']['PEN']['service']['receivable']);
    }

    public function test_executive_summary_renders_advanced_finance_panel(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 10:00:00');

        [$user, $organization] = $this->context();
        $client = $this->client($user, $organization, 'Cliente ejecutivo');

        $this->order($user, $organization, $client, [
            'stage' => 'invoiced',
            'invoice_number' => 'EXEC-1',
            'invoice_amount' => 1200,
            'invoice_due_date' => '2026-09-18',
        ]);

        $this->actingAs($user)
            ->get('/resumen')
            ->assertOk()
            ->assertSee('Finanzas ejecutivas')
            ->assertSee('Aging de cartera')
            ->assertSee('Proyección operativa neta')
            ->assertSee('Concentración de cuentas por cobrar')
            ->assertSee('Cliente ejecutivo')
            ->assertSee('1,200.00');
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
            'title' => 'Servicio financiero',
            'stage' => 'execution',
            'currency' => 'PEN',
            'includes_tax' => true,
            'created_by' => $user->id,
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

    private function obligation(
        User $user,
        Organization $organization,
        string $name,
        string $currency,
    ): RecurringObligation {
        return RecurringObligation::withoutEvents(
            fn () => RecurringObligation::query()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'category' => 'service',
                'frequency' => 'monthly',
                'anchor_date' => '2026-09-01',
                'expected_amount' => 0,
                'currency' => $currency,
                'reminder_days_before' => 7,
                'is_critical' => false,
                'is_active' => true,
                'created_by' => $user->id,
            ]),
        );
    }

    private function occurrence(
        Organization $organization,
        RecurringObligation $obligation,
        string $dueDate,
        float $amount,
    ): ObligationOccurrence {
        return ObligationOccurrence::query()->create([
            'recurring_obligation_id' => $obligation->id,
            'organization_id' => $organization->id,
            'due_date' => $dueDate,
            'status' => 'pending',
            'expected_amount' => $amount,
            'currency' => $obligation->currency,
        ]);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'finance-advanced',
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
