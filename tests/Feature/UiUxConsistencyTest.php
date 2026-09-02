<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\DailyTaskPriority;
use App\Support\TaskPriorityCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiUxConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_priority_uses_core_calculator(): void
    {
        [$user, $organization] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Prioridad consistente',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' =>
                '2026-09-02 17:00:00',
            'created_by' => $user->id,
        ]);

        $now = CarbonImmutable::parse(
            '2026-09-02 10:00:00',
            'America/Lima',
        );

        $core = app(
            TaskPriorityCalculator::class,
        )->calculate(
            $task,
            $now,
        );

        $this->assertSame(
            $core['score'],
            DailyTaskPriority::score(
                $task,
                $now,
            ),
        );

        $this->assertSame(
            $core['band'],
            DailyTaskPriority::band(
                $task,
                $now,
            ),
        );
    }

    public function test_quick_capture_defaults_to_normal_vocabulary(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/captura')
            ->assertOk()
            ->assertSee('Opciones avanzadas')
            ->assertSee('Normal')
            ->assertSee('Crítica')
            ->assertSee('Crítico');
    }

    public function test_mi_dia_has_action_first_language(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Prioridad ahora')
            ->assertSee('Críticos')
            ->assertSee('Planificados')
            ->assertSee('Más');
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
