<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\DatabaseRuntimeSnapshot;
use App\Support\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RuntimeObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_scheduler_heartbeat_reports_missing_healthy_watch_and_stale(): void
    {
        Storage::fake('local');

        $heartbeat = app(SchedulerHeartbeat::class);
        $start = CarbonImmutable::parse('2026-09-17 10:00:00', 'America/Lima');

        $missing = $heartbeat->snapshot($start);
        $this->assertSame('missing', $missing['status']);
        $this->assertFalse($missing['healthy']);

        $heartbeat->beat($start);

        $healthy = $heartbeat->snapshot($start->addSeconds(90));
        $this->assertSame('healthy', $healthy['status']);
        $this->assertTrue($healthy['healthy']);
        $this->assertSame(90, $healthy['age_seconds']);

        $watch = $heartbeat->snapshot($start->addMinutes(4));
        $this->assertSame('watch', $watch['status']);
        $this->assertSame(240, $watch['age_seconds']);

        $stale = $heartbeat->snapshot($start->addMinutes(6));
        $this->assertSame('stale', $stale['status']);
        $this->assertSame(360, $stale['age_seconds']);
    }

    public function test_database_runtime_reports_database_backed_services_on_sqlite(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('cache.default', 'database');
        config()->set('session.driver', 'database');
        config()->set('queue.default', 'database');

        $snapshot = app(DatabaseRuntimeSnapshot::class)->build();

        $this->assertSame('sqlite', $snapshot['driver']);
        $this->assertFalse($snapshot['supported']);
        $this->assertFalse($snapshot['metrics_available']);
        $this->assertSame(
            ['cache', 'sessions', 'queue'],
            $snapshot['database_backed_services'],
        );
    }

    public function test_database_runtime_calculates_mysql_connection_pressure(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.driver', 'mysql');
        config()->set('cache.default', 'database');
        config()->set('session.driver', 'database');
        config()->set('queue.default', 'database');

        DB::shouldReceive('select')
            ->once()
            ->with(\Mockery::on(
                fn (string $sql): bool => str_contains(
                    $sql,
                    'SHOW GLOBAL STATUS',
                ),
            ))
            ->andReturn([
                (object) ['Variable_name' => 'Threads_connected', 'Value' => '82'],
                (object) ['Variable_name' => 'Threads_running', 'Value' => '7'],
                (object) ['Variable_name' => 'Max_used_connections', 'Value' => '97'],
                (object) ['Variable_name' => 'Aborted_connects', 'Value' => '11'],
                (object) ['Variable_name' => 'Connection_errors_max_connections', 'Value' => '3'],
            ]);

        DB::shouldReceive('select')
            ->once()
            ->with(\Mockery::on(
                fn (string $sql): bool => str_contains(
                    $sql,
                    'SHOW GLOBAL VARIABLES',
                ),
            ))
            ->andReturn([
                (object) ['Variable_name' => 'max_connections', 'Value' => '100'],
            ]);

        $snapshot = app(DatabaseRuntimeSnapshot::class)->build();

        $this->assertTrue($snapshot['supported']);
        $this->assertTrue($snapshot['metrics_available']);
        $this->assertSame(100, $snapshot['max_connections']);
        $this->assertSame(82, $snapshot['threads_connected']);
        $this->assertSame(7, $snapshot['threads_running']);
        $this->assertSame(97, $snapshot['max_used_connections']);
        $this->assertSame(3, $snapshot['connection_errors_max_connections']);
        $this->assertSame(82.0, $snapshot['current_utilization_pct']);
        $this->assertSame(97.0, $snapshot['max_used_utilization_pct']);
        $this->assertSame('attention', $snapshot['pressure']);
    }

    public function test_safety_recovery_front_shows_scheduler_and_database_runtime_without_secrets(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow('2026-09-17 10:00:00');

        [$user] = $this->context();

        app(SchedulerHeartbeat::class)->beat(
            CarbonImmutable::now('America/Lima'),
        );

        $response = $this->actingAs($user)
            ->get(route('safety-recovery.index'));

        $response
            ->assertOk()
            ->assertSee('Scheduler')
            ->assertSee('Operativo')
            ->assertSee('Base de datos')
            ->assertSee('Driver: sqlite')
            ->assertSee('Cache: array')
            ->assertSee('Sesiones: array')
            ->assertSee('Cola: sync')
            ->assertDontSee('DB_PASSWORD')
            ->assertDontSee('DB_USERNAME');
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'runtime-observability@arpynet.test',
            'is_active' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Runtime',
            'slug' => 'arpynet-runtime',
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
