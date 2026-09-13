<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFrontCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_and_edit_project_in_front(): void
    {
        [$user, $organization] = $this->context('member');

        $participant = User::factory()->create([
            'email' => 'participant-project-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($participant->id, [
            'role' => 'member',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/proyectos/nuevo')
            ->assertOk()
            ->assertSee('Nuevo proyecto')
            ->assertDontSee('/admin/proyectos', false);

        $create = $this->actingAs($user)->post('/proyectos', [
            'organization_id' => $organization->id,
            'name' => 'Proyecto FRONT',
            'description' => 'Proyecto creado sin Filament.',
            'type' => 'project',
            'horizon' => 'medium',
            'status' => 'active',
            'start_date' => '2026-09-13',
            'target_date' => '2026-12-31',
            'budget' => '12500.50',
            'currency' => 'PEN',
            'next_action' => 'Preparar entregable',
            'blockers' => 'Ninguno',
            'notes' => 'Operación FRONT',
            'is_private' => '1',
            'participants' => [$participant->id],
        ]);

        $create->assertRedirect();

        $project = Project::query()
            ->where('name', 'Proyecto FRONT')
            ->firstOrFail();

        $this->assertSame($organization->id, $project->organization_id);
        $this->assertSame('active', $project->status);
        $this->assertSame('12500.50', $project->budget);
        $this->assertTrue($project->is_private);
        $this->assertTrue(
            $project->participants()->whereKey($participant->id)->exists(),
        );

        $update = $this->actingAs($user)->post(
            '/proyectos/'.$project->id.'/editar',
            [
                'organization_id' => $organization->id,
                'name' => 'Proyecto FRONT actualizado',
                'description' => 'Proyecto actualizado.',
                'type' => 'goal',
                'horizon' => 'long',
                'status' => 'waiting',
                'start_date' => '2026-09-13',
                'target_date' => '2027-03-01',
                'budget' => '14000',
                'currency' => 'USD',
                'next_action' => 'Esperar aprobación',
                'blockers' => 'Aprobación externa',
                'notes' => 'Cambio completo FRONT',
                'participants' => [$participant->id],
            ],
        );

        $update->assertRedirect();

        $project->refresh();
        $this->assertSame('Proyecto FRONT actualizado', $project->name);
        $this->assertSame('goal', $project->type);
        $this->assertSame('waiting', $project->status);
        $this->assertFalse($project->is_private);

        $this->actingAs($user)
            ->get('/proyectos/'.$project->id.'/editar')
            ->assertOk()
            ->assertSee('Proyecto FRONT actualizado')
            ->assertSee('Guardar cambios')
            ->assertDontSee('/admin/proyectos', false);
    }

    public function test_viewer_can_read_project_but_cannot_mutate_project_or_create_task(): void
    {
        [$owner, $organization] = $this->context('owner');

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto solo lectura',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'currency' => 'PEN',
            'created_by' => $owner->id,
        ]);

        $viewer = User::factory()->create([
            'email' => 'viewer-project-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/proyectos/'.$project->id.'/editar')
            ->assertOk()
            ->assertSee('Proyecto solo lectura')
            ->assertSee('solo lectura')
            ->assertDontSee('Guardar cambios');

        $payload = [
            'organization_id' => $organization->id,
            'name' => 'No debe cambiar',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'currency' => 'PEN',
        ];

        $this->actingAs($viewer)
            ->post('/proyectos/'.$project->id.'/editar', $payload)
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/proyectos/'.$project->id.'/actualizar', [
                'status' => 'waiting',
                'next_action' => 'Intento',
                'blockers' => null,
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/proyectos/'.$project->id.'/tareas', [
                'title' => 'Tarea no permitida',
                'urgency' => 'normal',
            ])
            ->assertForbidden();

        $this->assertSame('Proyecto solo lectura', $project->fresh()->name);
        $this->assertSame(0, Task::query()->count());
    }

    public function test_existing_project_cannot_move_between_writable_organizations(): void
    {
        [$user, $organization] = $this->context('owner');

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto fijo',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'planned',
            'currency' => 'PEN',
            'created_by' => $user->id,
        ]);

        $second = Organization::query()->create([
            'name' => 'Segundo ámbito proyecto',
            'slug' => 'project-front-second',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $move = $this->actingAs($user)
            ->from('/proyectos/'.$project->id.'/editar')
            ->post('/proyectos/'.$project->id.'/editar', [
                'organization_id' => $second->id,
                'name' => 'Proyecto fijo',
                'type' => 'project',
                'horizon' => 'short',
                'status' => 'planned',
                'currency' => 'PEN',
            ]);

        $move
            ->assertRedirect('/proyectos/'.$project->id.'/editar')
            ->assertSessionHasErrors('organization_id');

        $this->assertSame($organization->id, $project->fresh()->organization_id);
    }

    public function test_foreign_project_is_not_available_in_front(): void
    {
        [$user] = $this->context('owner');

        $foreignUser = User::factory()->create([
            'email' => 'foreign-project-front@arpynet.test',
            'is_active' => true,
        ]);

        $this->actingAs($foreignUser);

        $foreign = Organization::query()->create([
            'name' => 'Proyecto ámbito ajeno',
            'slug' => 'project-front-foreign',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $foreignUser->id,
        ]);

        $project = Project::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'Proyecto oculto',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'currency' => 'PEN',
            'created_by' => $foreignUser->id,
        ]);

        $this->actingAs($user)
            ->get('/proyectos/'.$project->id.'/editar')
            ->assertForbidden();
    }

    private function context(string $role): array
    {
        $user = User::factory()->create([
            'email' => $role.'-project-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Proyectos FRONT',
            'slug' => 'project-front-'.$role,
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => $role,
            'is_default' => true,
            'is_active' => true,
        ]);

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
