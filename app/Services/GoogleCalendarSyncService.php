<?php

namespace App\Services;

use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEventLink;
use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

class GoogleCalendarSyncService
{
    public function __construct(
        private readonly GoogleCalendarService $oauth,
        private readonly GoogleCalendarPayloadFactory $payloads,
    ) {
    }

    public function syncUser(User $user): array
    {
        $connection = $user
            ->googleCalendarConnection()
            ->first();

        if (! $connection?->isConnected()) {
            throw new RuntimeException(
                'Google Calendar no está conectado.',
            );
        }

        if (
            blank(
                $connection->token_data['refresh_token']
                    ?? null,
            )
        ) {
            throw new RuntimeException(
                'La conexión no tiene refresh token. '
                .'Desconecta y vuelve a conectar Google Calendar.',
            );
        }

        $client = $this->oauth
            ->authenticatedClient($connection);

        $calendar = new GoogleCalendar($client);

        $result = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deleted' => 0,
            'errors' => 0,
        ];

        foreach ($this->currentItems($user) as $item) {
            try {
                $action = $this->syncItem(
                    $calendar,
                    $connection,
                    $user,
                    $item,
                );

                $result[$action]++;
            } catch (Throwable $exception) {
                report($exception);
                $result['errors']++;
            }
        }

        $result['deleted'] += $this->cleanupTerminalLinks(
            $calendar,
            $user,
        );

        $connection->forceFill([
            'last_sync_at' => now(),
            'last_error_at' =>
                $result['errors'] > 0 ? now() : null,
            'last_error' =>
                $result['errors'] > 0
                    ? $result['errors']
                        .' elemento(s) no pudieron sincronizarse.'
                    : null,
        ])->save();

