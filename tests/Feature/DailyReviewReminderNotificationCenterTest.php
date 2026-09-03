<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DailyReviewReminderNotificationCenterTest
    extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_internal_reminder_is_visible_in_notification_center(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-03 18:00:00',
        );

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

        Artisan::call(
            'daily-review:remind',
            ['--force' => true],
        );

        $this->actingAs($user)
            ->get('/notificaciones')
            ->assertOk()
            ->assertSee(
                'Revisión diaria pendiente',
            )
            ->assertSee(
                'Tu revisión sigue incompleta',
            );
    }
}
