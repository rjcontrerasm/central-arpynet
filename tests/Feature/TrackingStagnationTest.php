<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\GlobalTrackingItemFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingStagnationTest
    extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_task_without_movement_is_detected_conservatively(): void
    {
        $now = CarbonImmutable::parse(
            '2026-09-02 10:00:00',
            'America/Lima',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        $task = Task::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'Implementación detenida',
                'status' =>
                    'in_progress',
                'urgency' =>
                    'normal',
                'impact' =>
                    'normal',
                'next_action' => null,
                'last_activity_at' =>
                    $now->subDays(8),
                'created_by' =>
                    $user->id,
            ]);

        $task->load(
            'organization',
        );

        $item =
            GlobalTrackingItemFactory::task(
                $task,
                $now,
            );

        $this->assertTrue(
            $item['stagnant'],
        );

        $this->assertTrue(
            $item['no_next_action'],
        );

        $this->assertSame(
            'attention',
            $item['level'],
        );

        $this->assertContains(
            'En curso sin siguiente acción',
            $item['reasons'],
        );
    }

    public function test_planned_pending_task_far_in_future_is_not_false_positive(): void
    {
        $now = CarbonImmutable::parse(
            '2026-09-02 10:00:00',
            'America/Lima',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        $task = Task::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'Plan de diciembre',
                'status' => 'pending',
                'urgency' => 'normal',
                'impact' => 'normal',
                'next_action' => null,
                'due_at' =>
                    $now->addMonths(3),
                'last_activity_at' =>
                    $now->subDays(20),
                'created_by' =>
                    $user->id,
            ]);

        $task->load(
            'organization',
        );

        $item =
            GlobalTrackingItemFactory::task(
                $task,
                $now,
            );

        $this->assertFalse(
            $item['stagnant'],
        );

        $this->assertFalse(
            $item['no_next_action'],
        );
    }

    public function test_tracking_can_filter_stagnant_items(): void
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
                'Tarea realmente estancada',
            'status' =>
                'in_progress',
            'urgency' => 'normal',
            'impact' => 'normal',
            'next_action' =>
                'Resolver bloqueo',
            'last_activity_at' =>
                CarbonImmutable::now()
                    ->subDays(8),
            'created_by' =>
                $user->id,
        ]);

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea reciente',
            'status' =>
                'in_progress',
            'urgency' => 'normal',
            'impact' => 'normal',
            'next_action' =>
                'Continuar mañana',
            'last_activity_at' =>
                CarbonImmutable::now(),
            'created_by' =>
                $user->id,
        ]);

        $this->actingAs($user)
            ->get(
                '/seguimiento'
                .'?type=task'
                .'&focus=stagnant',
            )
            ->assertOk()
            ->assertSee(
                'Tarea realmente estancada',
            )
            ->assertDontSee(
                'Tarea reciente',
            )
            ->assertSee(
                'Estancados',
            );
    }

    public function test_tracking_can_filter_missing_next_action(): void
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
                'Sin paso siguiente',
            'status' =>
                'in_progress',
            'urgency' => 'normal',
            'impact' => 'normal',
            'next_action' => null,
            'last_activity_at' =>
                CarbonImmutable::now(),
            'created_by' =>
                $user->id,
        ]);

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Con paso siguiente',
            'status' =>
                'in_progress',
            'urgency' => 'normal',
            'impact' => 'normal',
            'next_action' =>
                'Enviar correo',
            'last_activity_at' =>
                CarbonImmutable::now(),
            'created_by' =>
                $user->id,
        ]);

        $this->actingAs($user)
            ->get(
                '/seguimiento'
                .'?type=task'
                .'&focus=no_next_action',
            )
            ->assertOk()
            ->assertSee(
                'Sin paso siguiente',
            )
            ->assertDontSee(
                'Con paso siguiente',
            )
            ->assertSee(
                'Sin próxima acción',
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