        return $result;
    }

    private function currentItems(User $user): array
    {
        $items = [];

        Task::query()
            ->visibleTo($user)
            ->with('organization')
            ->whereNotIn(
                'status',
                ['completed', 'cancelled', 'someday'],
            )
            ->whereNotNull('due_at')
            ->chunkById(
                100,
                function ($tasks) use (&$items): void {
                    foreach ($tasks as $task) {
                        $items[] = $this->payloads
                            ->task($task);
                    }
                },
            );

        ObligationOccurrence::query()
            ->visibleTo($user)
            ->with([
                'organization',
                'obligation',
            ])
            ->where('status', 'pending')
            ->chunkById(
                100,
                function ($occurrences) use (&$items): void {
                    foreach ($occurrences as $occurrence) {
                        $items[] = $this->payloads
                            ->obligation($occurrence);
                    }
                },
            );

        ServiceOrder::query()
            ->visibleTo($user)
            ->with([
                'organization',
                'client',
            ])
            ->whereNotIn(
                'stage',
                ['closed', 'cancelled'],
            )
            ->whereNotNull('next_action_at')
            ->chunkById(
                100,
                function ($orders) use (&$items): void {
                    foreach ($orders as $order) {
                        $items[] = $this->payloads
                            ->serviceOrder($order);
                    }
                },
            );

        Incident::query()
            ->visibleTo($user)
            ->with([
                'organization',
                'client',
            ])
            ->open()
            ->whereNotNull('next_action_at')
            ->chunkById(
                100,
                function ($incidents) use (&$items): void {
                    foreach ($incidents as $incident) {
                        $items[] = $this->payloads
                            ->incident($incident);
                    }
                },
            );

        return $items;
    }

    private function syncItem(
        GoogleCalendar $calendar,
        GoogleCalendarConnection $connection,
        User $user,
        array $item,
    ): string {
        $payload = $this->googlePayload($item);
        $hash = hash(
            'sha256',
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE,
            ),
        );

        $link = GoogleCalendarEventLink::query()
            ->firstOrNew([
                'user_id' => $user->id,
                'source_type' => $item['source_type'],
                'source_id' => $item['source_id'],
            ]);

        if (
            $link->exists
            && $link->content_hash === $hash
        ) {
            $link->forceFill([
                'last_synced_at' => now(),
                'last_error_at' => null,
                'last_error' => null,
            ])->save();

            return 'unchanged';
        }

        $event = new GoogleEvent($payload);

        if (! $link->exists) {
            $created = $calendar->events->insert(
                $connection->calendar_id,
                $event,
            );

            $link->forceFill([
                'calendar_id' =>
                    $connection->calendar_id,
                'google_event_id' =>
                    $created->getId(),
                'content_hash' => $hash,
                'last_synced_at' => now(),
                'last_error_at' => null,
                'last_error' => null,
            ])->save();

            return 'created';
        }

        try {
            $calendar->events->patch(
                $link->calendar_id,
                $link->google_event_id,
                $event,
            );

            $link->forceFill([
                'content_hash' => $hash,
                'last_synced_at' => now(),
                'last_error_at' => null,
                'last_error' => null,
            ])->save();

            return 'updated';
        } catch (GoogleServiceException $exception) {
            if ((int) $exception->getCode() !== 404) {
                $link->forceFill([
                    'last_error_at' => now(),
                    'last_error' => $exception->getMessage(),
                ])->save();

                throw $exception;
            }

            $created = $calendar->events->insert(
                $connection->calendar_id,
                $event,
            );

            $link->forceFill([
                'calendar_id' =>
                    $connection->calendar_id,
                'google_event_id' =>
                    $created->getId(),
                'content_hash' => $hash,
                'last_synced_at' => now(),
                'last_error_at' => null,
                'last_error' => null,
            ])->save();

            return 'updated';
        }
    }

    private function googlePayload(array $item): array
    {
        return [
            'summary' => $item['summary'],
            'description' => $item['description'],
            'start' => $item['start'],
            'end' => $item['end'],
            'transparency' => 'transparent',
            'extendedProperties' => [
                'private' => [
                    'central_app' => 'central-arpynet',
                    'central_source_type' =>
                        $item['source_type'],
                    'central_source_id' =>
                        (string) $item['source_id'],
                ],
            ],
        ];
    }

    private function cleanupTerminalLinks(
        GoogleCalendar $calendar,
        User $user,
    ): int {
        $deleted = 0;

        GoogleCalendarEventLink::query()
            ->where('user_id', $user->id)
            ->chunkById(
                100,
                function ($links) use (
                    $calendar,
                    &$deleted,
                ): void {
                    foreach ($links as $link) {
                        if (
                            $this->sourceStillEligible($link)
                        ) {
                            continue;
                        }

                        try {
                            $calendar->events->delete(
                                $link->calendar_id,
                                $link->google_event_id,
                            );
                        } catch (GoogleServiceException $exception) {
                            if ((int) $exception->getCode() !== 404) {
                                report($exception);
                                continue;
                            }
                        }

                        $link->delete();
                        $deleted++;
                    }
                },
            );

        return $deleted;
    }

    private function sourceStillEligible(
        GoogleCalendarEventLink $link,
    ): bool {
        return match ($link->source_type) {
            'task' => $this->taskEligible(
                Task::query()->find($link->source_id),
            ),

            'obligation' => $this->obligationEligible(
                ObligationOccurrence::query()
                    ->find($link->source_id),
            ),

            'service_order' => $this->serviceOrderEligible(
                ServiceOrder::query()
                    ->find($link->source_id),
            ),

            'incident' => $this->incidentEligible(
                Incident::query()->find($link->source_id),
            ),

            default => false,
        };
    }

    private function taskEligible(?Task $task): bool
    {
        return $task !== null
            && ! in_array(
                $task->status,
                ['completed', 'cancelled', 'someday'],
                true,
            )
            && $task->due_at !== null;
    }

    private function obligationEligible(
        ?ObligationOccurrence $occurrence,
    ): bool {
        return $occurrence !== null
            && $occurrence->status === 'pending';
    }

    private function serviceOrderEligible(
        ?ServiceOrder $order,
    ): bool {
        return $order !== null
            && ! in_array(
                $order->stage,
                ['closed', 'cancelled'],
                true,
            )
            && $order->next_action_at !== null;
    }

    private function incidentEligible(
        ?Incident $incident,
    ): bool {
        return $incident !== null
            && ! in_array(
                $incident->status,
                ['resolved', 'closed', 'cancelled'],
                true,
            )
            && $incident->next_action_at !== null;
    }
}
