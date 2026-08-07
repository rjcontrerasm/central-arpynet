<?php

namespace App\Support;

use App\Models\RecurringObligation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ObligationOccurrenceGenerator
{
    public function generateFor(
        RecurringObligation $obligation,
        CarbonInterface $from,
        CarbonInterface $to,
    ): int {
        if (! $obligation->is_active) {
            return 0;
        }

        $intervalMonths = $this->intervalMonths(
            $obligation->frequency,
        );

        $anchor = CarbonImmutable::parse(
            $obligation->anchor_date->toDateString(),
        )->startOfDay();

        $fromDate = CarbonImmutable::parse(
            $from->toDateString(),
        )->startOfDay();

        $toDate = CarbonImmutable::parse(
            $to->toDateString(),
        )->endOfDay();

        $endDate = $obligation->end_date
            ? CarbonImmutable::parse(
                $obligation->end_date->toDateString(),
            )->endOfDay()
            : null;

        $cursor = $anchor;
        $created = 0;

        while ($cursor->lt($fromDate)) {
            $cursor = $cursor->addMonthsNoOverflow(
                $intervalMonths,
            );
        }

        while ($cursor->lte($toDate)) {
            if ($endDate && $cursor->gt($endDate)) {
                break;
            }

            $dueDate = $cursor->toDateString();

            $exists = $obligation
                ->occurrences()
                ->whereDate('due_date', $dueDate)
                ->exists();

            if (! $exists) {
                $obligation
                    ->occurrences()
                    ->create([
                        'organization_id' =>
                            $obligation->organization_id,
                        'due_date' => $dueDate,
                        'status' => 'pending',
                        'expected_amount' =>
                            $obligation->expected_amount,
                        'currency' =>
                            $obligation->currency,
                    ]);

                $created++;
            }

            $cursor = $cursor->addMonthsNoOverflow(
                $intervalMonths,
            );
        }

        return $created;
    }

    private function intervalMonths(string $frequency): int
    {
        return match ($frequency) {
            'monthly' => 1,
            'bimonthly' => 2,
            'quarterly' => 3,
            'semiannual' => 6,
            'annual' => 12,
            default => 1,
        };
    }
}
