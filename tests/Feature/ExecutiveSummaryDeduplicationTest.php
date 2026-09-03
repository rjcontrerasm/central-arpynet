<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveSummaryDeduplicationTest
    extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_decision_item_is_not_repeated_in_other_alerts(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea crítica sin duplicar',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'critical',
            'due_at' =>
                CarbonImmutable::now()
                    ->subDay(),
            'created_by' =>
                $user->id,
        ]);

        $response =
            $this->actingAs($user)
                ->get('/resumen')
                ->assertOk()
                ->assertSee(
                    'Tarea crítica sin duplicar',
                )
                ->assertSee(
                    'Decidir ahora',
                )
                ->assertSee(
                    'Otras alertas',
                );

        $summary =
            $response->viewData(
                'summary',
            );

        $this->assertTrue(
            $summary['decisions']
                ->contains(
                    fn (
                        array $item,
                    ): bool =>
                        $item['title']
                        ===
                        'Tarea crítica sin duplicar',
                ),
        );

        $this->assertFalse(
            $summary['attention']
                ->contains(
                    fn (
                        array $item,
                    ): bool =>
                        $item['title']
                        ===
                        'Tarea crítica sin duplicar',
                ),
        );

        $this->assertTrue(
            $summary['attention_all']
                ->contains(
                    fn (
                        array $item,
                    ): bool =>
                        $item['title']
                        ===
                        'Tarea crítica sin duplicar',
                ),
        );

    }

    public function test_non_decision_attention_remains_in_other_alerts(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea semanal solo vigilar',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                CarbonImmutable::now()
                    ->addDays(5),
            'next_action' =>
                'Revisar el viernes',
            'created_by' =>
                $user->id,
        ]);

        $response =
            $this->actingAs($user)
                ->get(
                    '/resumen?period=week',
                )
                ->assertOk();

        $summary =
            $response->viewData(
                'summary',
            );

        $this->assertFalse(
            $summary['decisions']
                ->contains(
                    fn (
                        array $item,
                    ): bool =>
                        $item['title']
                        ===
                        'Tarea semanal solo vigilar',
                ),
        );

        $this->assertTrue(
            $summary['attention']
                ->contains(
                    fn (
                        array $item,
                    ): bool =>
                        $item['title']
                        ===
                        'Tarea semanal solo vigilar',
                ),
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
