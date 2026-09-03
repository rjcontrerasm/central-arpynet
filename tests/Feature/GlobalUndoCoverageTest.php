<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalUndoCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_edit_and_next_action_register_global_undo(): void
    {
        [$user, $organization, $task] =
            $this->context();

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/editar",
                [
                    'organization_id' =>
                        $organization->id,
                    'due_date' => '2026-09-20',
                    'urgency' => 'high',
                    'impact' => 'high',
                ],
            )
            ->assertRedirect('/mi-dia');

        $this->get('/resumen')
            ->assertOk()
            ->assertSee('Tarea actualizada.')
            ->assertSee('Deshacer');

        $this->post('/deshacer')
            ->assertRedirect('/mi-dia');

        $task->refresh();

        $this->assertSame(
            'normal',
            $task->urgency,
        );

        $this->actingAs($user)
            ->post(
                "/tareas/{$task->id}/proxima-accion",
                [
                    'next_action' =>
                        'Enviar correo',
                    'return_to' => 'daily',
                ],
            )
            ->assertRedirect('/mi-dia');

        $this->post('/deshacer')
            ->assertRedirect('/mi-dia');

        $this->assertNull(
            $task->fresh()->next_action,
        );
    }

    public function test_quick_capture_creation_can_be_undone(): void
    {
        [$user, $organization] =
            $this->context();

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'Tarea temporal',
                'due_mode' => 'none',
                'urgency' => 'normal',
                'impact' => 'normal',
            ])
            ->assertRedirect('/captura');

        $task = Task::query()
            ->where(
                'title',
                'Tarea temporal',
            )
            ->firstOrFail();

        $this->post('/deshacer')
            ->assertRedirect('/captura');

        $this->assertSoftDeleted(
            'tasks',
            ['id' => $task->id],
        );
    }

    public function test_project_conversion_can_be_safely_undone(): void
    {
        [$user, , $task] =
            $this->context();

        $this->actingAs($user)
            ->post(
                "/tareas/{$task->id}/convertir",
                ['target' => 'project'],
            )
            ->assertRedirect('/mi-dia');

        $projectId =
            $task->fresh()->project_id;

        $this->assertNotNull(
            $projectId,
        );

        $this->post('/deshacer')
            ->assertRedirect('/mi-dia');

        $this->assertNull(
            $task->fresh()->project_id,
        );

        $this->assertSoftDeleted(
            'projects',
            ['id' => $projectId],
        );
    }

    public function test_service_conversion_can_be_safely_undone(): void
    {
        [$user, $organization, $task] =
            $this->context();

        $client = Client::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Cliente undo',
                'is_active' => true,
                'created_by' =>
                    $user->id,
            ]);

        $this->actingAs($user)
            ->post(
                "/tareas/{$task->id}/convertir",
                [
                    'target' => 'service',
                    'client_id' =>
                        $client->id,
                ],
            )
            ->assertRedirect('/mi-dia');

        $serviceId = (int) str_replace(
            'service:',
            '',
            (string) $task->fresh()
                ->external_id,
        );

        $this->post('/deshacer')
            ->assertRedirect('/mi-dia');

        $task->refresh();

        $this->assertSame(
            'pending',
            $task->status,
        );

        $this->assertNull(
            $task->external_id,
        );

        $this->assertSoftDeleted(
            'service_orders',
            ['id' => $serviceId],
        );
    }

    private function context(): array
    {
        CarbonImmutable::setTestNow(
            '2026-09-03 10:00:00',
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

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea undo global',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'created_by' => $user->id,
        ]);

        return [
            $user,
            $organization,
            $task,
        ];
    }
}
