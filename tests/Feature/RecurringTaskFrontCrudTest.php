<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\RecurringTaskRule;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTaskFrontCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_member_can_create_and_edit_recurring_task_in_front(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 09:00:00');
        [$user, $organization] = $this->context('member');

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto recurrente',
            'type' => 'work',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/tareas-recurrentes')
            ->assertOk()
            ->assertSee('Tareas recurrentes')
            ->assertDontSee('/admin/tareas-recurrentes', false);

        $create = $this->actingAs($user)->post('/tareas-recurrentes', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'assigned_to' => $user->id,
            'title' => 'Revisión diaria FRONT',
            'description' => 'Generada desde CENTRAL Front.',
            'next_action' => 'Validar tablero',
            'frequency' => 'daily',
            'anchor_date' => '2026-09-13',
            'end_date' => '2026-10-13',
            'create_days_before' => 0,
            'due_time' => '17:00',
            'urgency' => 'high',
            'impact' => 'normal',
            'is_private' => '1',
            'is_active' => '1',
        ]);

        $create->assertRedirect();

        $rule = RecurringTaskRule::query()
            ->where('title', 'Revisión diaria FRONT')
            ->firstOrFail();

        $this->assertSame($organization->id, $rule->organization_id);
        $this->assertSame($project->id, $rule->project_id);
        $this->assertSame($user->id, $rule->assigned_to);
        $this->assertTrue($rule->is_private);
        $this->assertTrue($rule->is_active);

        $task = Task::query()
            ->where('external_system', 'central_recurring_task')
            ->firstOrFail();

        $this->assertSame('Revisión diaria FRONT', $task->title);
        $this->assertSame($organization->id, $task->organization_id);
        $this->assertSame($project->id, $task->project_id);

        $update = $this->actingAs($user)->post(
            '/tareas-recurrentes/'.$rule->id.'/editar',
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'assigned_to' => $user->id,
                'title' => 'Revisión diaria actualizada',
                'next_action' => 'Revisar pendientes',
                'frequency' => 'weekly',
                'anchor_date' => '2026-09-13',
                'end_date' => '2026-10-13',
                'create_days_before' => 1,
                'due_time' => '16:00',
                'urgency' => 'critical',
                'impact' => 'high',
            ],
        );

        $update->assertRedirect();

        $rule->refresh();
        $this->assertSame('Revisión diaria actualizada', $rule->title);
        $this->assertSame('weekly', $rule->frequency);
        $this->assertSame(1, $rule->create_days_before);
        $this->assertSame('16:00', $rule->due_time);
        $this->assertFalse($rule->is_private);
        $this->assertFalse($rule->is_active);
    }

    public function test_viewer_can_read_but_cannot_create_or_update_recurring_task(): void
    {
        [$owner, $organization] = $this->context('owner');

        $rule = RecurringTaskRule::withoutEvents(
            fn () => RecurringTaskRule::query()->create([
                'organization_id' => $organization->id,
                'title' => 'Regla solo lectura',
                'frequency' => 'weekly',
                'anchor_date' => now()->toDateString(),
                'create_days_before' => 0,
                'due_time' => '17:00',
                'urgency' => 'normal',
                'impact' => 'normal',
                'is_active' => false,
                'assigned_to' => $owner->id,
                'created_by' => $owner->id,
            ]),
        );

        $viewer = User::factory()->create([
            'email' => 'viewer-recurring-task-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/tareas-recurrentes/'.$rule->id.'/editar')
            ->assertOk()
            ->assertSee('Regla solo lectura')
            ->assertSee('solo lectura')
            ->assertDontSee('Guardar cambios');

        $payload = [
            'organization_id' => $organization->id,
            'title' => 'No permitido',
            'frequency' => 'weekly',
            'anchor_date' => now()->toDateString(),
            'create_days_before' => 0,
            'due_time' => '17:00',
            'urgency' => 'normal',
            'impact' => 'normal',
            'is_active' => '1',
        ];

        $this->actingAs($viewer)
            ->post('/tareas-recurrentes', $payload)
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post('/tareas-recurrentes/'.$rule->id.'/editar', $payload)
            ->assertForbidden();

        $this->assertSame('Regla solo lectura', $rule->fresh()->title);
    }

    public function test_recurring_task_rejects_cross_organization_project_and_move(): void
    {
        [$user, $organization] = $this->context('owner');

        $second = Organization::query()->create([
            'name' => 'Segundo ámbito recurrencia',
            'slug' => 'recurring-task-second',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $second->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => false,
            'is_active' => true,
        ]);

        $foreignProject = Project::query()->create([
            'organization_id' => $second->id,
            'name' => 'Proyecto segundo ámbito',
            'type' => 'work',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $invalid = $this->actingAs($user)
            ->from('/tareas-recurrentes/nueva')
            ->post('/tareas-recurrentes', [
                'organization_id' => $organization->id,
                'project_id' => $foreignProject->id,
                'title' => 'Relación inválida',
                'frequency' => 'weekly',
                'anchor_date' => now()->toDateString(),
                'create_days_before' => 0,
                'due_time' => '17:00',
                'urgency' => 'normal',
                'impact' => 'normal',
            ]);

        $invalid
            ->assertRedirect('/tareas-recurrentes/nueva')
            ->assertSessionHasErrors('project_id');

        $rule = RecurringTaskRule::withoutEvents(
            fn () => RecurringTaskRule::query()->create([
                'organization_id' => $organization->id,
                'title' => 'Regla fija',
                'frequency' => 'weekly',
                'anchor_date' => now()->toDateString(),
                'create_days_before' => 0,
                'due_time' => '17:00',
                'urgency' => 'normal',
                'impact' => 'normal',
                'is_active' => false,
                'assigned_to' => $user->id,
                'created_by' => $user->id,
            ]),
        );

        $move = $this->actingAs($user)
            ->from('/tareas-recurrentes/'.$rule->id.'/editar')
            ->post('/tareas-recurrentes/'.$rule->id.'/editar', [
                'organization_id' => $second->id,
                'title' => 'Regla fija',
                'frequency' => 'weekly',
                'anchor_date' => now()->toDateString(),
                'create_days_before' => 0,
                'due_time' => '17:00',
                'urgency' => 'normal',
                'impact' => 'normal',
            ]);

        $move
            ->assertRedirect('/tareas-recurrentes/'.$rule->id.'/editar')
            ->assertSessionHasErrors('organization_id');

        $this->assertSame($organization->id, $rule->fresh()->organization_id);
    }

    public function test_foreign_recurring_task_is_forbidden(): void
    {
        [$user] = $this->context('owner');

        $foreignUser = User::factory()->create([
            'email' => 'foreign-recurring-task-front@arpynet.test',
            'is_active' => true,
        ]);

        $foreign = Organization::query()->create([
            'name' => 'Ámbito recurrencia ajeno',
            'slug' => 'recurring-task-foreign',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $foreignUser->id,
        ]);

        $foreign->users()->attach($foreignUser->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $rule = RecurringTaskRule::withoutEvents(
            fn () => RecurringTaskRule::query()->create([
                'organization_id' => $foreign->id,
                'title' => 'Regla oculta',
                'frequency' => 'weekly',
                'anchor_date' => now()->toDateString(),
                'create_days_before' => 0,
                'due_time' => '17:00',
                'urgency' => 'normal',
                'impact' => 'normal',
                'is_active' => false,
                'assigned_to' => $foreignUser->id,
                'created_by' => $foreignUser->id,
            ]),
        );

        $this->actingAs($user)
            ->get('/tareas-recurrentes/'.$rule->id.'/editar')
            ->assertForbidden();
    }

    private function context(string $role): array
    {
        $user = User::factory()->create([
            'email' => $role.'-recurring-task-front@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Recurrentes FRONT',
            'slug' => 'recurring-task-front-'.$role,
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
