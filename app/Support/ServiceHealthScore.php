<?php

namespace App\Support;

use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;

class ServiceHealthScore
{
    public static function evaluate(
        ServiceOrder $order,
        CarbonImmutable $now,
    ): array {
        if ($order->stage === 'cancelled') {
            return [
                'applicable' => false,
                'score' => null,
                'status' => 'not_applicable',
                'label' => 'No aplica',
                'css' => '',
                'operational_score' => null,
                'financial_score' => null,
                'operational' => null,
                'financial' => null,
                'reasons' => ['Servicio cancelado'],
            ];
        }

        $operational = ServiceOrderOperationalState::evaluate(
            $order,
            $now,
        );

        $financial = ServiceOrderFinancialState::evaluate(
            $order,
            $now,
        );

        $operationalScore = match ($operational['level']) {
            'critical' => 20,
            'attention' => 50,
            'watch' => 75,
            'closed' => 100,
            default => 100,
        };

        $financialScore = self::financialScore(
            $order,
            $financial,
            $now,
        );

        $score = (int) round(
            ($operationalScore * 0.65)
            + ($financialScore * 0.35),
        );

        $status = self::status($score);
        $reasons = collect($operational['reasons']);

        match ($financial['status']) {
            'overdue' => $reasons->push(
                'Cobranza vencida '
                .self::overdueDays($order, $now)
                .' días',
            ),
            'receivable' => $reasons->push('Cobranza pendiente'),
            'pending_invoice' => $reasons->push('Pendiente de facturación'),
            default => null,
        };

        return [
            'applicable' => true,
            'score' => $score,
            'status' => $status,
            'label' => self::label($status),
            'css' => self::css($status),
            'operational_score' => $operationalScore,
            'financial_score' => $financialScore,
            'operational' => $operational,
            'financial' => $financial,
            'reasons' => $reasons
                ->unique()
                ->values()
                ->all(),
        ];
    }

    public static function status(int $score): string
    {
        return match (true) {
            $score >= 85 => 'healthy',
            $score >= 70 => 'watch',
            $score >= 50 => 'risk',
            default => 'critical',
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'healthy' => 'Saludable',
            'watch' => 'Vigilar',
            'risk' => 'En riesgo',
            'critical' => 'Crítico',
            default => 'No aplica',
        };
    }

    public static function css(string $status): string
    {
        return match ($status) {
            'watch' => 'watch',
            'risk' => 'attention',
            'critical' => 'critical',
            default => '',
        };
    }

    private static function financialScore(
        ServiceOrder $order,
        array $financial,
        CarbonImmutable $now,
    ): int {
        return match ($financial['status']) {
            'paid', 'no_amount' => 100,
            'pending_invoice' => 90,
            'receivable' => 85,
            'overdue' => match (true) {
                self::overdueDays($order, $now) <= 30 => 40,
                self::overdueDays($order, $now) <= 60 => 20,
                default => 0,
            },
            default => 100,
        };
    }

    private static function overdueDays(
        ServiceOrder $order,
        CarbonImmutable $now,
    ): int {
        if (! $order->invoice_due_date) {
            return 0;
        }

        $due = CarbonImmutable::parse(
            $order->invoice_due_date->toDateString(),
            config('app.timezone', 'America/Lima'),
        )->startOfDay();

        if ($due->gte($now->startOfDay())) {
            return 0;
        }

        return (int) round(
            $due->diffInDays($now->startOfDay(), true),
        );
    }
}
