<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JarvisTaskProposalPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_jarvis_shows_human_task_action_preparation_for_prioritized_task(): void
    {
        [$user, $organization] =
            $this->context();

        $task = $this->task(
            $user,
            $organization,
            [
                'title' =>
                    'Tarea priorizada Jarvis v9',
                'due_at' =>
                    now()->subDay(),
            ],
        );

        $this->actingAs($user)
            ->get(
                '/jarvis?scope='
                .$organization->id,
            )
            ->assertOk()
            ->assertSee(
                'Acciones de tarea disponibles con preparación humana',
            )
            ->assertSee(
                'Marcar como hecha',
            )
            ->assertSee(
                'Marcar en curso',
            )
            ->assertSee(
                'Mover a hoy',
            )
            ->assertSee(
                'Mover a mañana',
            )
            ->assertSee(
                'Mover una semana',
            )
            ->assertSee(
                'Preparar acción',
            );

        $this->assertDatabaseCount(
            'agent_action_proposals',
            0,
        );

        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );
    }

    public function test_human_can_prepare_all_supported_task_actions_without_mutating_task(): void
    {
        [$user, $organization] =
            $this->context();

        $task = $this->task(
            $user,
            $organization,
            [
                'title' =>
                    'Tarea acciones Jarvis v9',
                'due_at' =>
                    now()->addDays(3),
            ],
        );

        $originalStatus =
            $task->status;

        $originalDue =
            $task->due_at?->toIso8601String();

        $actions = [
            'complete',
            'start',
            'today',
            'tomorrow',
            'next_week',
        ];

        foreach ($actions as $action) {
            $this->actingAs($user)
                ->post(
                    '/jarvis/preparar-propuesta',
                    [
                        'subject_type' =>
                            'task',
                        'subject_id' =>
                            $task->id,
                        'action' =>
                            $action,
                    ],
                )
                ->assertRedirect();
        }

        $this->assertSame(
            $actions,
            AgentActionProposal::query()
                ->orderBy('id')
                ->pluck('action_key')
                ->all(),
        );

        $this->assertDatabaseCount(
            'agent_action_proposals',
            5,
        );

        $fresh = $task->fresh();

        $this->assertSame(
            $originalStatus,
            $fresh->status,
        );

        $this->assertSame(
            $originalDue,
            $fresh->due_at?->toIso8601String(),
        );

        $this->assertNull(
            $fresh->completed_at,
        );
    }

    public function test_task_preparation_becomes_stale_after_terminal_state_change(): void
    {
        [$user, $organization] =
            $this->context();

        $task = $this->task(
            $user,
            $organization,
            [
                'title' =>
                    'Tarea cerrada antes del clic',
                'status' =>
                    'completed',
                'completed_at' =>
                    now(),
            ],
        );

        $this->actingAs($user)
            ->post(
                '/jarvis/preparar-propuesta',
                [
                    'subject_type' =>
                        'task',
                    'subject_id' =>
                        $task->id,
                    'action' =>
                        'tomorrow',
                ],
            )
            ->assertRedirect()
            ->assertSessionHas(
                'agent_proposal_message',
                'La tarea cambió desde la lectura de Jarvis y esa preparación ya no es pertinente. Recarga Jarvis antes de continuar.',
            );

        $this->assertDatabaseCount(
            'agent_action_proposals',
            0,
        );

        $this->assertSame(
            'completed',
            $task->fresh()->status,
        );
    }

    public function test_start_preparation_is_stale_when_task_is_already_in_progress(): void
    {
        [$user, $organization] =
            $this->context();

        $task = $this->task(
            $user,
            $organization,
            [
                'title' =>
                    'Tarea ya iniciada',
                'status' =>
                    'in_progress',
            ],
        );

        $this->actingAs($user)
            ->post(
                '/jarvis/preparar-propuesta',
                [
                    'subject_type' =>
                        'task',
                    'subject_id' =>
                        $task->id,
                    'action' =>
                        'start',
                ],
            )
            ->assertRedirect()
            ->assertSessionHas(
                'agent_proposal_message',
            );

        $this->assertDatabaseCount(
            'agent_action_proposals',
            0,
        );
    }

    public function test_task_cannot_use_project_or_service_prepare_actions(): void
    {
        [$user, $organization] =
            $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $this->actingAs($user)
            ->from('/jarvis')
            ->post(
                '/jarvis/preparar-propuesta',
                [
                    'subject_type' =>
                        'task',
                    'subject_id' =>
                        $task->id,
                    'action' =>
                        'project.next_action.set',
                    'next_action' =>
                        'No corresponde',
                ],
            )
            ->assertRedirect('/jarvis')
            ->assertSessionHasErrors(
                'action',
            );

        $this->assertDatabaseCount(
            'agent_action_proposals',
            0,
        );
    }

    public function test_unauthorized_task_cannot_be_prepared(): void
    {
        [$user] = $this->context();

        $other =
            User::factory()->create();

        $organization =
            Organization::query()->create([
                'name' =>
                    'Organización ajena v9',
                'slug' =>
                    'organizacion-ajena-v9',
                'category' =>
                    'company',
                'timezone' =>
                    'America/Lima',
                'is_active' => true,
                'created_by' =>
                    $other->id,
            ]);

        $organization
            ->users()
            ->attach(
                $other->id,
                [
                    'role' => 'owner',
                    'is_default' => true,
                    'is_active' => true,
                ],
            );

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' =>
                'Tarea no autorizada v9',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'created_by' =>
                $other->id,
        ]);

        $this->actingAs($user)
            ->post(
                '/jarvis/preparar-propuesta',
                [
                    'subject_type' =>
                        'task',
                    'subject_id' =>
                        $task->id,
                    'action' =>
                        'start',
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'agent_action_proposals',
            0,
        );
    }

    private function task(
        User $user,
        Organization $organization,
        array $overrides = [],
    ): Task {
        return Task::query()->create(
            array_merge(
                [
                    'organization_id' =>
                        $organization->id,
                    'title' =>
                        'Tarea Jarvis v9',
                    'status' =>
                        'pending',
                    'urgency' =>
                        'high',
                    'impact' =>
                        'high',
                    'created_by' =>
                        $user->id,
                ],
                $overrides,
            ),
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
                'name' =>
                    'ARPYNET Jarvis Task Prepare',
                'slug' =>
                    'arpynet-jarvis-task-prepare-v9',
                'category' => 'company',
                'timezone' =>
                    'America/Lima',
                'is_active' => true,
                'created_by' =>
                    $user->id,
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
