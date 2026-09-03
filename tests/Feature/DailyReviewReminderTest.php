<?php

namespace Tests\Feature;

use App\Models\DailyReviewSession;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DailyReviewReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_force_creates_one_internal_notification_and_deduplicates(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-03 16:00:00',
        );

        [$user] = $this->context();

        $this->assertSame(
            0,
            Artisan::call(
                'daily-review:remind',
                ['--force' => true],
            ),
        );

        $this->assertCount(
            1,
            $user->fresh()->notifications,
        );

        $notification = $user->fresh()
            ->notifications()
            ->firstOrFail();

        $this->assertSame(
            'Revisión diaria pendiente',
            $notification->data['title'],
        );

        $this->assertSame(
            'daily_review_incomplete',
            $notification->data['type'],
        );

        Artisan::call(
            'daily-review:remind',
            ['--force' => true],
        );

        $this->assertCount(
            1,
            $user->fresh()->notifications,
        );
    }

    public function test_completed_review_is_not_notified(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-03 18:00:00',
        );

        [$user] = $this->context();

        DailyReviewSession::query()->create([
            'user_id' => $user->id,
            'review_date' => '2026-09-03',
            'decisions_reviewed_at' => now(),
            'waiting_reviewed_at' => now(),
            'tasks_reviewed_at' => now(),
            'operations_reviewed_at' => now(),
            'completed_at' => now(),
        ]);

        Artisan::call(
            'daily-review:remind',
            ['--force' => true],
        );

        $this->assertCount(
            0,
            $user->fresh()->notifications,
        );
    }

    public function test_before_configured_hour_no_notification_is_created(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-03 16:00:00',
        );

        config()->set(
            'daily_review.reminder.enabled',
            true,
        );

        config()->set(
            'daily_review.reminder.hour',
            17,
        );

        [$user] = $this->context();

        Artisan::call(
            'daily-review:remind',
        );

        $this->assertCount(
            0,
            $user->fresh()->notifications,
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()
            ->create([
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

        return [$user, $organization];
    }
}
