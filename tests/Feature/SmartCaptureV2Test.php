<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\SmartTaskCaptureParser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartCaptureV2Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_relative_weekday_and_explicit_dates(): void
    {
        $now = CarbonImmutable::parse('2026-09-02 10:00:00', 'America/Lima');
        $parser = app(SmartTaskCaptureParser::class);

        $this->assertSame(
            '2026-09-05',
            $parser->parse('en 3 días Enviar informe', collect(), collect(), $now)['due_date'],
        );

        $this->assertSame(
            '2026-09-04',
            $parser->parse('viernes Revisar propuesta', collect(), collect(), $now)['due_date'],
        );

        $this->assertSame(
            '2026-09-15',
            $parser->parse('15 de septiembre Renovar SSL', collect(), collect(), $now)['due_date'],
        );
    }

    public function test_capture_assigns_project_and_explicit_next_action(): void
    {
        CarbonImmutable::setTestNow('2026-09-02 10:00:00');

        [$user, $organization] = $this->context();

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Renovación web',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' => $organization->id,
                'title' => '#Renovación web en 3 días Preparar propuesta -> llamar al cliente',
                'due_mode' => 'today',
                'urgency' => 'normal',
                'impact' => 'normal',
            ])
            ->assertRedirect('/captura');

        $task = Task::query()->where('title', 'Preparar propuesta')->firstOrFail();

        $this->assertSame($project->id, $task->project_id);
        $this->assertSame('llamar al cliente', $task->next_action);
        $this->assertSame('2026-09-05', $task->due_at?->format('Y-m-d'));
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
