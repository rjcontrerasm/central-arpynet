<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class CalendarExternalContextBuilder
{
    public function build(
        array $calendar,
        CarbonImmutable $day,
        ?CarbonImmutable $now = null,
    ): array {
        $timezone = config('app.timezone', 'America/Lima');
        $day = $day->setTimezone($timezone)->startOfDay();
        $end = $day->addDay();
        $now = ($now ?? CarbonImmutable::now($timezone))
            ->setTimezone($timezone);

        $status = (string) ($calendar['status'] ?? 'disconnected');
        $events = collect($calendar['events'] ?? [])
            ->filter(fn ($event) => is_array($event))
            ->filter(fn (array $event) => ($event['source'] ?? null) === 'google_calendar')
            ->map(function (array $event) use ($timezone): array {
                $event['starts_at'] = $this->moment($event['starts_at'] ?? null, $timezone);
                $event['ends_at'] = $this->moment($event['ends_at'] ?? null, $timezone);
                $event['all_day'] = (bool) ($event['all_day'] ?? false);

                return $event;
            })
            ->filter(fn (array $event) => $event['starts_at'] instanceof CarbonImmutable)
            ->sortBy(fn (array $event) => $event['starts_at']->getTimestamp())
            ->values();

        $allDay = $events->where('all_day', true)->values();
        $timed = $events->where('all_day', false)->values();

        $bounded = $timed
            ->filter(fn (array $event) => $event['ends_at'] instanceof CarbonImmutable)
            ->filter(fn (array $event) => $event['ends_at']->greaterThan($event['starts_at']))
            ->values();

        $intervals = $bounded
            ->map(function (array $event) use ($day, $end): ?array {
                $start = $event['starts_at']->greaterThan($day)
                    ? $event['starts_at']
                    : $day;
                $finish = $event['ends_at']->lessThan($end)
                    ? $event['ends_at']
                    : $end;

                if (! $finish->greaterThan($start)) {
                    return null;
                }

                return [
                    'start' => $start,
                    'end' => $finish,
                ];
            })
            ->filter()
            ->sortBy(fn (array $interval) => $interval['start']->getTimestamp())
            ->values();

        $busySeconds = 0;
        $mergedStart = null;
        $mergedEnd = null;

        foreach ($intervals as $interval) {
            if ($mergedStart === null) {
                $mergedStart = $interval['start'];
                $mergedEnd = $interval['end'];
                continue;
            }

            if ($interval['start']->lessThanOrEqualTo($mergedEnd)) {
                if ($interval['end']->greaterThan($mergedEnd)) {
                    $mergedEnd = $interval['end'];
                }
                continue;
            }

            $busySeconds += $mergedEnd->getTimestamp() - $mergedStart->getTimestamp();
            $mergedStart = $interval['start'];
            $mergedEnd = $interval['end'];
        }

        if ($mergedStart !== null && $mergedEnd !== null) {
            $busySeconds += $mergedEnd->getTimestamp() - $mergedStart->getTimestamp();
        }

        $overlaps = [];
        $boundedCount = $bounded->count();

        for ($left = 0; $left < $boundedCount; $left++) {
            for ($right = $left + 1; $right < $boundedCount; $right++) {
                $a = $bounded[$left];
                $b = $bounded[$right];

                if (
                    $a['starts_at']->lessThan($b['ends_at'])
                    && $b['starts_at']->lessThan($a['ends_at'])
                ) {
                    $overlaps[] = [
                        'left' => $this->eventSummary($a),
                        'right' => $this->eventSummary($b),
                    ];
                }
            }
        }

        $isToday = $day->isSameDay($now);
        $isPast = $day->lessThan($now->startOfDay());

        $current = $isToday
            ? $bounded->first(fn (array $event) =>
                $event['starts_at']->lessThanOrEqualTo($now)
                && $event['ends_at']->greaterThan($now)
            )
            : null;

        $next = $isPast
            ? null
            : $timed->first(fn (array $event) =>
                $event['starts_at']->greaterThanOrEqualTo(
                    $isToday ? $now : $day,
                )
            );

        return [
            'source' => 'google_calendar',
            'status' => $status,
            'connected' => (bool) ($calendar['connected'] ?? false),
            'has_context' => $status === 'ok' && $events->isNotEmpty(),
            'counts' => [
                'external' => $events->count(),
                'timed' => $timed->count(),
                'all_day' => $allDay->count(),
                'bounded' => $bounded->count(),
            ],
            'busy_minutes' => intdiv($busySeconds, 60),
            'overlap_count' => count($overlaps),
            'overlaps' => $overlaps,
            'current_event' => is_array($current)
                ? $this->eventSummary($current)
                : null,
            'next_event' => is_array($next)
                ? $this->eventSummary($next)
                : null,
            'all_day_events' => $allDay
                ->map(fn (array $event) => $this->eventSummary($event))
                ->all(),
            'read_only_context' => true,
            'availability_inferred' => false,
        ];
    }

    private function moment(mixed $value, string $timezone): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->setTimezone($timezone);
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone($timezone);
        }

        return null;
    }

    private function eventSummary(array $event): array
    {
        return [
            'key' => $event['key'] ?? null,
            'title' => trim((string) ($event['title'] ?? 'Evento sin título')),
            'subtitle' => trim((string) ($event['subtitle'] ?? '')),
            'starts_at' => $event['starts_at'] ?? null,
            'ends_at' => $event['ends_at'] ?? null,
            'all_day' => (bool) ($event['all_day'] ?? false),
            'url' => $event['url'] ?? null,
            'external' => true,
        ];
    }
}
