<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Throwable;

class SchedulerHeartbeat
{
    public const PATH = 'central-health/scheduler-heartbeat.json';

    public function __construct(
        private readonly FilesystemFactory $filesystems,
    ) {
    }

    public function beat(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $payload = [
            'recorded_at' => $now->toIso8601String(),
            'timezone' => $now->getTimezone()->getName(),
        ];

        $this->filesystems
            ->disk('local')
            ->put(
                self::PATH,
                json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                ),
            );

        return $payload;
    }

    public function snapshot(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $disk = $this->filesystems->disk('local');

        if (! $disk->exists(self::PATH)) {
            return $this->missing('missing');
        }

        try {
            $payload = json_decode(
                (string) $disk->get(self::PATH),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            $recordedAt = CarbonImmutable::parse(
                (string) ($payload['recorded_at'] ?? ''),
                config('app.timezone', 'America/Lima'),
            );
        } catch (Throwable) {
            return $this->missing('invalid');
        }

        $ageSeconds = max(
            0,
            (int) $recordedAt->diffInSeconds($now, false),
        );

        $status = match (true) {
            $ageSeconds <= 180 => 'healthy',
            $ageSeconds <= 300 => 'watch',
            default => 'stale',
        };

        return [
            'status' => $status,
            'healthy' => $status === 'healthy',
            'last_seen_at' => $recordedAt,
            'age_seconds' => $ageSeconds,
        ];
    }

    private function missing(string $status): array
    {
        return [
            'status' => $status,
            'healthy' => false,
            'last_seen_at' => null,
            'age_seconds' => null,
        ];
    }
}
