<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalUndoOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_service_stage_update_can_be_undone(): void
    {
        [
            $user,
            $organization,
            $client,
        ] = $this->context();

        $service = $this->service(
            $user,
            $organization,
            $client,
        );

        $this->actingAs($user)
            ->post(
                "/servicios/{$service->id}/actualizar",
                [
                    'stage' => 'execution',
                    'next_action' =>
                        'Enviar avance',
                    'next_action_at' =>
                        '2026-09-10 10:00:00',
                    'focus' => 'all',
                ],
            )
            ->assertRedirect(
                '/servicios?focus=all',
            );

        $this->assertSame(
            'execution',
            $service->fresh()->stage,
        );

        $this->post('/deshacer')
            ->assertRedirect(
                '/servicios?focus=all',
            );

        $service->refresh();

        $this->assertSame(
            'opportunity',
            $service->stage,
        );

        $this->assertNull(
            $service->next_action,
        );
    }

    public function test_service_finance_update_can_be_undone(): void
    {
        [
            $user,
            $organization,
            $client,
        ] = $this->context();

        $service = $this->service(
            $user,
            $organization,
            $client,
            [
                'stage' => 'execution',
            ],
        );

        $this->actingAs($user)
            ->post(
                "/servicios/{$service->id}/finanzas",
                [
                    'amount' => 1200,
                    'invoice_number' =>
                        'F001-900',
                    'invoice_date' =>
                        '2026-09-03',
                    'invoice_amount' =>
                        1200,
                    'invoice_due_date' =>
                        '2026-09-20',
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

        $this->assertSame(
            'invoiced',
            $service->fresh()->stage,
        );

        $this->post('/deshacer')
            ->assertRedirect(
                '/servicios?focus=all&finance=all',
            );

        $service->refresh();

        $this->assertSame(
            'execution',
            $service->stage,
        );

        $this->assertNull(
            $service->invoice_number,
        );

        $this->assertNull(
            $service->invoice_amount,
        );
    }

    public function test_obligation_payment_can_be_undone(): void
    {
        [
            $user,
            $organization,
            ,
            $occurrence,
        ] = $this->context(
            withObligation: true,
        );

        $this->actingAs($user)
            ->post(
                "/vencimientos/{$occurrence->id}/actualizar",
                [
                    'action' => 'paid',
                    'actual_amount' => 295,
                    'paid_date' =>
                        '2026-09-09',
                    'payment_reference' =>
                        'OP-UNDO',
                    'focus' => 'all',
                ],
            )
            ->assertRedirect(
                '/vencimientos?focus=all',
            );

        $this->assertSame(
            'paid',
            $occurrence->fresh()->status,
        );

        $this->post('/deshacer')
            ->assertRedirect(
                '/vencimientos?focus=all',
            );

        $occurrence->refresh();

        $this->assertSame(
            'pending',
            $occurrence->status,
        );

        $this->assertNull(
            $occurrence->actual_amount,
        );

        $this->assertNull(
            $occurrence->paid_date,
        );

        $this->assertNull(
            $occurrence->completed_at,
        );
    }

    public function test_last_action_is_global_across_service_and_obligation(): void
    {
        [
            $user,
            $organization,
            $client,
            $occurrence,
        ] = $this->context(
            withObligation: true,
        );

        $service = $this->service(
            $user,
            $organization,
            $client,
        );

        $this->actingAs($user)
            ->post(
                "/servicios/{$service->id}/actualizar",
                [
                    'stage' => 'execution',
                    'focus' => 'all',
                ],
            );

        $this->post(
            "/vencimientos/{$occurrence->id}/actualizar",
            [
                'action' => 'skipped',
                'focus' => 'all',
            ],
        );

        $this->post('/deshacer');

        $this->assertSame(
            'execution',
            $service->fresh()->stage,
        );

        $this->assertSame(
            'pending',
            $occurrence->fresh()->status,
        );

        $this->post('/deshacer')
            ->assertSessionHas(
                'global_undo_success',
                'Ya no hay una acción para deshacer.',
            );
    }

    private function context(
        bool $withObligation = false,
    ): array {
        CarbonImmutable::setTestNow(
            '2026-09-03 10:00:00',
        );

        $user = User::factory()->create([
            'email' =>
                'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' =>
                    'arpynet-undo-ops',
                'category' => 'company',
                'timezone' =>
                    'America/Lima',
                'is_active' => true,
                'created_by' =>
                    $user->id,
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
            'organization_id' =>
                $organization->id,
            'name' => 'Cliente Undo Ops',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        if (! $withObligation) {
            return [
                $user,
                $organization,
                $client,
            ];
        }

        $obligation =
            RecurringObligation::withoutEvents(
                function () use (
                    $user,
                    $organization,
                ): RecurringObligation {
                    return RecurringObligation::query()
                        ->create([
                            'organization_id' =>
                                $organization->id,
                            'name' =>
                                'Pago Undo Ops',
                            'category' =>
                                'service',
                            'frequency' =>
                                'monthly',
                            'anchor_date' =>
                                '2026-09-10',
                            'expected_amount' =>
                                300,
                            'currency' => 'PEN',
                            'reminder_days_before' =>
                                7,
                            'is_critical' => true,
                            'is_active' => true,
                            'created_by' =>
                                $user->id,
                        ]);
                },
            );

        $occurrence =
            ObligationOccurrence::query()
                ->create([
                    'recurring_obligation_id' =>
                        $obligation->id,
                    'organization_id' =>
                        $organization->id,
                    'due_date' =>
                        '2026-09-10',
                    'status' => 'pending',
                    'expected_amount' => 300,
                    'currency' => 'PEN',
                ]);

        return [
            $user,
            $organization,
            $client,
            $occurrence,
        ];
    }

    private function service(
        User $user,
        Organization $organization,
        Client $client,
        array $overrides = [],
    ): ServiceOrder {
        return ServiceOrder::query()
            ->create(
                array_merge(
                    [
                        'organization_id' =>
                            $organization->id,
                        'client_id' =>
                            $client->id,
                        'title' =>
                            'Servicio Undo Ops',
                        'stage' =>
                            'opportunity',
                        'currency' => 'PEN',
                        'includes_tax' => true,
                        'created_by' =>
                            $user->id,
                    ],
                    $overrides,
                ),
            );
    }
}
