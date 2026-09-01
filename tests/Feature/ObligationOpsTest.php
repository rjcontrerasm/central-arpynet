<?php

namespace Tests\Feature;

use App\Models\ObligationOccurrence;
use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObligationOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vencimientos_page_is_available(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/vencimientos')
            ->assertOk()
            ->assertSee('Vencimientos')
            ->assertSee(
                'Obligaciones recurrentes, pagos y alertas',
            );
    }

    public function test_overdue_occurrence_is_visible_as_overdue(): void
    {
        [
            $user,
            $organization,
            $obligation,
        ] = $this->context();

        $occurrence = $this->occurrence(
            $organization,
            $obligation,
            now()->subDay()->toDateString(),
            250,
        );

        $this->actingAs($user)
            ->get('/vencimientos')
            ->assertOk()
            ->assertSee($obligation->name)
            ->assertSee('Vencido')
            ->assertSee('250.00');
    }

    public function test_user_can_register_payment(): void
    {
        [
            $user,
            $organization,
            $obligation,
        ] = $this->context();

        $occurrence = $this->occurrence(
            $organization,
            $obligation,
            '2026-09-10',
            300,
        );

        $this->actingAs($user)
            ->post(
                "/vencimientos/{$occurrence->id}/actualizar",
                [
                    'action' => 'paid',
                    'actual_amount' => 295,
                    'paid_date' => '2026-09-09',
                    'payment_reference' => 'OP-123',
                    'focus' => 'all',
                ],
            )
            ->assertRedirect(
                '/vencimientos?focus=all',
            );

        $occurrence->refresh();

        $this->assertSame(
            'paid',
            $occurrence->status,
        );

        $this->assertSame(
            '295.00',
            $occurrence->actual_amount,
        );

        $this->assertSame(
            '2026-09-09',
            $occurrence->paid_date->format('Y-m-d'),
        );

        $this->assertNotNull(
            $occurrence->completed_at,
        );
    }

    public function test_paid_occurrence_can_be_reopened(): void
    {
        [
            $user,
            $organization,
            $obligation,
        ] = $this->context();

        $occurrence = $this->occurrence(
            $organization,
            $obligation,
            '2026-09-10',
            100,
        );

        $occurrence->forceFill([
            'status' => 'paid',
            'actual_amount' => 100,
            'paid_date' => '2026-09-09',
        ])->save();

        $this->actingAs($user)
            ->post(
                "/vencimientos/{$occurrence->id}/actualizar",
                [
                    'action' => 'pending',
                    'focus' => 'all',
                ],
            )
            ->assertRedirect(
                '/vencimientos?focus=all',
            );

        $occurrence->refresh();

        $this->assertSame(
            'pending',
            $occurrence->status,
        );

        $this->assertNull(
            $occurrence->paid_date,
        );

        $this->assertNull(
            $occurrence->completed_at,
        );
    }

    public function test_foreign_scope_is_forbidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Ajena',
            'slug' => 'ajena',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(
                '/vencimientos?scope='.$foreign->id,
            )
            ->assertForbidden();
    }

    private function occurrence(
        Organization $organization,
        RecurringObligation $obligation,
        string $dueDate,
        float $amount,
    ): ObligationOccurrence {
        return ObligationOccurrence::query()->create([
            'recurring_obligation_id' =>
                $obligation->id,
            'organization_id' =>
                $organization->id,
            'due_date' => $dueDate,
            'status' => 'pending',
            'expected_amount' => $amount,
            'currency' => 'PEN',
        ]);
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

        $obligation = RecurringObligation::withoutEvents(
            function () use (
                $user,
                $organization,
            ): RecurringObligation {
                return RecurringObligation::query()->create([
                    'organization_id' =>
                        $organization->id,
                    'name' => 'Pago recurrente',
                    'category' => 'service',
                    'frequency' => 'monthly',
                    'anchor_date' => '2026-09-10',
                    'expected_amount' => 250,
                    'currency' => 'PEN',
                    'reminder_days_before' => 7,
                    'is_critical' => true,
                    'is_active' => true,
                    'created_by' => $user->id,
                ]);
            },
        );

        return [
            $user,
            $organization,
            $obligation,
        ];
    }
}
