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
use App\Models\WeeklyReviewSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_weekly_review_surfaces_operational_fronts(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-03 10:00:00',
        );

        [
            $user,
            $organization,
            $client,
        ] = $this->context();

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea arrastrada',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' =>
                '2026-09-01 17:00:00',
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea próxima',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                '2026-09-08 17:00:00',
            'created_by' => $user->id,
        ]);

        Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto estancado',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'last_activity_at' =>
                now()->subDays(20),
            'created_by' => $user->id,
        ]);

        ServiceOrder::query()->create([
            'organization_id' =>
                $organization->id,
            'client_id' => $client->id,
            'title' =>
                'Factura vencida semanal',
            'stage' => 'invoiced',
            'invoice_number' =>
                'F001-777',
            'invoice_amount' => 800,
            'invoice_due_date' =>
                '2026-09-01',
            'currency' => 'PEN',
            'includes_tax' => true,
            'created_by' => $user->id,
        ]);

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
                                'Obligación semanal',
                            'category' =>
                                'service',
                            'frequency' =>
                                'monthly',
                            'anchor_date' =>
                                '2026-09-02',
                            'expected_amount' =>
                                200,
                            'currency' =>
                                'PEN',
                            'reminder_days_before' =>
                                7,
                            'is_critical' => true,
                            'is_active' => true,
                            'created_by' =>
                                $user->id,
                        ]);
                },
            );

        ObligationOccurrence::query()
            ->create([
                'recurring_obligation_id' =>
                    $obligation->id,
                'organization_id' =>
                    $organization->id,
                'due_date' =>
                    '2026-09-02',
                'status' => 'pending',
                'expected_amount' => 200,
                'currency' => 'PEN',
            ]);

        $this->actingAs($user)
            ->get('/revision-semanal')
            ->assertOk()
            ->assertSee(
                'Revisión semanal',
            )
            ->assertSee(
                'Arrastres y vencidos',
            )
            ->assertSee(
                'Tarea arrastrada',
            )
            ->assertSee(
                'Proyecto estancado',
            )
            ->assertSee(
                'Cobranza y facturación',
            )
            ->assertSee(
                'PEN por cobrar',
            )
            ->assertSee(
                'Obligación semanal',
            )
            ->assertSee(
                'Próximos 7 / 30 días',
            )
            ->assertSee(
                'Tarea próxima',
            );
    }

    public function test_weekly_review_progress_is_persisted_and_completes_at_five_steps(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-03 10:00:00',
        );

        [$user] = $this->context();

        $steps = [
            'carryover',
            'stagnation',
            'finance',
            'obligations',
            'horizon',
        ];

        $this->actingAs($user);

        foreach ($steps as $step) {
            $this->post(
                '/revision-semanal/revisar',
                ['step' => $step],
            )->assertRedirect(
                '/revision-semanal',
            );
        }

        $review =
            WeeklyReviewSession::query()
                ->firstOrFail();

        $this->assertSame(
            '2026-08-31',
            $review->week_start
                ->format('Y-m-d'),
        );

        $this->assertNotNull(
            $review->completed_at,
        );

        $this->get('/revision-semanal')
            ->assertOk()
            ->assertSee('5/5')
            ->assertSee(
                'Revisado ✓',
            );
    }

    public function test_new_week_starts_with_new_progress(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-03 10:00:00',
        );

        [$user] = $this->context();

        $this->actingAs($user)
            ->post(
                '/revision-semanal/revisar',
                ['step' => 'carryover'],
            );

        CarbonImmutable::setTestNow(
            '2026-09-07 10:00:00',
        );

        $this->get('/revision-semanal')
            ->assertOk()
            ->assertSee('0/5');

        $this->assertSame(
            1,
            WeeklyReviewSession::query()
                ->count(),
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' =>
                'rcontreras@arpynet.com',
        ]);

        $organization =
            Organization::query()->create([
                'name' => 'ARPYNET',
                'slug' =>
                    'arpynet-weekly-review',
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

        $user->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        $client = Client::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Cliente revisión semanal',
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
