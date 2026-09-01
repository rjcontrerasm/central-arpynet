<?php

namespace App\Support;

use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;

class ServiceOrderOperationalState
{
    public static function evaluate(
        ServiceOrder $order,
        CarbonImmutable $now,
    ): array {
        $terminal = in_array(
            $order->stage,
            ['paid', 'closed', 'cancelled'],
            true,
        );

        $reasons = [];
        $level = 'normal';
        $rank = 0;

        if (
            ! $terminal
            && $order->next_action_at
            && $order->next_action_at->isPast()
        ) {
            $reasons[] = 'Siguiente acción vencida';
            $level = 'critical';
            $rank = max($rank, 100);
        }

        if (
            ! $terminal
            && $order->end_date
            && $order->end_date->isPast()
        ) {
            $reasons[] = 'Fecha contractual vencida';
            $level = 'critical';
            $rank = max($rank, 95);
        }

        if (
            $order->stage === 'invoiced'
            && ! $order->paid_date
            && $order->invoice_due_date
            && $order->invoice_due_date->isPast()
        ) {
            $reasons[] = 'Cobro vencido';
            $level = 'critical';
            $rank = max($rank, 98);
        }

        $daysInactive = self::daysInactive(
            $order,
            $now,
        );

        if (! $terminal && $daysInactive >= 14) {
            $reasons[] = 'Sin actividad '.$daysInactive.' días';

            if ($level !== 'critical') {
                $level = 'attention';
            }

            $rank = max($rank, 80);
        } elseif (! $terminal && $daysInactive >= 7) {
            $reasons[] = 'Sin actividad '.$daysInactive.' días';

            if ($level === 'normal') {
                $level = 'watch';
            }

            $rank = max($rank, 60);
        }

        if (
            ! $terminal
            && $order->next_action_at
            && ! $order->next_action_at->isPast()
            && $order->next_action_at->diffInDays(
                $now,
                true,
            ) <= 2
        ) {
            $reasons[] = 'Siguiente acción próxima';

            if ($level === 'normal') {
                $level = 'watch';
            }

            $rank = max($rank, 55);
        }

        if (
            ! $terminal
            && empty($order->next_action)
        ) {
            $reasons[] = 'Sin siguiente acción';

            if ($level === 'normal') {
                $level = 'watch';
            }

            $rank = max($rank, 50);
        }

        if ($terminal) {
            $level = 'closed';
            $rank = 0;
        }

        return [
            'level' => $level,
            'rank' => $rank,
            'reasons' => $reasons,
            'days_in_stage' => $order->days_in_stage,
            'days_inactive' => $daysInactive,
            'label' => self::label($level),
        ];
    }

    public static function label(string $level): string
    {
        return match ($level) {
            'critical' => 'Crítica',
            'attention' => 'Atención',
            'watch' => 'Vigilar',
            'closed' => 'Cerrada',
            default => 'Normal',
        };
    }

    private static function daysInactive(
        ServiceOrder $order,
        CarbonImmutable $now,
    ): int {
        $from = $order->last_activity_at
            ?? $order->stage_changed_at
            ?? $order->updated_at
            ?? $order->created_at;

        if (! $from) {
            return 0;
        }

        $from = CarbonImmutable::instance($from);

        if ($from->isAfter($now)) {
            return 0;
        }

        return (int) $from->diffInDays(
            $now,
            false,
        );
    }
}
