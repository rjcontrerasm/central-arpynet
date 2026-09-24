<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyOpsFocusTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_mi_dia_exposes_a_clear_daily_focus_summary(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-24 09:00:00',
        );

        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Foco del día')
            ->assertSee('Sin urgencias en tu bandeja')
            ->assertSee('Abrir agenda')
            ->assertSee('+ Capturar');
    }

    public function test_critical_work_is_promoted_before_supporting_context(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-24 09:00:00',
        );

        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Resolver incidente urgente',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('elemento requiere')
            ->assertSee('atención inmediata')
            ->assertSee('Ver críticos')
            ->assertSeeInOrder([
                'Foco del día',
                'Prioridad ahora',
                'Proyectos a revisar',
                'Órdenes y servicios',
            ]);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'daily-focus@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Daily Focus',
            'slug' => 'arpynet-daily-focus',
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

        $user->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
