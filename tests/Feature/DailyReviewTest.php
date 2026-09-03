<?php

namespace Tests\Feature;

use App\Models\DailyReviewSession;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_daily_review_renders_four_guided_steps(): void
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
                'Decisión pendiente',
            'status' =>
                'in_progress',
            'urgency' => 'normal',
            'impact' => 'normal',
            'next_action' => null,
            'created_by' =>
                $user->id,
        ]);

        $this->actingAs($user)
            ->get('/revision-diaria')
            ->assertOk()
            ->assertSee('Revisión diaria')
            ->assertSee(
                'Decisiones y críticos',
            )
            ->assertSee(
                'Seguimientos en espera',
            )
            ->assertSee(
                'Tareas con fecha',
            )
            ->assertSee(
                'Servicios y vencimientos',
            )
            ->assertSee('0/4 revisados');
    }

    public function test_review_completes_after_all_four_steps(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [$user] = $this->context();

        foreach ([
            'decisions',
            'waiting',
            'tasks',
            'operations',
        ] as $step) {
            $this->actingAs($user)
                ->post(
                    '/revision-diaria/revisar',
                    ['step' => $step],
                )
                ->assertRedirect(
                    '/revision-diaria',
                );
        }

        $review = DailyReviewSession::query()
            ->where(
                'user_id',
                $user->id,
            )
            ->firstOrFail();

        $this->assertNotNull(
            $review->completed_at,
        );

        $this->assertSame(
            4,
            $review->reviewedCount(),
        );
    }

    public function test_marking_same_step_twice_is_idempotent(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [$user] = $this->context();

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)
                ->post(
                    '/revision-diaria/revisar',
                    ['step' => 'decisions'],
                )
                ->assertRedirect(
                    '/revision-diaria',
                );
        }

        $this->assertDatabaseCount(
            'daily_review_sessions',
            1,
        );

        $review = DailyReviewSession::query()
            ->firstOrFail();

        $this->assertNotNull(
            $review->decisions_reviewed_at,
        );

        $this->assertSame(
            1,
            $review->reviewedCount(),
        );
    }

    public function test_review_is_scoped_to_current_day(): void
    {
        [$user] = $this->context();

        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        $this->actingAs($user)
            ->post(
                '/revision-diaria/revisar',
                ['step' => 'decisions'],
            )
            ->assertRedirect(
                '/revision-diaria',
            );

        CarbonImmutable::setTestNow(
            '2026-09-03 10:00:00',
        );

        $this->actingAs($user)
            ->get('/revision-diaria')
            ->assertOk()
            ->assertSee('0/4 revisados');
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
                'slug' => 'arpynet',
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

        return [
            $user,
            $organization,
        ];
    }
}
