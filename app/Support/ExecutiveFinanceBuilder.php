<?php

namespace App\Support;

use App\Models\ObligationOccurrence;
use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ExecutiveFinanceBuilder
{
    public function build(
        Collection $organizationIds,
        ?int $selectedScope,
        CarbonImmutable $now,
    ): array {
        $serviceRows = ServiceOrder::query()
            ->with('client')
            ->whereIn('organization_id', $organizationIds)
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->where('stage', '!=', 'cancelled')
            ->get()
            ->map(function (ServiceOrder $order) use ($now): array {
                return [
                    'order' => $order,
                    'currency' => $this->currency($order->currency),
                    'state' => ServiceOrderFinancialState::evaluate(
                        $order,
                        $now,
                    ),
                ];
            })
            ->filter(
                fn (array $row): bool =>
                    $row['state']['service_amount'] > 0
                    || $row['state']['invoice_amount'] > 0,
            )
            ->values();

        $obligationRows = ObligationOccurrence::query()
            ->whereIn('organization_id', $organizationIds)
            ->when(
                $selectedScope,
                fn ($query) => $query->where(
                    'organization_id',
                    $selectedScope,
                ),
            )
            ->where('status', 'pending')
            ->get()
            ->map(fn (ObligationOccurrence $occurrence): array => [
                'occurrence' => $occurrence,
                'currency' => $this->currency($occurrence->currency),
                'amount' => round(
                    (float) ($occurrence->expected_amount ?? 0),
                    2,
                ),
            ])
            ->filter(fn (array $row): bool => $row['amount'] > 0)
            ->values();

        $serviceByCurrency = $serviceRows->groupBy('currency');
        $obligationByCurrency = $obligationRows->groupBy('currency');

        $currencies = $serviceByCurrency
            ->keys()
            ->merge($obligationByCurrency->keys())
            ->unique()
            ->sort()
            ->values();

        $groups = $currencies->mapWithKeys(
            function (string $currency) use (
                $serviceByCurrency,
                $obligationByCurrency,
                $now,
            ): array {
                $services = $serviceByCurrency->get(
                    $currency,
                    collect(),
                );

                $obligations = $obligationByCurrency->get(
                    $currency,
                    collect(),
                );

                return [
                    $currency => $this->currencySummary(
                        $services,
                        $obligations,
                        $now,
                    ),
                ];
            },
        );

        return [
            'generated_at' => $now,
            'currencies' => $groups,
            'has_data' => $groups->isNotEmpty(),
            'cross_currency_totals_disabled' => true,
            'cash_balance_available' => false,
            'profitability_available' => false,
        ];
    }

    private function currencySummary(
        Collection $services,
        Collection $obligations,
        CarbonImmutable $now,
    ): array {
        $invoiced = $services->filter(
            fn (array $row): bool => $row['state']['is_invoiced'],
        );

        $paid = $invoiced->filter(
            fn (array $row): bool => $row['state']['is_paid'],
        );

        $receivable = $invoiced->filter(
            fn (array $row): bool => $row['state']['outstanding'] > 0,
        );

        $overdue = $receivable->filter(
            fn (array $row): bool => $row['state']['is_overdue'],
        );

        $pendingInvoice = $services->filter(
            fn (array $row): bool =>
                $row['state']['status'] === 'pending_invoice',
        );

        $invoicedAmount = $this->sumState(
            $invoiced,
            'invoice_amount',
        );

        $collectedAmount = $this->sumState(
            $paid,
            'invoice_amount',
        );

        $receivableAmount = $this->sumState(
            $receivable,
            'outstanding',
        );

        $overdueAmount = $this->sumState(
            $overdue,
            'outstanding',
        );

        $pendingInvoiceAmount = $this->sumState(
            $pendingInvoice,
            'service_amount',
        );

        $aging = [
            'current' => 0.0,
            'overdue_1_30' => 0.0,
            'overdue_31_60' => 0.0,
            'overdue_61_plus' => 0.0,
        ];

        foreach ($receivable as $row) {
            $bucket = $this->agingBucket(
                $row['order'],
                $row['state'],
                $now,
            );

            $aging[$bucket] = round(
                $aging[$bucket]
                + (float) $row['state']['outstanding'],
                2,
            );
        }

        $collections7 = $this->scheduledCollections(
            $receivable,
            $now,
            7,
        );

        $collections30 = $this->scheduledCollections(
            $receivable,
            $now,
            30,
        );

        $obligationsOverdue = $this->overdueObligations(
            $obligations,
            $now,
        );

        $obligations7 = $this->scheduledObligations(
            $obligations,
            $now,
            7,
        );

        $obligations30 = $this->scheduledObligations(
            $obligations,
            $now,
            30,
        );

        $topClients = $this->topReceivableClients(
            $receivable,
            $receivableAmount,
        );

        return [
            'service' => [
                'pending_invoice' => $pendingInvoiceAmount,
                'invoiced' => $invoicedAmount,
                'collected' => $collectedAmount,
                'receivable' => $receivableAmount,
                'overdue' => $overdueAmount,
                'collection_rate' => $invoicedAmount > 0
                    ? round(
                        ($collectedAmount / $invoicedAmount) * 100,
                        1,
                    )
                    : null,
                'overdue_rate' => $receivableAmount > 0
                    ? round(
                        ($overdueAmount / $receivableAmount) * 100,
                        1,
                    )
                    : null,
                'receivable_count' => $receivable->count(),
                'overdue_count' => $overdue->count(),
            ],
            'aging' => $aging,
            'schedule' => [
                'collections_7d' => $collections7,
                'collections_30d' => $collections30,
                'obligations_7d' => $obligations7,
                'obligations_30d' => $obligations30,
                'obligations_overdue' => $obligationsOverdue,
                'net_7d' => round(
                    $collections7 - $obligations7,
                    2,
                ),
                'net_30d' => round(
                    $collections30 - $obligations30,
                    2,
                ),
            ],
            'top_receivable_clients' => $topClients,
            'top_client_concentration' =>
                $topClients->first()['share'] ?? null,
        ];
    }

    private function sumState(
        Collection $rows,
        string $field,
    ): float {
        return round(
            $rows->sum(
                fn (array $row): float =>
                    (float) ($row['state'][$field] ?? 0),
            ),
            2,
        );
    }

    private function agingBucket(
        ServiceOrder $order,
        array $state,
        CarbonImmutable $now,
    ): string {
        if (! $state['is_overdue'] || ! $order->invoice_due_date) {
            return 'current';
        }

        $due = CarbonImmutable::parse(
            $order->invoice_due_date->toDateString(),
            config('app.timezone', 'America/Lima'),
        )->startOfDay();

        $days = (int) round(
            $due->diffInDays($now->startOfDay(), true),
        );

        if ($days <= 30) {
            return 'overdue_1_30';
        }

        if ($days <= 60) {
            return 'overdue_31_60';
        }

        return 'overdue_61_plus';
    }

    private function scheduledCollections(
        Collection $receivable,
        CarbonImmutable $now,
        int $days,
    ): float {
        $start = $now->startOfDay();
        $end = $start->addDays($days)->endOfDay();

        return round(
            $receivable
                ->filter(function (array $row) use ($start, $end): bool {
                    $dueDate = $row['order']->invoice_due_date;

                    if (! $dueDate) {
                        return false;
                    }

                    $due = CarbonImmutable::parse(
                        $dueDate->toDateString(),
                        config('app.timezone', 'America/Lima'),
                    );

                    return $due->gte($start)
                        && $due->lte($end);
                })
                ->sum(
                    fn (array $row): float =>
                        (float) $row['state']['outstanding'],
                ),
            2,
        );
    }

    private function overdueObligations(
        Collection $obligations,
        CarbonImmutable $now,
    ): float {
        $today = $now->startOfDay();

        return round(
            $obligations
                ->filter(
                    fn (array $row): bool =>
                        $row['occurrence']->due_date
                        && $row['occurrence']->due_date
                            ->startOfDay()
                            ->lt($today),
                )
                ->sum('amount'),
            2,
        );
    }

    private function scheduledObligations(
        Collection $obligations,
        CarbonImmutable $now,
        int $days,
    ): float {
        $start = $now->startOfDay();
        $end = $start->addDays($days)->endOfDay();

        return round(
            $obligations
                ->filter(
                    fn (array $row): bool =>
                        $row['occurrence']->due_date
                        && $row['occurrence']->due_date
                            ->startOfDay()
                            ->gte($start)
                        && $row['occurrence']->due_date
                            ->endOfDay()
                            ->lte($end),
                )
                ->sum('amount'),
            2,
        );
    }

    private function topReceivableClients(
        Collection $receivable,
        float $receivableAmount,
    ): Collection {
        if ($receivableAmount <= 0) {
            return collect();
        }

        return $receivable
            ->groupBy(
                fn (array $row): string =>
                    (string) ($row['order']->client_id ?: 'none'),
            )
            ->map(function (Collection $rows) use ($receivableAmount): array {
                $first = $rows->first();
                $amount = round(
                    $rows->sum(
                        fn (array $row): float =>
                            (float) $row['state']['outstanding'],
                    ),
                    2,
                );

                return [
                    'client_id' => $first['order']->client_id,
                    'name' => $first['order']->client?->name
                        ?: 'Sin cliente',
                    'amount' => $amount,
                    'share' => round(
                        ($amount / $receivableAmount) * 100,
                        1,
                    ),
                    'orders' => $rows->count(),
                ];
            })
            ->sortByDesc('amount')
            ->take(5)
            ->values();
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));

        return $currency !== ''
            ? $currency
            : 'N/D';
    }
}
