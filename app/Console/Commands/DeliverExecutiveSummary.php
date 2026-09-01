<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ExecutiveSummaryNotification;
use App\Support\ExecutiveSummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeliverExecutiveSummary extends Command
{
    protected $signature =
        'summary:deliver
        {period=today : today or week}
        {--force : Send even if already generated today}';

    protected $description =
        'Create internal executive summary notifications';

    public function handle(
        ExecutiveSummaryBuilder $builder,
    ): int {
        $period = (string) $this->argument('period');

        if (! in_array($period, ['today', 'week'], true)) {
            $this->error('Period must be today or week.');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now(
            config('app.timezone', 'America/Lima'),
        );

        $sent = 0;
        $skipped = 0;

        User::query()
            ->orderBy('id')
            ->each(
                function (User $user) use (
                    $builder,
                    $period,
                    $now,
                    &$sent,
                    &$skipped,
                ): void {
                    $organizationIds =
                        DB::table('organization_user')
                            ->where('user_id', $user->id)
                            ->where('is_active', true)
                            ->pluck('organization_id');

                    if ($organizationIds->isEmpty()) {
                        $skipped++;

                        return;
                    }

                    if (
                        ! $this->option('force')
                        && $this->alreadyDelivered(
                            $user,
                            $period,
                            $now,
                        )
                    ) {
                        $skipped++;

                        return;
                    }

                    $summary = $builder->build(
                        $organizationIds,
                        null,
                        $period,
                        $now,
                    );

                    $user->notify(
                        new ExecutiveSummaryNotification(
                            $period,
                            $summary['counts'],
                            $now->toIso8601String(),
                        ),
                    );

                    $sent++;
                },
            );

        $this->info(
            "summary:deliver {$period} | "
            ."sent={$sent} | skipped={$skipped}",
        );

        return self::SUCCESS;
    }

    private function alreadyDelivered(
        User $user,
        string $period,
        CarbonImmutable $now,
    ): bool {
        return $user->notifications()
            ->where(
                'type',
                ExecutiveSummaryNotification::class,
            )
            ->whereDate(
                'created_at',
                $now->toDateString(),
            )
            ->get()
            ->contains(
                fn ($notification): bool =>
                    ($notification->data['period'] ?? null)
                    === $period,
            );
    }
}
