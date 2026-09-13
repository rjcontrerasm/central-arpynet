<?php

namespace App\Support;

use App\Models\Incident;
use Carbon\CarbonImmutable;

class Incident360State
{
    public static function evaluate(
        Incident $incident,
        CarbonImmutable $now,
    ): array {
        $terminal = in_array(
            $incident->status,
            ['resolved', 'closed', 'cancelled'],
            true,
        );

        $response = self::responseSla($incident, $now);
        $resolution = self::resolutionSla($incident, $now);
        $nextAction = self::nextAction($incident, $now, $terminal);

        $severityRank = match ($incident->severity) {
            'critical' => 100,
            'high' => 80,
            'medium' => 55,
            'low' => 30,
            default => 10,
        };

        $rank = $terminal ? 0 : $severityRank;
        $reasons = collect();

        if (! $terminal && $incident->severity === 'critical') {
            $reasons->push('Severidad crítica');
        } elseif (! $terminal && $incident->severity === 'high') {
            $reasons->push('Severidad alta');
        }

        if (! $terminal && $resolution['status'] === 'breached') {
            $rank += 80;
            $reasons->push('SLA de resolución vencido');
        }

        if (! $terminal && $response['status'] === 'breached') {
            $rank += 60;
            $reasons->push('SLA de respuesta vencido');
        }

        if (! $terminal && $nextAction['status'] === 'overdue') {
            $rank += 40;
            $reasons->push('Próxima acción vencida');
        }

        if (
            ! $terminal
            && ! $incident->acknowledged_at
            && in_array($incident->severity, ['critical', 'high'], true)
        ) {
            $rank += 20;
            $reasons->push('Incidente aún no reconocido');
        }

        $lastActivity = $incident->last_activity_at
            ?? $incident->updated_at
            ?? $incident->created_at
            ?? $incident->detected_at;

        $staleHours = 0;

        if ($lastActivity) {
            $activity = CarbonImmutable::instance($lastActivity);

            if ($activity->lte($now)) {
                $staleHours = (int) floor(
                    $activity->diffInMinutes($now, true) / 60,
                );
            }
        }

        if (! $terminal && $staleHours >= 24) {
            $rank += 20;
            $reasons->push('Sin actividad '.$staleHours.' h');
        }

        $detected = $incident->detected_at
            ? CarbonImmutable::instance($incident->detected_at)
            : ($incident->created_at
                ? CarbonImmutable::instance($incident->created_at)
                : $now);

        $ended = $incident->resolved_at
            ?? $incident->closed_at;

        $durationEnd = $ended
            ? CarbonImmutable::instance($ended)
            : $now;

        $openMinutes = max(
            0,
            (int) round($detected->diffInMinutes($durationEnd, true)),
        );

        return [
            'active' => ! $terminal,
            'terminal' => $terminal,
            'rank' => $rank,
            'severity_label' => Incident::severityOptions()[$incident->severity]
                ?? ucfirst((string) $incident->severity),
            'status_label' => Incident::statusOptions()[$incident->status]
                ?? ucfirst((string) $incident->status),
            'category_label' => Incident::categoryOptions()[$incident->category]
                ?? ucfirst((string) $incident->category),
            'source_label' => Incident::sourceOptions()[$incident->source]
                ?? ucfirst((string) $incident->source),
            'response_sla' => $response,
            'resolution_sla' => $resolution,
            'next_action' => $nextAction,
            'open_minutes' => $openMinutes,
            'open_duration' => self::durationLabel($openMinutes),
            'stale_hours' => $staleHours,
            'reasons' => $reasons->unique()->values(),
            'needs_attention' => ! $terminal && $reasons->isNotEmpty(),
            'timeline' => self::timeline($incident),
        ];
    }

