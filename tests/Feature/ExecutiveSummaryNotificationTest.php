<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveSummaryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_center_is_available(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/notificaciones')
            ->assertOk()
            ->assertSee('Notificaciones');
    }

    public function test_daily_summary_command_creates_notification(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea crítica de notificación',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $this->artisan('summary:deliver today')
            ->assertExitCode(0);

        $this->assertCount(
            1,
            $user->fresh()->notifications,
        );

        $notification =
            $user->fresh()->notifications->first();

        $this->assertSame(
            'today',
            $notification->data['period'],
        );

        $this->assertSame(
            'Resumen de hoy',
            $notification->data['title'],
        );
    }

    public function test_same_summary_is_not_duplicated_same_day(): void
    {
        [$user] = $this->context();

        $this->artisan('summary:deliver today')
            ->assertExitCode(0);

        $this->artisan('summary:deliver today')
            ->assertExitCode(0);

        $this->assertCount(
            1,
            $user->fresh()->notifications,
        );
    }

    public function test_notification_can_be_marked_read(): void
    {
        [$user] = $this->context();

        $this->artisan('summary:deliver today')
            ->assertExitCode(0);

        $notification =
            $user->fresh()->notifications->first();

        $this->actingAs($user)
            ->post(
                "/notificaciones/{$notification->id}/leer",
            )
            ->assertRedirect();

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
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

        return [$user, $organization];
    }
}
