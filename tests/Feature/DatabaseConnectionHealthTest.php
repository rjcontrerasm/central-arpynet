<?php

namespace Tests\Feature;

use App\Support\DatabaseConnectionHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseConnectionHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_falls_back_without_exposing_error_details(): void
    {
        $health = app(DatabaseConnectionHealth::class)
            ->snapshot();

        $this->assertFalse($health['available']);
        $this->assertSame('sqlite', $health['driver']);
        $this->assertSame('unavailable', $health['status']);
        $this->assertFalse($health['error_details_exposed']);
        $this->assertNull($health['max_connections']);
        $this->assertNull($health['threads_connected']);
    }

    public function test_db_health_command_is_safe_when_advanced_metrics_are_unavailable(): void
    {
        $this->artisan('central:db-health')
            ->expectsOutputToContain('Base de datos: sqlite')
            ->expectsOutputToContain('Métricas avanzadas de conexiones no disponibles.')
            ->assertSuccessful();
    }
}
