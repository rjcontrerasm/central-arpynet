<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CasaAndinaMonitorClient
{
    public function configured(): bool
    {
        return filled(config('casa_andina_monitor.base_url'))
            && filled(config('casa_andina_monitor.token'));
    }

    public function fetchStatus(): array
    {
        if (! $this->configured()) {
            throw new RuntimeException(
                'Casa Andina Monitor no está configurado.',
            );
        }

        $url = rtrim(
            (string) config('casa_andina_monitor.base_url'),
            '/',
        ).'/'.ltrim(
            (string) config('casa_andina_monitor.endpoint'),
            '/',
        );

        $response = Http::acceptJson()
            ->withToken(
                (string) config('casa_andina_monitor.token'),
            )
            ->timeout(
                (int) config('casa_andina_monitor.timeout', 10),
            )
            ->get($url);

        $response->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException(
                'El monitor devolvió una respuesta JSON inválida.',
            );
        }

        if (($payload['version'] ?? null) !== 1) {
            throw new RuntimeException(
                'Versión de contrato del monitor no soportada.',
            );
        }

        if (
            isset($payload['services'])
            && ! is_array($payload['services'])
        ) {
            throw new RuntimeException(
                'services debe ser un arreglo.',
            );
        }

        if (
            isset($payload['certificates'])
            && ! is_array($payload['certificates'])
        ) {
            throw new RuntimeException(
                'certificates debe ser un arreglo.',
            );
        }

        return $payload;
    }
}
