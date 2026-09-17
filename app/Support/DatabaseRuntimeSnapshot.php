<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseRuntimeSnapshot
{
    public function build(): array
    {
        $connection = (string) config('database.default');
        $driver = (string) config(
            'database.connections.'.$connection.'.driver',
            $connection,
        );

        $runtime = [
            'driver' => $driver,
            'cache_store' => (string) config('cache.default'),
            'session_driver' => (string) config('session.driver'),
            'queue_connection' => (string) config('queue.default'),
        ];

        $runtime['database_backed_services'] = collect([
            'cache' => $runtime['cache_store'],
            'sessions' => $runtime['session_driver'],
            'queue' => $runtime['queue_connection'],
        ])->filter(
            fn (string $value): bool => $value === 'database',
        )->keys()->values()->all();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return [
                ...$runtime,
                'supported' => false,
                'metrics_available' => false,
                'max_connections' => null,
                'threads_connected' => null,
                'threads_running' => null,
                'max_used_connections' => null,
                'aborted_connects' => null,
                'connection_errors_max_connections' => null,
                'current_utilization_pct' => null,
                'max_used_utilization_pct' => null,
                'pressure' => 'unknown',
            ];
        }

        try {
            $status = $this->normalize(
                DB::select(
                    "SHOW GLOBAL STATUS WHERE Variable_name IN ("
                    ."'Threads_connected',"
                    ."'Threads_running',"
                    ."'Max_used_connections',"
                    ."'Aborted_connects',"
                    ."'Connection_errors_max_connections'"
                    .")",
                ),
            );

            $variables = $this->normalize(
                DB::select(
                    "SHOW GLOBAL VARIABLES WHERE Variable_name IN ("
                    ."'max_connections'"
                    .")",
                ),
            );
        } catch (Throwable) {
            return [
                ...$runtime,
                'supported' => true,
                'metrics_available' => false,
                'max_connections' => null,
                'threads_connected' => null,
                'threads_running' => null,
                'max_used_connections' => null,
                'aborted_connects' => null,
                'connection_errors_max_connections' => null,
                'current_utilization_pct' => null,
                'max_used_utilization_pct' => null,
                'pressure' => 'unknown',
            ];
        }

        $maxConnections = max(
            0,
            (int) ($variables['max_connections'] ?? 0),
        );
        $threadsConnected = max(
            0,
            (int) ($status['threads_connected'] ?? 0),
        );
        $threadsRunning = max(
            0,
            (int) ($status['threads_running'] ?? 0),
        );
        $maxUsedConnections = max(
            0,
            (int) ($status['max_used_connections'] ?? 0),
        );

        $currentUtilization = $this->percentage(
            $threadsConnected,
            $maxConnections,
        );
        $maxUsedUtilization = $this->percentage(
            $maxUsedConnections,
            $maxConnections,
        );

        $pressure = match (true) {
            $currentUtilization !== null
                && $currentUtilization >= 80 => 'attention',
            $currentUtilization !== null
                && $currentUtilization >= 60 => 'watch',
            default => 'healthy',
        };

        return [
            ...$runtime,
            'supported' => true,
            'metrics_available' => true,
            'max_connections' => $maxConnections,
            'threads_connected' => $threadsConnected,
            'threads_running' => $threadsRunning,
            'max_used_connections' => $maxUsedConnections,
            'aborted_connects' => max(
                0,
                (int) ($status['aborted_connects'] ?? 0),
            ),
            'connection_errors_max_connections' => max(
                0,
                (int) (
                    $status['connection_errors_max_connections'] ?? 0
                ),
            ),
            'current_utilization_pct' => $currentUtilization,
            'max_used_utilization_pct' => $maxUsedUtilization,
            'pressure' => $pressure,
        ];
    }

    private function normalize(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $name = $values['Variable_name']
                ?? $values['VARIABLE_NAME']
                ?? null;
            $value = $values['Value']
                ?? $values['VALUE']
                ?? null;

            if ($name === null) {
                continue;
            }

            $normalized[strtolower((string) $name)] = $value;
        }

        return $normalized;
    }

    private function percentage(int $used, int $maximum): ?float
    {
        if ($maximum <= 0) {
            return null;
        }

        return round(($used / $maximum) * 100, 1);
    }
}
