<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\RecurringTaskRule;
use App\Models\Task;
use App\Models\User;
use App\Support\RecurringTaskGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RecurringTaskGenerationTest
    extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_rule_generates_one_idempotent_task_for_today(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 09:00:00',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        $rule =
            RecurringTaskRule::withoutEvents(
                fn () =>
                    RecurringTaskRule::query()
                        ->create([
                            'organization_id' =>
                                $organization->id,
                            'title' =>
                                'Revisión diaria',
                            'next_action' =>
                                'Validar panel',
                            'frequency' =>
                                'daily',
                            'anchor_date' =>
                                '2026-09-02',
                            'create_days_before' =>
                                0,
                            'due_time' =>
                                '17:00',
                            'urgency' =>
                                'normal',
                            'impact' =>
                                'high',
                            'is_active' =>
                                true,
                            'assigned_to' =>
                                $user->id,
                            'created_by' =>
                                $user->id,
                        ]),
            );

        $generator = app(
            RecurringTaskGenerator::class,
        );

        $first =
            $generator->generateFor(
                $rule,
                CarbonImmutable::now(),
            );

        $second =
            $generator->generateFor(
                $rule,
                CarbonImmutable::now(),
            );

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);

        $this->assertDatabaseCount(
            'recurring_task_runs',
            1,
        );

        $task = Task::query()
            ->where(
                'external_system',
                'central_recurring_task',
            )
            ->firstOrFail();

        $this->assertSame(
            'Revisión diaria',
            $task->title,
        );

        $this->assertSame(
            '2026-09-02 17:00',
            $task->due_at?->format(
                'Y-m-d H:i',
            ),
        );

        $this->assertSame(
            'recurring',
            $task->source,
        );
    }

    public function test_rule_can_create_a_future_task_with_anticipation(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 09:00:00',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        $rule =
            RecurringTaskRule::withoutEvents(
                fn () =>
                    RecurringTaskRule::query()
                        ->create([
                            'organization_id' =>
                                $organization->id,
                            'title' =>
                                'Reporte semanal',
                            'frequency' =>
                                'weekly',
                            'anchor_date' =>
                                '2026-09-05',
                            'create_days_before' =>
                                3,
                            'due_time' =>
                                '12:00',
                            'urgency' =>
                                'normal',
                            'impact' =>
                                'normal',
                            'is_active' =>
                                true,
                            'assigned_to' =>
                                $user->id,
                            'created_by' =>
                                $user->id,
                        ]),
            );

        $created = app(
            RecurringTaskGenerator::class,
        )->generateFor(
            $rule,
            CarbonImmutable::now(),
        );

        $this->assertSame(1, $created);

        $this->assertDatabaseHas(
            'recurring_task_runs',
            [
                'recurring_task_rule_id' =>
                    $rule->id,
                'scheduled_for' =>
                    '2026-09-05 00:00:00',
            ],
        );

        $task = Task::query()
            ->where(
                'external_system',
                'central_recurring_task',
            )
            ->firstOrFail();

        $this->assertSame(
            '2026-09-05',
            $task->due_at?->format(
                'Y-m-d',
            ),
        );
    }

    public function test_old_anchor_does_not_backfill_history(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 09:00:00',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        $rule =
            RecurringTaskRule::withoutEvents(
                fn () =>
                    RecurringTaskRule::query()
                        ->create([
                            'organization_id' =>
                                $organization->id,
                            'title' =>
                                'Control mensual',
                            'frequency' =>
                                'monthly',
                            'anchor_date' =>
                                '2025-09-02',
                            'create_days_before' =>
                                0,
                            'due_time' =>
                                '17:00',
                            'urgency' =>
                                'normal',
                            'impact' =>
                                'normal',
                            'is_active' =>
                                true,
                            'assigned_to' =>
                                $user->id,
                            'created_by' =>
                                $user->id,
                        ]),
            );

        app(
            RecurringTaskGenerator::class,
        )->generateFor(
            $rule,
            CarbonImmutable::now(),
        );

        $this->assertDatabaseCount(
            'recurring_task_runs',
            1,
        );

        $this->assertDatabaseHas(
            'recurring_task_runs',
            [
                'scheduled_for' =>
                    '2026-09-02 00:00:00',
            ],
        );
    }

    public function test_command_is_idempotent(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 09:00:00',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        RecurringTaskRule::withoutEvents(
            fn () =>
                RecurringTaskRule::query()
                    ->create([
                        'organization_id' =>
                            $organization->id,
                        'title' =>
                            'Comando recurrente',
                        'frequency' =>
                            'daily',
                        'anchor_date' =>
                            '2026-09-02',
                        'create_days_before' =>
                            0,
                        'due_time' =>
                            '17:00',
                        'urgency' =>
                            'normal',
                        'impact' =>
                            'normal',
                        'is_active' =>
                            true,
                        'assigned_to' =>
                            $user->id,
                        'created_by' =>
                            $user->id,
                    ]),
        );

        $this->assertSame(
            0,
            Artisan::call(
                'tasks:generate-recurring',
            ),
        );

        $this->assertSame(
            0,
            Artisan::call(
                'tasks:generate-recurring',
            ),
        );

        $this->assertDatabaseCount(
            'recurring_task_runs',
            1,
        );

        $this->assertDatabaseCount(
            'tasks',
            1,
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
