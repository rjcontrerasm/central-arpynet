<?php

namespace Tests\Feature;

use App\Models\AgentActionProposal;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralCopilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_copilot_requires_authentication(): void
    {
        $this->get(route('central-copilot.index'))
            ->assertRedirect(route('login'));
    }

    public function test_copilot_answers_today_from_authorized_central_context(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Resolver incidente crítico Copilot',
            'status' => 'pending',
            'urgency' => 'critical',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('central-copilot.index', [
                'scope' => $organization->id,
                'q' => '¿Qué hago hoy?',
            ]))
            ->assertOk()
            ->assertSee('CENTRAL Copilot')
            ->assertSee('Qué atender hoy')
            ->assertSee('Resolver incidente crítico Copilot')
            ->assertSee('Solo lectura')
            ->assertSee('sin red externa', false);
    }

    public function test_copilot_unknown_question_is_bounded_and_never_creates_proposal_or_mutates_task(): void
    {
        [$user, $organization] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea intacta Copilot',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'medium',
            'created_by' => $user->id,
        ]);

        $beforeTask = $task->fresh()->getAttributes();
        $beforeProposals = AgentActionProposal::query()->count();

        $this->actingAs($user)
            ->get(route('central-copilot.index', [
                'scope' => $organization->id,
                'q' => 'Cuéntame el clima de mañana en Tokio',
            ]))
            ->assertOk()
            ->assertSee('No puedo responder eso con evidencia suficiente')
            ->assertSee('No inventará una respuesta')
            ->assertSee('no consultará una red externa');

        $this->assertSame(
            $beforeProposals,
            AgentActionProposal::query()->count(),
        );

        $this->assertSame(
            $beforeTask,
            $task->fresh()->getAttributes(),
        );
    }

    public function test_copilot_rejects_foreign_scope(): void
    {
        [$user] = $this->context();
        $foreignUser = User::factory()->create();

        $foreign = Organization::query()->create([
            'name' => 'Ámbito Copilot ajeno',
            'slug' => 'copilot-foreign-scope',
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

        $this->actingAs($user)
            ->get(route('central-copilot.index', [
                'scope' => $foreign->id,
                'q' => '¿Cómo estamos?',
            ]))
            ->assertForbidden();
    }

    public function test_viewer_can_use_read_only_copilot_without_receiving_write_side_effects(): void
    {
        [$owner, $organization] = $this->context();
        $viewer = User::factory()->create();

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $viewer->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Contexto visible para viewer Copilot',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now()->subDay(),
            'created_by' => $owner->id,
        ]);

        $before = AgentActionProposal::query()->count();

        $this->actingAs($viewer)
            ->get(route('central-copilot.index', [
                'scope' => $organization->id,
                'q' => '¿Qué es crítico?',
            ]))
            ->assertOk()
            ->assertSee('Riesgos y focos críticos')
            ->assertSee('Contexto visible para viewer Copilot');

        $this->assertSame(
            $before,
            AgentActionProposal::query()->count(),
        );
    }

    public function test_copilot_exposes_proposal_counts_but_query_does_not_review_or_execute_them(): void
    {
        [$user, $organization] = $this->context();

        $task = Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea para propuesta Copilot',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'created_by' => $user->id,
        ]);

        $proposal = app(\App\Support\CentralAgentGateway::class)
            ->proposeTaskAction(
                $user,
                $task,
                'start',
                'Propuesta existente para comprobar conteo.',
            );

        $this->actingAs($user)
            ->get(route('central-copilot.index', [
                'scope' => $organization->id,
                'q' => '¿Qué decisiones tengo?',
            ]))
            ->assertOk()
            ->assertSee('Decisiones y propuestas')
            ->assertSee('Hay 1 propuestas pendientes')
            ->assertSee('Abrir cola segura de propuestas');

        $proposal->refresh();
        $this->assertSame('pending', $proposal->status);
        $this->assertSame('pending', $task->fresh()->status);
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'copilot-owner@arpynet.test',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Copilot',
            'slug' => 'arpynet-central-copilot',
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
