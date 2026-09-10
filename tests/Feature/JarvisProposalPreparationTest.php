<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JarvisProposalPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_human_can_prepare_project_next_action_proposal_without_mutating_project(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto preparación Jarvis',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'target_date' =>
                now()->subDay()
                    ->toDateString(),
            'next_action' => null,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(
                '/jarvis?scope='
                .$organization->id,
            )
            ->assertOk()
            ->assertSee(
                'Preparar propuesta',
            )
            ->assertSee(
                'Siguiente acción concreta',
            );

        $response =
            $this->actingAs($user)
                ->post(
                    '/jarvis/preparar-propuesta',
                    [
                        'subject_type' =>
                            'project',
                        'subject_id' =>
                            $project->id,
                        'action' =>
                            'project.next_action.set',
                        'next_action' =>
                            'Coordinar reunión de arranque',
                    ],
                );

        $proposal =
            AgentActionProposal::query()
                ->sole();

        $response->assertRedirect(
            route(
                'agent-proposals.index',
                [
                    'scope' =>
                        $organization->id,
                    'status' =>
                        'pending',
                ],
            ),
        );

        $this->assertSame(
            'pending',
            $proposal->status,
        );

        $this->assertSame(
            'project.next_action.set',
            $proposal->action_key,
        );

        $this->assertSame(
            'Coordinar reunión de arranque',
            $proposal->proposed_changes[
                'next_action'
            ],
        );

        $this->assertNull(
            $project->fresh()
                ->next_action,
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'event' =>
                    'agent_proposal.created',
                'subject_id' =>
                    $proposal->id,
            ],
        );
    }

    public function test_human_can_prepare_service_next_action_with_optional_date_without_mutation(): void
    {
        [$user, $organization] =
            $this->context();

        $client = Client::query()->create([
            'organization_id' =>
                $organization->id,
            'name' => 'Cliente Jarvis',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $order =
            ServiceOrder::query()->create([
                'organization_id' =>
                    $organization->id,
                'client_id' =>
                    $client->id,
                'title' =>
                    'Servicio preparación Jarvis',
                'stage' => 'execution',
                'currency' => 'PEN',
                'next_action' => null,
                'next_action_at' => null,
                'created_by' => $user->id,
            ]);

        $response =
            $this->actingAs($user)
                ->post(
                    '/jarvis/preparar-propuesta',
                    [
                        'subject_type' =>
                            'service_order',
                        'subject_id' =>
                            $order->id,
                        'action' =>
                            'service_order.next_action.set',
                        'next_action' =>
                            'Entregar informe técnico',
                        'next_action_at' =>
                            '2026-09-10 10:30:00',
                    ],
                );

        $proposal =
            AgentActionProposal::query()
                ->sole();

        $response->assertRedirect();

        $this->assertSame(
            'service_order.next_action.set',
            $proposal->action_key,
        );

        $this->assertSame(
            'Entregar informe técnico',
            $proposal->proposed_changes[
                'next_action'
            ],
        );

        $this->assertNotNull(
            $proposal->proposed_changes[
                'next_action_at'
            ],
        );

        $fresh = $order->fresh();

        $this->assertNull(
            $fresh->next_action,
        );

        $this->assertNull(
            $fresh->next_action_at,
        );
    }

    public function test_preparation_is_blocked_when_suggestion_is_no_longer_current(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto ya actualizado',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' =>
                'Acción registrada por otro flujo',
            'created_by' => $user->id,
        ]);

        $response =
            $this->actingAs($user)
                ->post(
                    '/jarvis/preparar-propuesta',
                    [
                        'subject_type' =>
                            'project',
                        'subject_id' =>
                            $project->id,
                        'action' =>
                            'project.next_action.set',
                        'next_action' =>
                            'No debe prepararse',
                    ],
                );

        $response
            ->assertRedirect()
            ->assertSessionHas(
                'agent_proposal_message',
            );

        $this->assertDatabaseCount(
            'agent_action_proposals',
            0,
        );

        $this->assertSame(
            'Acción registrada por otro flujo',
            $project->fresh()
                ->next_action,
        );
    }

    public function test_mismatched_preparation_is_rejected(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto acción inválida',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from('/jarvis')
            ->post(
                '/jarvis/preparar-propuesta',
                [
                    'subject_type' =>
                        'project',
                    'subject_id' =>
                        $project->id,
                    'action' =>
                        'service_order.next_action.set',
                    'next_action' =>
                        'Intento inválido',
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

    public function test_identical_manual_preparation_reuses_existing_pending_proposal(): void
    {
        [$user, $organization] =
            $this->context();

        $project = Project::query()->create([
            'organization_id' =>
                $organization->id,
            'name' =>
                'Proyecto deduplicación manual',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'next_action' => null,
            'created_by' => $user->id,
        ]);

        $payload = [
            'subject_type' => 'project',
            'subject_id' => $project->id,
            'action' =>
                'project.next_action.set',
            'next_action' =>
                'Definir cronograma final',
        ];

        $this->actingAs($user)
            ->post(
                '/jarvis/preparar-propuesta',
                $payload,
            )
            ->assertRedirect();

        $this->actingAs($user)
            ->post(
                '/jarvis/preparar-propuesta',
                $payload,
            )
            ->assertRedirect()
            ->assertSessionHas(
                'agent_proposal_message',
                'Ya existía una propuesta pendiente idéntica; CENTRAL reutilizó la existente. No se ejecutó ningún cambio.',
            );

        $this->assertDatabaseCount(
            'agent_action_proposals',
            1,
        );

        $proposal =
            AgentActionProposal::query()
                ->sole();

        $this->assertSame(
            1,
            AuditLog::query()
                ->where(
                    'event',
                    'agent_proposal.created',
                )
                ->where(
                    'subject_type',
                    'agent_action_proposal',
                )
                ->where(
                    'subject_id',
                    $proposal->id,
                )
                ->count(),
        );
    }

    public function test_user_cannot_prepare_proposal_for_an_unauthorized_organization(): void
    {
        [$user] = $this->context();

        $otherUser =
            User::factory()->create();

        $otherOrganization =
            Organization::query()->create([
                'name' =>
                    'Otra organización Jarvis',
                'slug' =>
                    'otra-organizacion-jarvis-v8',
                'category' => 'company',
                'timezone' =>
                    'America/Lima',
                'is_active' => true,
                'created_by' =>
                    $otherUser->id,
            ]);

        $otherOrganization
            ->users()
            ->attach(
                $otherUser->id,
                [
                    'role' => 'owner',
                    'is_default' => true,
                    'is_active' => true,
                ],
            );

        $project = Project::query()->create([
            'organization_id' =>
                $otherOrganization->id,
            'name' =>
                'Proyecto no autorizado',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' =>
                $otherUser->id,
        ]);

        $this->actingAs($user)
            ->post(
                '/jarvis/preparar-propuesta',
                [
                    'subject_type' =>
                        'project',
                    'subject_id' =>
                        $project->id,
                    'action' =>
                        'project.next_action.set',
                    'next_action' =>
                        'No autorizado',
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'agent_action_proposals',
            0,
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
                    'ARPYNET Jarvis Prepare',
                'slug' =>
                    'arpynet-jarvis-prepare-v8',
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
