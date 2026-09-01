<?php

namespace App\Support;

use App\Models\ObligationOccurrence;
use Carbon\CarbonImmutable;

class ObligationOperationalState
{
    public static function evaluate(
        ObligationOccurrence $occurrence,
        CarbonImmutable $now,
    ): array {
        if ($occurrence->status === 'paid') {
            return [
                'level' => 'paid',
                'rank' => 0,
                'label' => 'Pagado',
                'days' => 0,
            ];
        }

        if ($occurrence->status === 'skipped') {
            return [
                'level' => 'skipped',
                'rank' => 0,
                'label' => 'Omitido',
                'days' => 0,
            ];
        }

        $today = $now->startOfDay();

        $due = CarbonImmutable::instance(
            $occurrence->due_date,
        )->startOfDay();

        $days = (int) $today->diffInDays(
            $due,
            false,
        );

        if ($days < 0) {
            return [
                'level' => 'overdue',
                'rank' => 100 + abs($days),
                'label' => 'Vencido',
                'days' => $days,
            ];
        }

        if ($days === 0) {
            return [
                'level' => 'today',
                'rank' => 95,
                'label' => 'Vence hoy',
                'days' => 0,
            ];
        }

        $reminderDays = max(
            0,
            (int) (
                $occurrence->obligation
                    ?->reminder_days_before
                ?? 7
            ),
        );

        if (
            $occurrence->obligation?->is_critical
            && $days <= $reminderDays
        ) {
            return [
                'level' => 'critical',
                'rank' => 90 - min($days, 20),
                'label' => 'Próximo crítico',
                'days' => $days,
            ];
        }

        if ($days <= $reminderDays) {
            return [
                'level' => 'upcoming',
                'rank' => 70 - min($days, 20),
                'label' => 'Próximo',
                'days' => $days,
            ];
        }

        return [
            'level' => 'planned',
            'rank' => 20 - min($days, 19),
            'label' => 'Planificado',
            'days' => $days,
        ];
    }
}
