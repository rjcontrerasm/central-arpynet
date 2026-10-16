<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkTeam;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTaskActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_user_can_complete_task(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'complete'],
            )
            ->assertRedirect('/mi-dia');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
    }

    public function test_user_can_complete_task_with_json_response_and_undo_metadata(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $response = $this->actingAs($user)
            ->postJson(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'complete'],
            )
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'task_id' => $task->id,
                'action' => 'complete',
            ])
            ->assertJsonPath(
                'undo.url',
                route('global-undo.restore'),
            );

        $undoId = (int) $response->json(
            'undo.id',
        );

        $this->assertGreaterThan(
            0,
            $undoId,
        );

        $this->assertSame(
            'completed',
            $task->fresh()->status,
        );

        $this->postJson(
            '/deshacer',
            ['undo_id' => $undoId],
        )
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Acción deshecha.',
            ]);

        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );
    }

    public function test_live_flag_forces_json_without_relying_on_accept_header(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'complete',
                    '_live' => 1,
                ],
            )
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/json',
            )
            ->assertJson([
                'ok' => true,
                'task_id' => $task->id,
                'action' => 'complete',
            ]);
    }

    public function test_live_undo_returns_previous_stack_action(): void
    {
        [$user, $organization] = $this->context();

        $first = $this->task(
            $user,
            $organization,
        );

        $second = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Segunda tarea undo',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);

        $firstResponse = $this->actingAs($user)
            ->postJson(
                "/mi-dia/tareas/{$first->id}/accion",
                ['action' => 'complete'],
            )
            ->assertOk();

        $firstUndoId = (int) $firstResponse
            ->json('undo.id');

        $secondResponse = $this->postJson(
            "/mi-dia/tareas/{$second->id}/accion",
            ['action' => 'complete'],
        )
            ->assertOk();

        $secondUndoId = (int) $secondResponse
            ->json('undo.id');

        $this->postJson(
            '/deshacer',
            ['undo_id' => $secondUndoId],
        )
            ->assertOk()
            ->assertJsonPath(
                'next_undo.id',
                $firstUndoId,
            );

        $this->assertSame(
            'pending',
            $second->fresh()->status,
        );

        $this->assertSame(
            'completed',
            $first->fresh()->status,
        );

        $this->postJson(
            '/deshacer',
            ['undo_id' => $firstUndoId],
        )
            ->assertOk()
            ->assertJsonPath(
                'next_undo',
                null,
            );

        $this->assertSame(
            'pending',
            $first->fresh()->status,
        );
    }

    public function test_completing_task_can_create_linked_follow_up_and_undo_both(): void
    {
        Carbon::setTestNow(
            '2026-10-15 10:00:00',
        );

        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cotización PCM',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'start_date' => '2026-10-15',
            'created_by' => $user->id,
            'is_private' => false,
        ]);

        $team = WorkTeam::query()->create([
            'home_organization_id' => $organization->id,
            'name' => 'Administración',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $team->users()->attach(
            $user->id,
            [
                'role' => 'lead',
                'is_active' => true,
            ],
        );

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'title' => 'Cotizar twitter a la PCM',
            'next_action' =>
                'Seguimiento de cotización de twitter a la PCM',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'normal',
            'due_at' => now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'visibility_scope' => 'teams',
        ]);

        $task->workTeams()->sync([$team->id]);

        $response = $this->actingAs($user)
            ->postJson(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'complete',
                    'follow_up_mode' => 'tomorrow',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'follow_up.created',
                true,
            )
            ->assertJsonPath(
                'follow_up.title',
                'Seguimiento de cotización de twitter a la PCM',
            )
            ->assertJsonPath(
                'follow_up.due_date',
                '16/10/2026',
            );

        $task->refresh();

        $this->assertSame(
            'completed',
            $task->status,
        );

        $followUp = Task::query()
            ->where('parent_task_id', $task->id)
            ->firstOrFail();

        $this->assertSame(
            $organization->id,
            $followUp->organization_id,
        );
        $this->assertSame(
            $project->id,
            $followUp->project_id,
        );
        $this->assertSame(
            $user->id,
            $followUp->assigned_to,
        );
        $this->assertSame(
            'teams',
            $followUp->visibility_scope,
        );
        $this->assertSame(
            'next_action',
            $followUp->source,
        );
        $this->assertSame(
            'central_next_action',
            $followUp->external_system,
        );
        $this->assertSame(
            '2026-10-16 17:00:00',
            $followUp->due_at->format(
                'Y-m-d H:i:s',
            ),
        );

        $this->assertTrue(
            $followUp->workTeams()
                ->whereKey($team->id)
                ->exists(),
        );

        $this->get(
            '/mi-dia?q='
            .urlencode(
                'Seguimiento de cotización de twitter a la PCM',
            ),
        )
            ->assertOk()
            ->assertSee('Continuación de:')
            ->assertSee('Cotizar twitter a la PCM');

        $undoId = (int) $response->json(
            'undo.id',
        );

        $this->postJson(
            '/deshacer',
            ['undo_id' => $undoId],
        )
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Acción deshecha.',
            ]);

        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );

        $this->assertSoftDeleted(
            'tasks',
            ['id' => $followUp->id],
        );
    }

    public function test_completion_can_skip_creating_prepared_next_action(): void
    {
        [$user, $organization] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Cotizar servicio',
            'next_action' => 'Hacer seguimiento',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'complete',
                    'follow_up_mode' => 'none',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'follow_up',
                null,
            );

        $this->assertSame(
            'completed',
            $task->fresh()->status,
        );

        $this->assertSame(
            0,
            Task::query()
                ->where(
                    'parent_task_id',
                    $task->id,
                )
                ->count(),
        );
    }

    public function test_custom_follow_up_date_is_applied(): void
    {
        Carbon::setTestNow(
            '2026-10-15 10:00:00',
        );

        [$user, $organization] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Enviar propuesta',
            'next_action' => 'Llamar para confirmar recepción',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => now(),
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'complete',
                    'follow_up_mode' => 'custom',
                    'follow_up_due_date' => '2026-10-20',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'follow_up.due_date',
                '20/10/2026',
            );

        $followUp = Task::query()
            ->where('parent_task_id', $task->id)
            ->firstOrFail();

        $this->assertSame(
            '2026-10-20 17:00:00',
            $followUp->due_at->format(
                'Y-m-d H:i:s',
            ),
        );
    }

    public function test_team_member_without_company_membership_can_create_and_undo_follow_up(): void
    {
        Carbon::setTestNow(
            '2026-10-15 10:00:00',
        );

        [$owner, $organization] = $this->context();

        $marisol = User::factory()->create([
            'name' => 'Marisol',
            'email' => 'arpynetsac@gmail.com',
            'is_active' => true,
        ]);

        $team = WorkTeam::query()->create([
            'home_organization_id' => $organization->id,
            'name' => 'Administración',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $team->users()->attach(
            $marisol->id,
            [
                'role' => 'member',
                'is_active' => true,
            ],
        );

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Facturar servicio',
            'next_action' => 'Confirmar pago con cliente',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'due_at' => now(),
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
            'visibility_scope' => 'teams',
        ]);

        $task->workTeams()->sync([$team->id]);

        $this->assertFalse(
            $marisol->canAccessOrganization(
                $organization->id,
            ),
        );

        $response = $this->actingAs($marisol)
            ->postJson(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'complete',
                    'follow_up_mode' => 'tomorrow',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'follow_up.created',
                true,
            );

        $followUp = Task::query()
            ->where('parent_task_id', $task->id)
            ->firstOrFail();

        $this->assertSame(
            $owner->id,
            $followUp->assigned_to,
        );

        $this->assertTrue(
            $followUp->workTeams()
                ->whereKey($team->id)
                ->exists(),
        );

        $this->postJson(
            '/deshacer',
            [
                'undo_id' => (int) $response->json(
                    'undo.id',
                ),
            ],
        )
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );

        $this->assertSoftDeleted(
            'tasks',
            ['id' => $followUp->id],
        );
    }

    public function test_user_can_move_task_to_tomorrow(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'tomorrow'],
            )
            ->assertRedirect('/mi-dia');

        $this->assertSame(
            '2026-09-02 17:00:00',
            $task->fresh()
                ->due_at
                ->format('Y-m-d H:i:s'),
        );
    }

    public function test_user_can_move_task_one_week(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'next_week'],
            )
            ->assertRedirect('/mi-dia');

        $this->assertSame(
            '2026-09-08 17:00:00',
            $task->fresh()
                ->due_at
                ->format('Y-m-d H:i:s'),
        );
    }

    public function test_user_can_complete_task_from_overdue_filter(): void
    {
        [$user, $organization] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                [
                    'action' => 'complete',
                    'priority' => 'overdue',
                    'view' => 'mine',
                    'recurring_rule' => 77,
                ],
            )
            ->assertRedirect(route('daily-ops.show', [
                'priority' => 'overdue',
                'view' => 'mine',
                'recurring_rule' => 77,
            ]));

        $this->assertSame(
            'completed',
            $task->fresh()->status,
        );
    }

    public function test_foreign_task_is_forbidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa ajena',
            'slug' => 'empresa-ajena',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $task = Task::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'Tarea ajena',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(
                "/mi-dia/tareas/{$task->id}/accion",
                ['action' => 'complete'],
            )
            ->assertForbidden();

        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );
    }

    private function task(
        User $user,
        Organization $organization,
    ): Task {
        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea de prueba',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);
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
