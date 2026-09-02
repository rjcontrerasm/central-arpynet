<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartQuickCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_tomorrow_and_urgent_are_inferred_from_explicit_prefixes(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'mañana urgente Enviar informe SUNARP',
                'due_mode' => 'today',
                'urgency' => 'normal',
                'impact' => 'normal',
            ])
            ->assertRedirect('/captura')
            ->assertSessionHas(
                'quick_capture_success',
                fn (string $message): bool =>
                    str_contains(
                        $message,
                        'mañana',
                    )
                    && str_contains(
                        $message,
                        'urgente',
                    ),
            );

        $task = Task::query()
            ->where(
                'title',
                'Enviar informe SUNARP',
            )
            ->firstOrFail();

        $this->assertSame(
            'high',
            $task->urgency,
        );

        $this->assertSame(
            '2026-09-03',
            $task->due_at?->format(
                'Y-m-d',
            ),
        );
    }

    public function test_critical_prefix_produces_a_real_critical_task_for_today(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'crítico Revisar caída API',
                'due_mode' => 'none',
                'urgency' => 'normal',
                'impact' => 'normal',
            ])
            ->assertRedirect('/captura');

        $task = Task::query()
            ->where(
                'title',
                'Revisar caída API',
            )
            ->firstOrFail();

        $this->assertSame(
            'critical',
            $task->urgency,
        );

        $this->assertSame(
            'critical',
            $task->impact,
        );

        $this->assertSame(
            'critical',
            $task->priority_band,
        );

        $this->assertSame(
            '2026-09-02',
            $task->due_at?->format(
                'Y-m-d',
            ),
        );
    }

    public function test_waiting_prefix_creates_waiting_task_without_inventing_follow_up_date(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'esperando respuesta proveedor',
                'due_mode' => 'today',
                'urgency' => 'normal',
                'impact' => 'normal',
            ])
            ->assertRedirect('/captura');

        $task = Task::query()
            ->where(
                'title',
                'Esperando respuesta proveedor',
            )
            ->firstOrFail();

        $this->assertSame(
            'waiting',
            $task->status,
        );

        $this->assertNotNull(
            $task->waiting_since,
        );

        $this->assertNull(
            $task->waiting_until,
        );

        $this->assertNull(
            $task->due_at,
        );
    }

    public function test_waiting_prefix_can_use_an_explicit_tomorrow_follow_up(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [$user, $organization] =
            $this->context();

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'mañana esperando respuesta proveedor',
                'due_mode' => 'today',
                'urgency' => 'normal',
                'impact' => 'normal',
            ])
            ->assertRedirect('/captura');

        $task = Task::query()
            ->where(
                'title',
                'Esperando respuesta proveedor',
            )
            ->firstOrFail();

        $this->assertSame(
            'waiting',
            $task->status,
        );

        $this->assertSame(
            '2026-09-03',
            $task->waiting_until?->format(
                'Y-m-d',
            ),
        );

        $this->assertNull(
            $task->due_at,
        );
    }

    public function test_explicit_organization_prefix_can_switch_to_an_allowed_scope(): void
    {
        [$user, $organization] =
            $this->context();

        $personal =
            Organization::query()->create([
                'name' => 'Personal',
                'slug' => 'personal',
                'category' => 'personal',
                'timezone' =>
                    'America/Lima',
                'is_active' => true,
                'created_by' => $user->id,
            ]);

        $personal->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' =>
                    $organization->id,
                'title' =>
                    '@Personal mañana Comprar filtros',
                'due_mode' => 'today',
                'urgency' => 'normal',
                'impact' => 'normal',
            ])
            ->assertRedirect('/captura');

        $this->assertDatabaseHas(
            'tasks',
            [
                'organization_id' =>
                    $personal->id,
                'title' =>
                    'Comprar filtros',
            ],
        );
    }

    public function test_normal_capture_remains_unchanged_without_explicit_prefix(): void
    {
        [$user, $organization] =
            $this->context();

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'Revisar propuesta comercial',
                'due_mode' =>
                    'next_week',
                'urgency' => 'low',
                'impact' => 'high',
            ])
            ->assertRedirect('/captura');

        $this->assertDatabaseHas(
            'tasks',
            [
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'Revisar propuesta comercial',
                'urgency' => 'low',
                'impact' => 'high',
            ],
        );
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

        return [
            $user,
            $organization,
        ];
    }
}
