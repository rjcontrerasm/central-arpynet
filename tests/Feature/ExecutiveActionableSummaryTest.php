<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveActionableSummaryTest
    extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_summary_surfaces_a_concrete_decision_for_task_without_next_action(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Implementación sin siguiente paso',
            'status' =>
                'in_progress',
            'urgency' => 'normal',
            'impact' => 'normal',
            'next_action' => null,
            'last_activity_at' =>
                CarbonImmutable::now()
                    ->subDays(8),
            'created_by' =>
                $user->id,
        ]);

        $this->actingAs($user)
            ->get('/resumen')
            ->assertOk()
            ->assertSee(
                'Decidir ahora',
            )
            ->assertSee(
                'Implementación sin siguiente paso',
            )
            ->assertSee(
                'Definir próxima acción',
            );
    }

    public function test_overdue_task_recommends_resolution_or_reschedule(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Informe ejecutivo vencido',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                CarbonImmutable::now()
                    ->subDay(),
            'created_by' =>
                $user->id,
        ]);

        $this->actingAs($user)
            ->get('/resumen')
            ->assertOk()
            ->assertSee(
                'Informe ejecutivo vencido',
            )
            ->assertSee(
                'Resolver o reprogramar',
            );
    }

    public function test_far_future_planned_task_is_not_a_false_decision(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Plan diciembre no urgente',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                CarbonImmutable::now()
                    ->addMonths(3),
            'last_activity_at' =>
                CarbonImmutable::now()
                    ->subDays(20),
            'created_by' =>
                $user->id,
        ]);

        $response =
            $this->actingAs($user)
                ->get('/resumen')
                ->assertOk();

        $this->assertStringNotContainsString(
            'Plan diciembre no urgente',
            (string) $response
                ->viewData('summary')[
                    'decisions'
                ]
                ->pluck('title')
                ->implode('|'),
        );
    }

    private function context(): array
    {
        $user = User::factory()
            ->create([
                'email' =>
                    'rcontreras@arpynet.com',
            ]);

        $organization =
            Organization::query()
                ->create([
                    'name' => 'ARPYNET',
                    'slug' => 'arpynet',
                    'category' =>
                        'company',
                    'timezone' =>
                        'America/Lima',
                    'is_active' => true,
                    'created_by' =>
                        $user->id,
                ]);

        $organization->users()
            ->attach(
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

        return [
            $user,
            $organization,
        ];
    }
}
