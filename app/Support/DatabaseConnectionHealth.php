<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseConnectionHealth
{
    public function snapshot(): array
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            if ($driver !== 'mysql') {
                return $this->unavailable(
                    $driver,
                    'Métricas avanzadas disponibles solo para MariaDB/MySQL',
                );
            }

            $version = (string) (
                $connection->selectOne('select version() as version')
                    ?->version
                ?? 'desconocida'
            );

            $maxConnections = $this->integerValue(
                $connection->selectOne(
                    "SHOW VARIABLES LIKE 'max_connections'",
                ),
            );
            $threadsConnected = $this->integerValue(
                $connection->selectOne(
                    "SHOW GLOBAL STATUS LIKE 'Threads_connected'",
                ),
            );
            $threadsRunning = $this->integerValue(
                $connection->selectOne(
                    "SHOW GLOBAL STATUS LIKE 'Threads_running'",
                ),
            );
            $maxUsedConnections = $this->integerValue(
                $connection->selectOne(
                    "SHOW GLOBAL STATUS LIKE 'Max_used_connections'",
                ),
            );

            if ($maxConnections < 1) {
                return $this->unavailable(
                    $driver,
                    'El servidor no expuso max_connections',
                    $version,
                );
            }

            $currentPercent = round(
                ($threadsConnected / $maxConnections) * 100,
                1,
            );
            $peakPercent = round(
                ($maxUsedConnections / $maxConnections) * 100,
                1,
            );

            $status = match (true) {
                $currentPercent >= 90 => 'attention',
                $currentPercent >= 75 => 'watch',
                default => 'healthy',
            };

            return [
                'available' => true,
                'driver' => $driver,
                'version' => $version,
                'status' => $status,
                'status_label' => match ($status) {
                    'attention' => 'Presión crítica de conexiones',
                    'watch' => 'Conexiones cerca del límite',
                    default => 'Conexiones dentro del rango',
                },
                'max_connections' => $maxConnections,
                'threads_connected' => $threadsConnected,
                'threads_running' => $threadsRunning,
                'max_used_connections' => $maxUsedConnections,
                'current_percent' => $currentPercent,
                'peak_percent' => $peakPercent,
                'historical_near_limit' => $peakPercent >= 90,
                'global_scope' => true,
                'error_details_exposed' => false,
            ];
        } catch (Throwable) {
            return $this->unavailable(
                'unknown',
                'El servidor no permitió leer métricas de conexiones',
            );
        }
    }

    private function integerValue(?object $row): int
    {
        if (! $row) {
            return 0;
        }

        foreach (['Value', 'value'] as $property) {
            if (isset($row->{$property})) {
                return max(0, (int) $row->{$property});
            }
        }

        foreach ((array) $row as $value) {
            if (is_numeric($value)) {
                return max(0, (int) $value);
            }
        }

        return 0;
    }

    private function unavailable(
        string $driver,
        string $label,
        ?string $version = null,
    ): array {
        return [
            'available' => false,
            'driver' => $driver,
            'version' => $version,
            'status' => 'unavailable',
            'status_label' => $label,
            'max_connections' => null,
            'threads_connected' => null,
            'threads_running' => null,
            'max_used_connections' => null,
            'current_percent' => null,
            'peak_percent' => null,
            'historical_near_limit' => false,
            'global_scope' => true,
            'error_details_exposed' => false,
        ];
    }
}
