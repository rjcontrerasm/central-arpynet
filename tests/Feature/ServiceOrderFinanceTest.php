<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_summary_shows_outstanding_amount(): void
    {
        [$user, $organization, $client] =
            $this->context();

        $this->order(
            $user,
            $organization,
            $client,
            [
                'stage' => 'invoiced',
                'amount' => 1500,
                'invoice_number' => 'F001-1',
                'invoice_amount' => 1500,
                'invoice_date' => now()->toDateString(),
                'invoice_due_date' =>
                    now()->addDays(10)->toDateString(),
            ],
        );

        $this->actingAs($user)
            ->get('/servicios?focus=all')
            ->assertOk()
            ->assertSee('Por cobrar')
            ->assertSee('1,500.00');
    }

    public function test_overdue_invoice_can_be_filtered(): void
    {
        [$user, $organization, $client] =
            $this->context();

        $this->order(
            $user,
            $organization,
            $client,
            [
                'title' => 'Factura vencida',
                'stage' => 'invoiced',
                'invoice_number' => 'F001-2',
                'invoice_amount' => 800,
                'invoice_due_date' =>
                    now()->subDay()->toDateString(),
            ],
        );

        $this->order(
            $user,
            $organization,
            $client,
            [
                'title' => 'Factura vigente',
                'stage' => 'invoiced',
                'invoice_number' => 'F001-3',
                'invoice_amount' => 900,
                'invoice_due_date' =>
                    now()->addWeek()->toDateString(),
            ],
        );

        $this->actingAs($user)
            ->get(
                '/servicios?focus=all&finance=overdue',
            )
            ->assertOk()
            ->assertSee('Factura vencida')
            ->assertDontSee('Factura vigente');
    }

    public function test_finance_update_moves_order_to_invoiced(): void
    {
        [$user, $organization, $client] =
            $this->context();

        $order = $this->order(
            $user,
            $organization,
            $client,
            [
                'stage' => 'execution',
            ],
        );

        $this->actingAs($user)
            ->post(
                "/servicios/{$order->id}/finanzas",
                [
                    'amount' => 1200,
                    'invoice_number' => 'F001-10',
                    'invoice_date' => '2026-09-01',
                    'invoice_amount' => 1200,
                    'invoice_due_date' => '2026-09-15',
                    'paid_date' => '',
                    'currency' => 'PEN',
                    'includes_tax' => 1,
                    'focus' => 'all',
                    'finance' => 'all',
                ],
            )
            ->assertRedirect(
                '/servicios?focus=all&finance=all',
            );

        $order->refresh();

        $this->assertSame(
            'invoiced',
            $order->stage,
        );

        $this->assertSame(
            'F001-10',
            $order->invoice_number,
        );
    }

    public function test_paid_date_moves_order_to_paid(): void
    {
        [$user, $organization, $client] =
            $this->context();

        $order = $this->order(
            $user,
            $organization,
            $client,
            [
                'stage' => 'invoiced',
                'invoice_number' => 'F001-11',
                'invoice_amount' => 500,
            ],
        );

        $this->actingAs($user)
            ->post(
                "/servicios/{$order->id}/finanzas",
                [
                    'amount' => 500,
                    'invoice_number' => 'F001-11',
                    'invoice_date' => '2026-09-01',
                    'invoice_amount' => 500,
                    'invoice_due_date' => '2026-09-15',
                    'paid_date' => '2026-09-10',
                    'currency' => 'PEN',
                    'includes_tax' => 1,
                    'focus' => 'all',
                    'finance' => 'all',
                ],
            )
            ->assertRedirect(
                '/servicios?focus=all&finance=all',
            );

        $this->assertSame(
            'paid',
            $order->fresh()->stage,
        );
    }

    private function order(
        User $user,
        Organization $organization,
        Client $client,
        array $overrides = [],
    ): ServiceOrder {
        return ServiceOrder::query()->create(
            array_merge(
                [
                    'organization_id' =>
                        $organization->id,
                    'client_id' => $client->id,
                    'title' => 'Servicio financiero',
                    'stage' => 'opportunity',
                    'currency' => 'PEN',
                    'includes_tax' => true,
                    'created_by' => $user->id,
                ],
                $overrides,
            ),
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

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente prueba',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return [
            $user,
            $organization,
            $client,
        ];
    }
}
