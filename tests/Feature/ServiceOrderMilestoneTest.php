<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderMilestone;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderMilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_order_milestone_creates_linked_team_task(): void
    {
        [$user, $organization, $client] = $this->context();

        $assignee = User::factory()->create([
            'email' => 'milestone-assignee@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($assignee->id, [
            'role' => 'member',
            'is_default' => false,
            'is_active' => true,
        ]);

        $team = WorkTeam::query()->create([
            'home_organization_id' => $organization->id,
            'name' => 'Administración',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $team->users()->attach([
            $user->id => [
                'role' => 'lead',
                'is_active' => true,
            ],
            $assignee->id => [
                'role' => 'member',
                'is_active' => true,
            ],
        ]);

        $order = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio contractual',
            'order_number' => '014-2026',
            'stage' => 'execution',
            'currency' => 'PEN',
            'assigned_to' => $assignee->id,
            'work_team_id' => $team->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(
            '/servicios/'.$order->id.'/hitos',
            [
                'title' => 'Entregable 1',
                'description' => 'Informe de implementación.',
                'contractual_due_date' => '2026-10-20',
                'assigned_to' => $assignee->id,
                'urgency' => 'high',
                'amount' => '1250.50',
                'notes' => 'Según cronograma contractual.',
            ],
        );

        $response->assertRedirect();

        $milestone = ServiceOrderMilestone::query()
            ->where('service_order_id', $order->id)
            ->firstOrFail();

        $task = Task::query()->findOrFail($milestone->task_id);

        $this->assertSame(1, $milestone->sequence);
        $this->assertSame('Entregable 1', $milestone->title);
        $this->assertSame('1250.50', $milestone->amount);
        $this->assertSame('014-2026 · Entregable 1', $task->title);
        $this->assertSame('service_order_milestone', $task->source);
        $this->assertSame('teams', $task->visibility_scope);
        $this->assertSame($assignee->id, $task->assigned_to);
        $this->assertSame('high', $task->urgency);
        $this->assertSame('2026-10-20', $task->due_at?->format('Y-m-d'));
        $this->assertTrue(
            $task->workTeams()
                ->whereKey($team->id)
                ->exists(),
        );
    }

    public function test_updating_milestone_updates_contract_and_linked_task(): void
    {
        [$user, $organization, $client] = $this->context();

        $order = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'OS con hitos',
            'order_number' => 'OS-200',
            'stage' => 'execution',
            'currency' => 'PEN',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(
            '/servicios/'.$order->id.'/hitos',
            [
                'title' => 'Informe inicial',
                'contractual_due_date' => '2026-10-15',
                'assigned_to' => $user->id,
                'urgency' => 'normal',
            ],
        )->assertRedirect();

        $milestone = ServiceOrderMilestone::query()->firstOrFail();

        $this->actingAs($user)->post(
            '/servicios/'.$order->id.'/hitos/'.$milestone->id,
            [
                'title' => 'Informe inicial corregido',
                'contractual_due_date' => '2026-10-18',
                'assigned_to' => $user->id,
                'urgency' => 'critical',
                'delivered_date' => '2026-10-17',
                'conformity_date' => '2026-10-18',
                'amount' => '500',
                'description' => 'Versión final.',
                'notes' => 'Conformidad registrada.',
            ],
        )->assertRedirect();

        $milestone->refresh();
        $task = $milestone->task()->firstOrFail();

        $this->assertSame(
            'Informe inicial corregido',
            $milestone->title,
        );
        $this->assertSame(
            '2026-10-17',
            $milestone->delivered_date?->format('Y-m-d'),
        );
        $this->assertSame(
            '2026-10-18',
            $milestone->conformity_date?->format('Y-m-d'),
        );
        $this->assertSame(
            'OS-200 · Informe inicial corregido',
            $task->title,
        );
        $this->assertSame('critical', $task->urgency);
        $this->assertSame(
            '2026-10-18',
            $task->due_at?->format('Y-m-d'),
        );
    }

    public function test_viewer_cannot_create_service_order_milestone(): void
    {
        [$owner, $organization, $client] = $this->context();

        $order = ServiceOrder::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Servicio protegido',
            'stage' => 'execution',
            'currency' => 'PEN',
            'created_by' => $owner->id,
        ]);

        $viewer = User::factory()->create([
            'email' => 'milestone-viewer@arpynet.test',
            'is_active' => true,
        ]);

        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)->post(
            '/servicios/'.$order->id.'/hitos',
            [
                'title' => 'No permitido',
                'urgency' => 'normal',
            ],
        )->assertForbidden();

        $this->assertDatabaseCount(
            'service_order_milestones',
            0,
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'service-milestones@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Hitos',
            'slug' => 'arpynet-hitos',
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

        $client = Client::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Cliente Hitos',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return [$user, $organization, $client];
    }
}