    private static function responseSla(
        Incident $incident,
        CarbonImmutable $now,
    ): array {
        if (! $incident->response_due_at) {
            return self::slaResult('not_set', 'Sin SLA de respuesta');
        }

        $due = CarbonImmutable::instance($incident->response_due_at);

        if ($incident->acknowledged_at) {
            $ack = CarbonImmutable::instance($incident->acknowledged_at);
            $late = max(0, (int) round($due->diffInMinutes($ack, false)));

            return $ack->lte($due)
                ? self::slaResult('met', 'Respuesta dentro de SLA', $due, 0)
                : self::slaResult('breached', 'Respuesta fuera de SLA', $due, $late);
        }

        if ($now->gt($due)) {
            $late = max(0, (int) round($due->diffInMinutes($now, false)));

            return self::slaResult('breached', 'SLA de respuesta vencido', $due, $late);
        }

        $remaining = max(0, (int) round($now->diffInMinutes($due, false)));

        return self::slaResult('pending', 'Respuesta pendiente', $due, 0, $remaining);
    }

    private static function resolutionSla(
        Incident $incident,
        CarbonImmutable $now,
    ): array {
        if ($incident->status === 'cancelled') {
            return self::slaResult('not_applicable', 'No aplica');
        }

        if (! $incident->resolution_due_at) {
            return self::slaResult('not_set', 'Sin SLA de resolución');
        }

        $due = CarbonImmutable::instance($incident->resolution_due_at);
        $resolvedAt = $incident->resolved_at
            ?? $incident->closed_at;

        if ($resolvedAt) {
            $resolved = CarbonImmutable::instance($resolvedAt);
            $late = max(0, (int) round($due->diffInMinutes($resolved, false)));

            return $resolved->lte($due)
                ? self::slaResult('met', 'Resolución dentro de SLA', $due, 0)
                : self::slaResult('breached', 'Resolución fuera de SLA', $due, $late);
        }

        if ($now->gt($due)) {
            $late = max(0, (int) round($due->diffInMinutes($now, false)));

            return self::slaResult('breached', 'SLA de resolución vencido', $due, $late);
        }

        $remaining = max(0, (int) round($now->diffInMinutes($due, false)));

        return self::slaResult('pending', 'Resolución pendiente', $due, 0, $remaining);
    }

    private static function nextAction(
        Incident $incident,
        CarbonImmutable $now,
        bool $terminal,
    ): array {
        if ($terminal) {
            return [
                'status' => 'done',
                'label' => 'Incidente finalizado',
                'at' => null,
            ];
        }

        if (! $incident->next_action_at) {
            return [
                'status' => filled($incident->next_action)
                    ? 'unscheduled'
                    : 'not_set',
                'label' => filled($incident->next_action)
                    ? 'Acción sin fecha'
                    : 'Sin próxima acción',
                'at' => null,
            ];
        }

        $at = CarbonImmutable::instance($incident->next_action_at);

        return [
            'status' => $at->lt($now) ? 'overdue' : 'upcoming',
            'label' => $at->lt($now)
                ? 'Seguimiento vencido'
                : 'Seguimiento programado',
            'at' => $at,
        ];
    }

    private static function slaResult(
        string $status,
        string $label,
        ?CarbonImmutable $due = null,
        int $lateMinutes = 0,
        int $remainingMinutes = 0,
    ): array {
        return [
            'status' => $status,
            'label' => $label,
            'due_at' => $due,
            'late_minutes' => $lateMinutes,
            'remaining_minutes' => $remainingMinutes,
        ];
    }

    private static function timeline(Incident $incident): array
    {
        return collect([
            ['key' => 'detected', 'label' => 'Detectado', 'at' => $incident->detected_at],
            ['key' => 'acknowledged', 'label' => 'Reconocido', 'at' => $incident->acknowledged_at],
            ['key' => 'mitigated', 'label' => 'Mitigado', 'at' => $incident->mitigated_at],
            ['key' => 'resolved', 'label' => 'Resuelto', 'at' => $incident->resolved_at],
            ['key' => 'closed', 'label' => 'Cerrado', 'at' => $incident->closed_at],
        ])
            ->filter(fn (array $item): bool => $item['at'] !== null)
            ->map(function (array $item): array {
                $item['at'] = CarbonImmutable::instance($item['at']);

                return $item;
            })
            ->values()
            ->all();
    }

    private static function durationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        if ($minutes < 1440) {
            $hours = intdiv($minutes, 60);
            $remaining = $minutes % 60;

            return $remaining > 0
                ? $hours.' h '.$remaining.' min'
                : $hours.' h';
        }

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        return $hours > 0
            ? $days.' d '.$hours.' h'
            : $days.' d';
    }
}
