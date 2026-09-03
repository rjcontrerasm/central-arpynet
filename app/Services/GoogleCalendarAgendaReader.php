<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Throwable;

class GoogleCalendarAgendaReader
{
    public function __construct(
        private readonly GoogleCalendarService $oauth,
    ) {
    }

    public function eventsFor(
        User $user,
        CarbonImmutable $date,
        string $timezone,
    ): array {
        $connection = $user->googleCalendarConnection()->first();

        if (! $connection?->isConnected()) {
            return [
                'connected' => false,
                'status' => 'disconnected',
                'events' => [],
                'error' => null,
            ];
        }

        try {
            $calendar = new GoogleCalendar(
                $this->oauth->authenticatedClient($connection),
            );

            $start = $date->setTimezone($timezone)->startOfDay();
            $end = $start->addDay();

            $response = $calendar->events->listEvents(
                $connection->calendar_id,
                [
                    'timeMin' => $start->toRfc3339String(),
                    'timeMax' => $end->toRfc3339String(),
                    'singleEvents' => true,
                    'orderBy' => 'startTime',
                    'maxResults' => 100,
                ],
            );

            return [
                'connected' => true,
                'status' => 'ok',
                'events' => $this->externalEvents(
                    $response->getItems(),
                    $timezone,
                ),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'connected' => true,
                'status' => 'error',
                'events' => [],
                'error' => 'No se pudo consultar Google Calendar en este momento.',
            ];
        }
    }

    /** @param array<int, GoogleEvent> $events */
    public function externalEvents(array $events, string $timezone): array
    {
        $normalized = [];

        foreach ($events as $event) {
            if ($event->getStatus() === 'cancelled' || $this->isCentralEvent($event)) {
                continue;
            }

            $start = $event->getStart();
            $end = $event->getEnd();

            if (! $start) {
                continue;
            }

            $allDay = filled($start->getDate());
            $startsAt = $allDay
                ? CarbonImmutable::parse($start->getDate(), $timezone)->startOfDay()
                : CarbonImmutable::parse($start->getDateTime())->setTimezone($timezone);

            $endsAt = null;
            if ($end) {
                $endsAt = filled($end->getDate())
                    ? CarbonImmutable::parse($end->getDate(), $timezone)->startOfDay()
                    : (filled($end->getDateTime())
                        ? CarbonImmutable::parse($end->getDateTime())->setTimezone($timezone)
                        : null);
            }

            $normalized[] = [
                'key' => 'google:'.$event->getId(),
                'source' => 'google_calendar',
                'kind' => 'calendar',
                'title' => trim((string) ($event->getSummary() ?: 'Evento sin título')),
                'subtitle' => trim((string) ($event->getLocation() ?: '')),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'all_day' => $allDay,
                'organization' => null,
                'priority' => null,
                'url' => $event->getHtmlLink(),
                'external' => true,
            ];
        }

        return $normalized;
    }

    private function isCentralEvent(GoogleEvent $event): bool
    {
        $private = $event->getExtendedProperties()?->getPrivate();

        return is_array($private)
            && ($private['central_app'] ?? null) === 'central-arpynet';
    }
}
