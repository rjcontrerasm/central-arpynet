<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\GoogleCalendarAgendaReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProjectDailyAgendaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_project_target_date_appears_in_agenda(): void
    {
        CarbonImmutable::setTestNow('2026-09-06 09:00:00');
        [$user, $organization] = $this->context();

        Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Implementar portal cliente',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'target_date' => '2026-09-08',
            'next_action' => 'Validar salida a producción',
            'created_by' => $user->id,
        ]);

        $this->mockCalendar();

        $this->actingAs($user)
            ->get('/agenda?date=2026-09-08')
            ->assertOk()
            ->assertSee('Implementar portal cliente')
            ->assertSee('Proyecto')
            ->assertSee('Validar salida a producción');
    }

    public function test_overdue_project_target_appears_only_in_today_backlog(): void
    {
        CarbonImmutable::setTestNow('2026-09-06 09:00:00');
        [$user, $organization] = $this->context();

        Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto objetivo vencido',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'target_date' => '2026-09-05',
            'created_by' => $user->id,
        ]);

        $this->mockCalendar();

        $this->actingAs($user)
            ->get('/agenda?date=2026-09-06')
            ->assertOk()
            ->assertSee('Pendientes vencidos')
            ->assertSee('Proyecto objetivo vencido');

        $this->mockCalendar();

        $this->actingAs($user)
            ->get('/agenda?date=2026-09-07')
            ->assertOk()
            ->assertDontSee('Proyecto objetivo vencido');
    }

    public function test_mi_dia_shows_project_operational_signal(): void
    {
        CarbonImmutable::setTestNow('2026-09-06 09:00:00');
        [$user, $organization] = $this->context();

        Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Proyecto bloqueado hoy',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'target_date' => '2026-09-10',
            'next_action' => 'Escalar aprobación',
            'blockers' => 'Falta aprobación del cliente',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Proyectos a revisar')
            ->assertSee('Proyecto bloqueado hoy')
            ->assertSee('Escalar aprobación')
            ->assertSee('Falta aprobación del cliente');
    }

    public function test_foreign_project_is_hidden_from_mi_dia(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa ajena proyectos',
            'slug' => 'empresa-ajena-proyectos-diarios',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        Project::query()->create([
            'organization_id' => $foreign->id,
            'name' => 'Proyecto privado ajeno',
            'type' => 'project',
            'horizon' => 'short',
            'status' => 'active',
            'blockers' => 'No debe verse',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertDontSee('Proyecto privado ajeno');
    }

    private function mockCalendar(): void
    {
        $reader = Mockery::mock(
            GoogleCalendarAgendaReader::class,
        );

        $reader
            ->shouldReceive('eventsFor')
            ->once()
            ->andReturn([
                'connected' => true,
                'status' => 'ok',
                'error' => null,
                'events' => [],
            ]);

        $this->app->instance(
            GoogleCalendarAgendaReader::class,
            $reader,
        );
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-project-daily-agenda',
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

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
