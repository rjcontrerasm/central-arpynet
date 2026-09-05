<?php

namespace App\Support;

use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use App\Services\GoogleCalendarAgendaReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OperationalAgendaBuilder
{
    public function __construct(
        private readonly GoogleCalendarAgendaReader $calendar,
    ) {
    }

    public function build(User $user, CarbonImmutable $date, ?int $organizationId = null): array
    {
        $timezone = config('app.timezone', 'America/Lima');
        $day = $date->setTimezone($timezone)->startOfDay();
        $start = $day;
        $end = $day->addDay();
        $items = collect();

        foreach ([
            $this->taskItems($user, $start, $end, $organizationId),
            $this->waitingItems($user, $start, $end, $organizationId),
            $this->obligationItems($user, $day, $organizationId),
            $this->serviceItems($user, $start, $end, $organizationId),
            $this->incidentItems($user, $start, $end, $organizationId),
        ] as $group) {
            $group->each(fn (array $item) => $items->push($item));
        }

        $isToday = $day->isSameDay(CarbonImmutable::now($timezone));

        if ($isToday) {
            foreach ([
                $this->overdueTaskItems($user, $start, $organizationId),
                $this->overdueWaitingItems($user, $start, $organizationId),
                $this->overdueObligationItems($user, $day, $organizationId),
                $this->overdueServiceItems($user, $start, $organizationId),
                $this->overdueIncidentItems($user, $start, $organizationId),
            ] as $group) {
                $group->each(fn (array $item) => $items->push($item));
            }
        }

        $calendar = $this->calendar->eventsFor($user, $day, $timezone);
        foreach ($calendar['events'] ?? [] as $event) {
            $items->push($event);
        }

        $items = $items->map(function (array $item): array {
            $item['overdue'] = (bool) ($item['overdue'] ?? false);
            return $item;
        })->sortBy(
            fn (array $item): string => sprintf(
                '%d-%d-%s-%s',
                $item['overdue'] ? 0 : 1,
                $item['all_day'] ? 0 : 1,
                $item['starts_at']->format('H:i:s'),
                $item['title'],
            ),
        )->values();

        $overdueItems = $items->where('overdue', true)->values();
        $scheduledItems = $items->where('overdue', false)->values();

        return [
            'date' => $day,
            'items' => $items,
            'overdueItems' => $overdueItems,
            'scheduledItems' => $scheduledItems,
            'isToday' => $isToday,
            'calendar' => $calendar,
            'counts' => [
                'total' => $items->count(),
                'calendar' => $items->where('source', 'google_calendar')->count(),
                'tasks' => $items->where('kind', 'task')->count(),
                'followups' => $items->whereIn('kind', ['waiting', 'service', 'incident'])->count(),
                'obligations' => $items->where('kind', 'obligation')->count(),
                'overdue' => $overdueItems->count(),
                'scheduled' => $scheduledItems->count(),
            ],
        ];
    }

    private function taskItems(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $organizationId): Collection
    {
        return Task::query()->visibleTo($user)->with('organization')
            ->whereNotIn('status', ['completed', 'cancelled', 'someday', 'waiting'])
            ->whereNotNull('due_at')->where('due_at', '>=', $start)->where('due_at', '<', $end)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (Task $task): array => [
                'key' => 'task:'.$task->id,
                'source' => 'central',
                'kind' => 'task',
                'title' => $task->title,
                'subtitle' => $task->next_action ?: 'Tarea',
                'starts_at' => CarbonImmutable::instance($task->due_at),
                'ends_at' => null,
                'all_day' => false,
                'organization' => $task->organization?->name,
                'priority' => $task->priority_band,
                'url' => route('daily-ops.show', ['scope' => $task->organization_id], false),
                'external' => false,
                'overdue' => false,
            ]);
    }

    private function waitingItems(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $organizationId): Collection
    {
        return Task::query()->visibleTo($user)->with('organization')
            ->where('status', 'waiting')->whereNotNull('waiting_until')
            ->where('waiting_until', '>=', $start)->where('waiting_until', '<', $end)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (Task $task): array => [
                'key' => 'waiting:'.$task->id,
                'source' => 'central',
                'kind' => 'waiting',
                'title' => $task->title,
                'subtitle' => $task->waiting_reason ?: 'Seguimiento en espera',
                'starts_at' => CarbonImmutable::instance($task->waiting_until),
                'ends_at' => null,
                'all_day' => false,
                'organization' => $task->organization?->name,
                'priority' => 'waiting',
                'url' => route('daily-ops.show', ['scope' => $task->organization_id], false),
                'external' => false,
                'overdue' => false,
            ]);
    }

    private function obligationItems(User $user, CarbonImmutable $day, ?int $organizationId): Collection
    {
        return ObligationOccurrence::query()->visibleTo($user)->with(['organization', 'obligation'])
            ->where('status', 'pending')->whereDate('due_date', $day->toDateString())
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (ObligationOccurrence $o): array => [
                'key' => 'obligation:'.$o->id,
                'source' => 'central',
                'kind' => 'obligation',
                'title' => $o->obligation?->name ?: 'Vencimiento',
                'subtitle' => 'Vencimiento'.($o->expected_amount !== null ? ' · '.$o->currency.' '.number_format((float) $o->expected_amount, 2) : ''),
                'starts_at' => $day->setTime(9, 0),
                'ends_at' => null,
                'all_day' => true,
                'organization' => $o->organization?->name,
                'priority' => $o->obligation?->is_critical ? 'critical' : null,
                'url' => route('obligation-ops.show', ['scope' => $o->organization_id, 'focus' => 'today'], false),
                'external' => false,
                'overdue' => false,
            ]);
    }

    private function serviceItems(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $organizationId): Collection
    {
        return ServiceOrder::query()->visibleTo($user)->with(['organization', 'client'])
            ->whereNotIn('stage', ['closed', 'cancelled'])->whereNotNull('next_action_at')
            ->where('next_action_at', '>=', $start)->where('next_action_at', '<', $end)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (ServiceOrder $order): array => [
                'key' => 'service:'.$order->id,
                'source' => 'central',
                'kind' => 'service',
                'title' => $order->title,
                'subtitle' => $order->next_action ?: 'Seguimiento de servicio',
                'starts_at' => CarbonImmutable::instance($order->next_action_at),
                'ends_at' => null,
                'all_day' => false,
                'organization' => $order->organization?->name,
                'priority' => null,
                'url' => route('service-orders-ops.show', ['scope' => $order->organization_id, 'focus' => 'all'], false),
                'external' => false,
                'overdue' => false,
            ]);
    }

    private function incidentItems(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $organizationId): Collection
    {
        return Incident::query()->visibleTo($user)->with('organization')->open()->whereNotNull('next_action_at')
            ->where('next_action_at', '>=', $start)->where('next_action_at', '<', $end)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (Incident $incident): array => [
                'key' => 'incident:'.$incident->id,
                'source' => 'central',
                'kind' => 'incident',
                'title' => $incident->title,
                'subtitle' => $incident->next_action ?: 'Seguimiento de incidente',
                'starts_at' => CarbonImmutable::instance($incident->next_action_at),
                'ends_at' => null,
                'all_day' => false,
                'organization' => $incident->organization?->name,
                'priority' => $incident->severity,
                'url' => url('/admin/incidents'),
                'external' => false,
                'overdue' => false,
            ]);
    }

    private function overdueTaskItems(User $user, CarbonImmutable $start, ?int $organizationId): Collection
    {
        return Task::query()->visibleTo($user)->with('organization')
            ->whereNotIn('status', ['completed', 'cancelled', 'someday', 'waiting'])
            ->whereNotNull('due_at')->where('due_at', '<', $start)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (Task $task): array => [
                'key' => 'overdue-task:'.$task->id,
                'source' => 'central',
                'kind' => 'task',
                'title' => $task->title,
                'subtitle' => $task->next_action ?: 'Tarea vencida',
                'starts_at' => CarbonImmutable::instance($task->due_at),
                'ends_at' => null,
                'all_day' => false,
                'organization' => $task->organization?->name,
                'priority' => $task->priority_band,
                'url' => route('daily-ops.show', ['scope' => $task->organization_id], false),
                'external' => false,
                'overdue' => true,
            ]);
    }

    private function overdueWaitingItems(User $user, CarbonImmutable $start, ?int $organizationId): Collection
    {
        return Task::query()->visibleTo($user)->with('organization')
            ->where('status', 'waiting')->whereNotNull('waiting_until')
            ->where('waiting_until', '<', $start)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (Task $task): array => [
                'key' => 'overdue-waiting:'.$task->id,
                'source' => 'central',
                'kind' => 'waiting',
                'title' => $task->title,
                'subtitle' => $task->waiting_reason ?: 'Seguimiento vencido',
                'starts_at' => CarbonImmutable::instance($task->waiting_until),
                'ends_at' => null,
                'all_day' => false,
                'organization' => $task->organization?->name,
                'priority' => 'waiting',
                'url' => route('daily-ops.show', ['scope' => $task->organization_id], false),
                'external' => false,
                'overdue' => true,
            ]);
    }

    private function overdueObligationItems(User $user, CarbonImmutable $day, ?int $organizationId): Collection
    {
        return ObligationOccurrence::query()->visibleTo($user)->with(['organization', 'obligation'])
            ->where('status', 'pending')->whereDate('due_date', '<', $day->toDateString())
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (ObligationOccurrence $o): array => [
                'key' => 'overdue-obligation:'.$o->id,
                'source' => 'central',
                'kind' => 'obligation',
                'title' => $o->obligation?->name ?: 'Vencimiento',
                'subtitle' => 'Vencimiento pendiente',
                'starts_at' => CarbonImmutable::parse($o->due_date, config('app.timezone', 'America/Lima'))->startOfDay(),
                'ends_at' => null,
                'all_day' => true,
                'organization' => $o->organization?->name,
                'priority' => $o->obligation?->is_critical ? 'critical' : null,
                'url' => route('obligation-ops.show', ['scope' => $o->organization_id, 'focus' => 'overdue'], false),
                'external' => false,
                'overdue' => true,
            ]);
    }

    private function overdueServiceItems(User $user, CarbonImmutable $start, ?int $organizationId): Collection
    {
        return ServiceOrder::query()->visibleTo($user)->with(['organization', 'client'])
            ->whereNotIn('stage', ['closed', 'cancelled'])
            ->whereNotNull('next_action_at')->where('next_action_at', '<', $start)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (ServiceOrder $order): array => [
                'key' => 'overdue-service:'.$order->id,
                'source' => 'central',
                'kind' => 'service',
                'title' => $order->title,
                'subtitle' => $order->next_action ?: 'Seguimiento vencido',
                'starts_at' => CarbonImmutable::instance($order->next_action_at),
                'ends_at' => null,
                'all_day' => false,
                'organization' => $order->organization?->name,
                'priority' => null,
                'url' => route('service-orders-ops.show', ['scope' => $order->organization_id, 'focus' => 'all'], false),
                'external' => false,
                'overdue' => true,
            ]);
    }

    private function overdueIncidentItems(User $user, CarbonImmutable $start, ?int $organizationId): Collection
    {
        return Incident::query()->visibleTo($user)->with('organization')->open()
            ->whereNotNull('next_action_at')->where('next_action_at', '<', $start)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()->map(fn (Incident $incident): array => [
                'key' => 'overdue-incident:'.$incident->id,
                'source' => 'central',
                'kind' => 'incident',
                'title' => $incident->title,
                'subtitle' => $incident->next_action ?: 'Seguimiento vencido',
                'starts_at' => CarbonImmutable::instance($incident->next_action_at),
                'ends_at' => null,
                'all_day' => false,
                'organization' => $incident->organization?->name,
                'priority' => $incident->severity,
                'url' => url('/admin/incidents'),
                'external' => false,
                'overdue' => true,
            ]);
    }

}
