<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectOpsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_project_workspace_shows_operational_signals(): void
    {
        Carbon::setTestNow('2026-09-06 09:00:00');
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Renovar plataforma CENTRAL',
            'type' => 'project',
            'horizon' => 'medium',
            'status' => 'active',
            'target_date' => '2026-09-20',
            'blockers' => 'Esperando validación del proveedor',
            'last_activity_at' => now()->subDays(31),
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'title' => 'Diseñar solución',
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'title' => 'Implementar solución',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/proyectos?focus=all')
            ->assertOk()
            ->assertSee('Renovar plataforma CENTRAL')
            ->assertSee('Avance 50%')
            ->assertSee('Estancado')
            ->assertSee('Tiene bloqueos')
            ->assertSee('Sin siguiente acción')
            ->assertSee('Acciones rápidas')
            ->assertSee('Crear tarea vinculada');
    }

    public function test_project_workspace_filters_without_next_action(): void
    {
        [$user, $organization] = $this->context();

        Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto sin acción',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto con acción',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' => 'Llamar al proveedor',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/proyectos?focus=no_next_action')
            ->assertOk()
            ->assertSee('Proyecto sin acción')
            ->assertDontSee('Proyecto con acción');
    }

    public function test_project_workspace_rejects_foreign_scope(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Organización ajena',
            'slug' => 'organizacion-ajena-project-ops',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/proyectos?scope='.$foreign->id)
            ->assertForbidden();
    }

    public function test_project_can_be_updated_from_workspace(): void
    {
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto editable',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'planned',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(
                '/proyectos/'.$project->id.'/actualizar',
                [
                    'status' => 'active',
                    'next_action' => 'Coordinar reunión de inicio',
                    'blockers' => 'Esperando contrato',
                    'focus' => 'all',
                ],
            )
            ->assertRedirect();

        $project->refresh();

        $this->assertSame('active', $project->status);
        $this->assertSame(
            'Coordinar reunión de inicio',
            $project->next_action,
        );
        $this->assertSame(
            'Esperando contrato',
            $project->blockers,
        );
    }

    public function test_task_can_be_created_and_linked_from_project(): void
    {
        Carbon::setTestNow('2026-09-06 10:00:00');
        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto con tarea',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(
                '/proyectos/'.$project->id.'/tareas',
                [
                    'title' => 'Preparar cronograma',
                    'due_date' => '2026-09-08',
                    'urgency' => 'high',
                    'focus' => 'all',
                ],
            )
            ->assertRedirect();

        $task = Task::query()
            ->where('project_id', $project->id)
            ->where('title', 'Preparar cronograma')
            ->firstOrFail();

        $this->assertSame(
            $organization->id,
            $task->organization_id,
        );
        $this->assertSame('pending', $task->status);
        $this->assertSame('high', $task->urgency);
        $this->assertSame('project_ops', $task->source);
        $this->assertSame(
            '2026-09-08 17:00:00',
            $task->due_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_foreign_project_cannot_be_changed(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa externa',
            'slug' => 'empresa-externa-project-actions',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $project = Project::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'Proyecto ajeno',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(
                '/proyectos/'.$project->id.'/actualizar',
                [
                    'status' => 'waiting',
                    'next_action' => 'No permitido',
                ],
            )
            ->assertForbidden();

        $this->actingAs($user)
            ->post(
                '/proyectos/'.$project->id.'/tareas',
                [
                    'title' => 'Tarea no permitida',
                    'urgency' => 'normal',
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $project->id,
            'title' => 'Tarea no permitida',
        ]);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-project-ops',
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

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
