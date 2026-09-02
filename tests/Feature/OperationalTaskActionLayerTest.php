<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\OperationalTaskActionService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalTaskActionLayerTest
    extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_catalog_is_allowlisted_and_every_action_requires_confirmation(): void
    {
        $catalog = app(
            OperationalTaskActionService::class,
        )->catalog();

        $this->assertSame(
            [
                'complete',
                'start',
                'today',
                'tomorrow',
                'next_week',
            ],
            array_keys($catalog),
        );

        foreach ($catalog as $action) {
            $this->assertTrue(
                $action[
                    'confirmation_required'
                ],
            );
        }
    }

    public function test_preview_is_read_only(): void
    {
        [
            $user,
            $organization,
        ] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $before = $task->fresh()
            ->toArray();

        $preview = app(
            OperationalTaskActionService::class,
        )->preview(
            $user,
            $task,
            'complete',
            CarbonImmutable::parse(
                '2026-09-02 10:00:00',
            ),
        );

        $this->assertTrue(
            $preview[
                'confirmation_required'
            ],
        );

        $this->assertSame(
            $before,
            $task->fresh()
                ->toArray(),
        );
    }

    public function test_execute_without_confirmation_is_blocked(): void
    {
        [
            $user,
            $organization,
        ] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        try {
            app(
                OperationalTaskActionService::class,
            )->execute(
                $user,
                $task,
                'complete',
                confirmed: false,
            );

            $this->fail(
                'Expected confirmation validation error.',
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'confirmation',
                $exception->errors(),
            );
        }

        $this->assertSame(
            'pending',
            $task->fresh()->status,
        );
    }

    public function test_foreign_task_is_blocked_by_action_layer(): void
    {
        [$user] = $this->context();

        $foreign =
            Organization::query()
                ->create([
                    'name' =>
                        'Empresa ajena',
                    'slug' =>
                        'empresa-ajena-action-layer',
                    'category' =>
                        'company',
                    'timezone' =>
                        'America/Lima',
                    'is_active' =>
                        true,
                    'created_by' =>
                        $user->id,
                ]);

        $task = Task::query()
            ->create([
                'organization_id' =>
                    $foreign->id,
                'title' =>
                    'Tarea ajena segura',
                'status' => 'pending',
                'urgency' =>
                    'normal',
                'impact' =>
                    'normal',
                'created_by' =>
                    $user->id,
            ]);

        $this->expectException(
            AuthorizationException::class,
        );

        app(
            OperationalTaskActionService::class,
        )->preview(
            $user,
            $task,
            'complete',
        );
    }

    public function test_confirmed_execution_updates_task(): void
    {
        CarbonImmutable::setTestNow(
            '2026-09-02 10:00:00',
        );

        [
            $user,
            $organization,
        ] = $this->context();

        $task = $this->task(
            $user,
            $organization,
        );

        $result = app(
            OperationalTaskActionService::class,
        )->execute(
            $user,
            $task,
            'tomorrow',
            confirmed: true,
            now: CarbonImmutable::now(),
        );

        $this->assertTrue(
            $result['changed'],
        );

        $this->assertSame(
            '2026-09-03 17:00:00',
            $task->fresh()
                ->due_at
                ?->format(
                    'Y-m-d H:i:s',
                ),
        );
    }

    private function task(
        User $user,
        Organization $organization,
    ): Task {
        return Task::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'title' =>
                    'Tarea capa segura',
                'status' =>
                    'pending',
                'urgency' =>
                    'normal',
                'impact' =>
                    'normal',
                'due_at' =>
                    now()->addWeek(),
                'created_by' =>
                    $user->id,
            ]);
    }

    private function context(): array
    {
        $user = User::factory()
            ->create([
                'email' =>
                    'rcontreras@arpynet.com',
            ]);

        $organization =
            Organization::query()
                ->create([
                    'name' => 'ARPYNET',
                    'slug' => 'arpynet',
                    'category' =>
                        'company',
                    'timezone' =>
                        'America/Lima',
                    'is_active' => true,
                    'created_by' =>
                        $user->id,
                ]);

        $organization->users()
            ->attach(
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
