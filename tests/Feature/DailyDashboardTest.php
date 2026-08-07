<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\DailyDashboard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_metrics_and_queries(): void
    {
        Carbon::setTestNow('2026-08-06 09:00:00');

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

        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Informe vencido',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'critical',
            'due_at' => now()->subDay(),
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Actividad de hoy',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => now()->setTime(17, 0),
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Esperando conformidad',
            'status' => 'waiting',
            'urgency' => 'normal',
            'impact' => 'high',
            'due_at' => now()->addDays(2),
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea planificada',
            'status' => 'pending',
            'urgency' => 'low',
            'impact' => 'low',
        ]);

        $dashboard = app(DailyDashboard::class);
        $metrics = $dashboard->metrics($user);

        $this->assertSame(1, $metrics['overdue']);
        $this->assertSame(1, $metrics['today']);
        $this->assertSame(1, $metrics['critical']);
        $this->assertSame(1, $metrics['waiting']);
        $this->assertSame(4, $metrics['open']);
        $this->assertSame(
            'Informe vencido',
            $dashboard->priorityTasks($user)->first()?->title,
        );
        $this->assertSame(2, $dashboard->upcomingTasks($user)->count());
    }
}
