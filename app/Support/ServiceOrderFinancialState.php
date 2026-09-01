<?php

namespace App\Support;

use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;

class ServiceOrderFinancialState
{
    public static function evaluate(
        ServiceOrder $order,
        CarbonImmutable $now,
    ): array {
        $serviceAmount = self::number(
            $order->amount,
        );

        $invoiceAmount = self::number(
            $order->invoice_amount,
        );

        if (
            $invoiceAmount <= 0
            && $order->invoice_number
            && $serviceAmount > 0
        ) {
            $invoiceAmount = $serviceAmount;
        }

        $isInvoiced = (bool) (
            $order->invoice_number
            || $order->invoice_date
            || $invoiceAmount > 0
            || in_array(
                $order->stage,
                ['invoiced', 'paid', 'closed'],
                true,
            )
        );

        $isPaid = (bool) $order->paid_date;

        $isOverdue = $isInvoiced
            && ! $isPaid
            && $order->invoice_due_date
            && $order->invoice_due_date->isPast();

        $outstanding = (
            $isInvoiced
            && ! $isPaid
        )
            ? $invoiceAmount
            : 0.0;

        $status = match (true) {
            $isPaid => 'paid',
            $isOverdue => 'overdue',
            $isInvoiced => 'receivable',
            $serviceAmount > 0 => 'pending_invoice',
            default => 'no_amount',
        };

        return [
            'service_amount' => $serviceAmount,
            'invoice_amount' => $invoiceAmount,
            'outstanding' => $outstanding,
            'is_invoiced' => $isInvoiced,
            'is_paid' => $isPaid,
            'is_overdue' => $isOverdue,
            'status' => $status,
            'label' => self::label($status),
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'paid' => 'Pagado',
            'overdue' => 'Cobro vencido',
            'receivable' => 'Por cobrar',
            'pending_invoice' => 'Por facturar',
            default => 'Sin monto',
        };
    }

    private static function number(
        mixed $value,
    ): float {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }
}
