<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\ObligationOccurrence;
use App\Models\ServiceOrder;
use App\Models\Task;
use Carbon\CarbonInterface;

class GoogleCalendarPayloadFactory
{
    public function task(Task $task): array
    {
        $date = $task->due_at->toDateString();

        return [
            'source_type' => 'task',
            'source_id' => $task->id,
            'summary' => '[Central] Tarea: '.$task->title,
            'description' => $this->description([
                'Empresa / ámbito' => $task->organization?->name,
                'Prioridad' => $task->priority_band,
                'Próxima acción' => $task->next_action,
                'Central' => url('/admin/tareas'),
            ]),
            'start' => [
                'date' => $date,
            ],
            'end' => [
                'date' => $task->due_at
                    ->copy()
                    ->addDay()
                    ->toDateString(),
            ],
        ];
    }

    public function obligation(
        ObligationOccurrence $occurrence,
    ): array {
        $date = $occurrence->due_date->toDateString();
        $name = $occurrence->obligation?->name
            ?? 'Obligación';

        $amount = $occurrence->expected_amount !== null
            ? $occurrence->currency.' '
                .number_format(
                    (float) $occurrence->expected_amount,
                    2,
                )
            : 'Variable';

        return [
            'source_type' => 'obligation',
            'source_id' => $occurrence->id,
            'summary' => '[Central] Vence: '.$name,
            'description' => $this->description([
                'Empresa / ámbito' =>
                    $occurrence->organization?->name,
                'Proveedor / entidad' =>
                    $occurrence->obligation?->provider,
                'Monto esperado' => $amount,
                'Central' => url('/admin/vencimientos'),
            ]),
            'start' => [
                'date' => $date,
            ],
            'end' => [
                'date' => $occurrence->due_date
                    ->copy()
                    ->addDay()
                    ->toDateString(),
            ],
        ];
    }

    public function serviceOrder(
        ServiceOrder $order,
    ): array {
        return [
            'source_type' => 'service_order',
            'source_id' => $order->id,
            'summary' => '[Central] Seguimiento: '.$order->title,
            'description' => $this->description([
                'Empresa' => $order->organization?->name,
                'Cliente' => $order->client?->name,
                'Etapa' => ServiceOrder::stageOptions()[$order->stage]
                    ?? $order->stage,
                'Próxima acción' => $order->next_action,
                'Central' => url('/admin/ordenes-servicio'),
            ]),
            'start' => $this->dateTime(
                $order->next_action_at,
            ),
            'end' => $this->dateTime(
                $order->next_action_at
                    ->copy()
                    ->addMinutes(30),
            ),
        ];
    }

    public function incident(Incident $incident): array
    {
        return [
            'source_type' => 'incident',
            'source_id' => $incident->id,
            'summary' => '[Central] Incidente: '.$incident->title,
            'description' => $this->description([
                'Empresa / ámbito' =>
                    $incident->organization?->name,
                'Cliente' => $incident->client?->name,
                'Severidad' =>
                    Incident::severityOptions()[$incident->severity]
                        ?? $incident->severity,
                'Próxima acción' => $incident->next_action,
                'Central' => url('/admin/incidentes'),
            ]),
            'start' => $this->dateTime(
                $incident->next_action_at,
            ),
            'end' => $this->dateTime(
                $incident->next_action_at
                    ->copy()
                    ->addMinutes(30),
            ),
        ];
    }

    private function dateTime(
        CarbonInterface $dateTime,
    ): array {
        return [
            'dateTime' => $dateTime->toRfc3339String(),
            'timeZone' => config(
                'app.timezone',
                'America/Lima',
            ),
        ];
    }

    private function description(array $lines): string
    {
        return collect($lines)
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
