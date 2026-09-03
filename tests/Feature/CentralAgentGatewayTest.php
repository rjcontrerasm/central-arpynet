<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\CentralAgentGateway;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class CentralAgentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_is_read_preview_only_and_has_no_execute_method(): void
    {
        $gateway = app(
            CentralAgentGateway::class,
        );

        $contract = $gateway->contract();

        $this->assertFalse(
            $contract['public_api'],
        );

        $this->assertFalse(
            $contract['network_calls'],
        );

        $this->assertFalse(
            $contract['write_execution'],
        );

        $this->assertSame(
            [
                'task.read',
                'task.action.preview',
            ],
            $contract['allowed_operations'],
        );

        $reflection = new ReflectionClass(
            CentralAgentGateway::class,
        );

        $this->assertFalse(
            $reflection->hasMethod(
                'executeTaskAction',
            ),
        );
    }

    public function test_task_context_and_preview_are_read_only(): void
    {
        [$user, $organization] =
            $this->context();

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' => 'Tarea para Jarvis',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'high',
            'created_by' => $user->id,
        ]);

        $gateway = app(
            CentralAgentGateway::class,
        );

        $before = $task->fresh()
            ->toArray();

        $context = $gateway->taskContext(
            $user,
            $task,
        );

        $preview = $gateway->previewTaskAction(
            $user,
            $task,
            'tomorrow',
        );

        $this->assertSame(
            'Tarea para Jarvis',
            $context['title'],
        );

        $this->assertTrue(
            $preview['confirmation_required'],
        );

        $this->assertSame(
            $before,
            $task->fresh()->toArray(),
        );
    }

    public function test_foreign_task_is_not_visible_to_gateway(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()
            ->create([
                'name' => 'Ajena agente',
                'slug' => 'ajena-agente',
                'category' => 'company',
                'timezone' => 'America/Lima',
                'is_active' => true,
                'created_by' => $user->id,
            ]);

        $task = Task::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'No visible',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'created_by' => $user->id,
        ]);

        $this->expectException(
            AuthorizationException::class,
        );

        app(CentralAgentGateway::class)
            ->taskContext(
                $user,
                $task,
            );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()
            ->create([
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
