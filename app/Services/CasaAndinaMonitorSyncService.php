<?php

namespace App\Services;

use App\Models\ExternalMonitorSyncState;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class CasaAndinaMonitorSyncService
{
    public const PROVIDER = 'casa-andina-monitor';

    public function __construct(
        private readonly CasaAndinaMonitorClient $client,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config(
            'casa_andina_monitor.enabled',
            false,
        );
    }

    public function configured(): bool
    {
        return $this->client->configured();
    }

    public function sync(): array
    {
        $state = ExternalMonitorSyncState::query()
            ->firstOrCreate([
                'provider' => self::PROVIDER,
            ]);

        $state->forceFill([
            'last_sync_at' => now(),
        ])->save();

        try {
            $organization = $this->organization();
            $owner = $this->owner();
            $payload = $this->client->fetchStatus();

            $result = [
                'created' => 0,
                'updated' => 0,
                'resolved' => 0,
                'unchanged' => 0,
            ];

            $items = 0;

            foreach ((array) ($payload['services'] ?? []) as $service) {
                $items++;
                $this->syncService(
                    $organization,
                    $owner,
                    (array) $service,
                    $result,
                );
            }

            foreach (
                (array) ($payload['certificates'] ?? [])
                as $certificate
            ) {
                $items++;
                $this->syncCertificate(
                    $organization,
                    $owner,
                    (array) $certificate,
                    $result,
                );
            }

            $state->forceFill([
                'last_success_at' => now(),
                'last_error_at' => null,
                'last_error' => null,
                'last_generated_at' => $this->generatedAt($payload),
                'last_item_count' => $items,
            ])->save();

            return $result + ['items' => $items];
        } catch (Throwable $exception) {
            $state->forceFill([
                'last_error_at' => now(),
                'last_error' => mb_substr(
                    $exception->getMessage(),
                    0,
                    4000,
                ),
            ])->save();

            throw $exception;
        }
    }

    private function syncService(
        Organization $organization,
        User $owner,
        array $item,
        array &$result,
    ): void {
        $id = trim((string) ($item['id'] ?? ''));

        if ($id === '') {
            throw new RuntimeException(
                'Servicio del monitor sin id.',
            );
        }

        $status = strtolower(
            trim((string) ($item['status'] ?? '')),
        );

        $healthy = in_array(
            $status,
            ['up', 'ok', 'healthy'],
            true,
        );

        $name = trim(
            (string) (
                $item['name']
                ?? $item['url']
                ?? $id
            ),
        );

        $externalId = 'service:'.$id;

        if ($healthy) {
            $this->resolve(
                $organization,
                $externalId,
                'Servicio recuperado según Casa Andina Monitor.',
                $result,
            );

            return;
        }

        $severity = match ($status) {
            'down', 'critical' => 'critical',
            'degraded', 'warning' => 'high',
            default => 'medium',
        };

        $this->openOrRefresh(
            $organization,
            $owner,
            $externalId,
            [
                'title' => 'Monitor: '.$name.' no está saludable',
                'category' => 'availability',
                'severity' => $severity,
                'description' => $this->lines([
                    'Estado reportado' => $status ?: 'desconocido',
                    'URL' => $item['url'] ?? null,
                    'Código HTTP' => $item['http_status'] ?? null,
                    'Tiempo de respuesta (ms)' =>
                        $item['response_ms'] ?? null,
                    'Última comprobación' =>
                        $item['checked_at'] ?? null,
                ]),
                'affected_service' => $name,
                'external_url' => $item['url'] ?? null,
            ],
            $result,
        );
    }

    private function syncCertificate(
        Organization $organization,
        User $owner,
        array $item,
        array &$result,
    ): void {
        $id = trim((string) ($item['id'] ?? ''));

        if ($id === '') {
            throw new RuntimeException(
                'Certificado del monitor sin id.',
            );
        }

        $status = strtolower(
            trim((string) ($item['status'] ?? '')),
        );

        $healthy = in_array(
            $status,
            ['ok', 'healthy'],
            true,
        );

        $hostname = trim(
            (string) (
                $item['hostname']
                ?? $item['name']
                ?? $id
            ),
        );

        $externalId = 'certificate:'.$id;

        if ($healthy) {
            $this->resolve(
                $organization,
                $externalId,
                'Certificado SSL nuevamente saludable.',
                $result,
            );

            return;
        }

        $severity = match ($status) {
            'expired' => 'critical',
            'critical' => 'high',
            'warning' => 'medium',
            default => 'medium',
        };

        $this->openOrRefresh(
            $organization,
            $owner,
            $externalId,
            [
                'title' => 'Monitor: revisar SSL de '.$hostname,
                'category' => 'certificate',
                'severity' => $severity,
                'description' => $this->lines([
                    'Estado reportado' => $status ?: 'desconocido',
                    'Hostname' => $hostname,
                    'Vence' => $item['expires_at'] ?? null,
                    'Días restantes' =>
                        $item['days_remaining'] ?? null,
                    'Última comprobación' =>
                        $item['checked_at'] ?? null,
                ]),
                'affected_service' => $hostname,
                'external_url' => isset($item['url'])
                    ? $item['url']
                    : 'https://'.$hostname,
            ],
            $result,
        );
    }

    private function openOrRefresh(
        Organization $organization,
        User $owner,
        string $externalId,
        array $attributes,
        array &$result,
    ): void {
        $incident = Incident::query()
            ->where('organization_id', $organization->id)
            ->where('source', 'monitor')
            ->where('external_id', $externalId)
            ->first();

        $wasNew = ! $incident;

        if (! $incident) {
            $incident = new Incident();
        }

        $wasTerminal = $incident->exists
            && in_array(
                $incident->status,
                ['resolved', 'closed', 'cancelled'],
                true,
            );

        $status = $wasNew || $wasTerminal
            ? 'new'
            : $incident->status;

        $data = [
            'organization_id' => $organization->id,
            'source' => 'monitor',
            'external_id' => $externalId,
            'status' => $status,
            'title' => $attributes['title'],
            'category' => $attributes['category'],
            'severity' => $attributes['severity'],
            'created_by' => $incident->created_by ?: $owner->id,
            'last_activity_at' => now(),
        ];

        $this->optional(
            $data,
            'description',
            $attributes['description'] ?? null,
        );

        $this->optional(
            $data,
            'affected_service',
            $attributes['affected_service'] ?? null,
        );

        $this->optional(
            $data,
            'external_url',
            $attributes['external_url'] ?? null,
        );

        if ($wasTerminal) {
            $this->optional($data, 'resolved_at', null, true);
            $this->optional(
                $data,
                'resolution_summary',
                null,
                true,
            );
        }

        $incident->forceFill($data)->save();

        if ($wasNew) {
            $result['created']++;
        } else {
            $result['updated']++;
        }
    }

    private function resolve(
        Organization $organization,
        string $externalId,
        string $summary,
        array &$result,
    ): void {
        $incident = Incident::query()
            ->where('organization_id', $organization->id)
            ->where('source', 'monitor')
            ->where('external_id', $externalId)
            ->first();

        if (! $incident) {
            $result['unchanged']++;
            return;
        }

        if (
            in_array(
                $incident->status,
                ['resolved', 'closed', 'cancelled'],
                true,
            )
        ) {
            $result['unchanged']++;
            return;
        }

        $data = [
            'status' => 'resolved',
            'last_activity_at' => now(),
        ];

        $this->optional($data, 'resolved_at', now());
        $this->optional(
            $data,
            'resolution_summary',
            $summary,
        );

        $incident->forceFill($data)->save();

        $result['resolved']++;
    }

    private function organization(): Organization
    {
        $slug = (string) config(
            'casa_andina_monitor.organization_slug',
            'casa-andina',
        );

        $organization = Organization::query()
            ->where('slug', $slug)
            ->first();

        if (! $organization) {
            $organization = Organization::query()
                ->where('name', 'Casa Andina')
                ->first();
        }

        if (! $organization) {
            throw new RuntimeException(
                'No existe la organización Casa Andina en Central.',
            );
        }

        return $organization;
    }

    private function owner(): User
    {
        $email = (string) config(
            'casa_andina_monitor.owner_email',
        );

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            throw new RuntimeException(
                'No existe el usuario propietario de la integración.',
            );
        }

        return $user;
    }

    private function optional(
        array &$data,
        string $column,
        mixed $value,
        bool $includeNull = false,
    ): void {
        if (! Schema::hasColumn('incidents', $column)) {
            return;
        }

        if ($value === null && ! $includeNull) {
            return;
        }

        $data[$column] = $value;
    }

    private function generatedAt(array $payload): ?CarbonImmutable
    {
        $value = $payload['generated_at'] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function lines(array $values): string
    {
        return collect($values)
            ->filter(
                fn ($value): bool =>
                    $value !== null
                    && $value !== '',
            )
            ->map(
                fn ($value, $label): string =>
                    $label.': '.$value,
            )
            ->implode("\n");
    }
}
