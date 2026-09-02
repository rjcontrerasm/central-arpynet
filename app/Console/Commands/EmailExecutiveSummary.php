<?php

namespace App\Console\Commands;

use App\Mail\ExecutiveSummaryMail;
use App\Models\User;
use App\Support\ExecutiveSummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailExecutiveSummary extends Command
{
    protected $signature =
        'summary:email
        {period=today : today or week}
        {--force : Resend even if already sent today}';

    protected $description =
        'Send executive summary by email';

    public function handle(
        ExecutiveSummaryBuilder $builder,
    ): int {
        if (
            ! config(
                'central.summary_mail.enabled',
                true,
            )
        ) {
            $this->info(
                'summary:email disabled by configuration',
            );

            return self::SUCCESS;
        }

        $period = (string) $this->argument('period');

        if (! in_array($period, ['today', 'week'], true)) {
            $this->error(
                'Period must be today or week.',
            );

            return self::FAILURE;
        }

        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        User::query()
            ->orderBy('id')
            ->each(
                function (User $user) use (
                    $builder,
                    $period,
                    $now,
                    &$sent,
                    &$skipped,
                    &$failed,
                ): void {
                    if (! filter_var(
                        $user->email,
                        FILTER_VALIDATE_EMAIL,
                    )) {
                        $skipped++;

                        return;
                    }

                    $organizationIds =
                        DB::table('organization_user')
                            ->where(
                                'user_id',
                                $user->id,
                            )
                            ->where(
                                'is_active',
                                true,
                            )
                            ->pluck('organization_id');

                    if ($organizationIds->isEmpty()) {
                        $skipped++;

                        return;
                    }

                    $delivery = DB::table(
                        'summary_email_deliveries',
                    )
                        ->where(
                            'user_id',
                            $user->id,
                        )
                        ->where(
                            'period',
                            $period,
                        )
                        ->whereDate(
                            'summary_date',
                            $now->toDateString(),
                        )
                        ->first();

                    if (
                        $delivery
                        && $delivery->status === 'sent'
                        && ! $this->option('force')
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

                    $values = [
                        'recipient' => $user->email,
                        'status' => 'pending',
                        'sent_at' => null,
                        'error_message' => null,
                        'updated_at' => $now,
                    ];

                    if ($delivery) {
                        DB::table(
                            'summary_email_deliveries',
                        )
                            ->where(
                                'id',
                                $delivery->id,
                            )
                            ->update($values);
                    } else {
                        $deliveryId = DB::table(
                            'summary_email_deliveries',
                        )->insertGetId(
                            array_merge(
                                $values,
                                [
                                    'user_id' =>
                                        $user->id,
                                    'period' =>
                                        $period,
                                    'summary_date' =>
                                        $now->toDateString(),
                                    'created_at' =>
                                        $now,
                                ],
                            ),
                        );

                        $delivery = DB::table(
                            'summary_email_deliveries',
                        )->find($deliveryId);
                    }

                    try {
                        Mail::mailer(
                            config(
                                'central.summary_mail.mailer',
                                'sendmail',
                            ),
                        )
                            ->to($user->email)
                            ->send(
                                new ExecutiveSummaryMail(
                                    $period,
                                    $summary['counts'],
                                    $now
                                        ->toIso8601String(),
                                    route(
                                        'executive-summary.show',
                                        [
                                            'period' =>
                                                $period,
                                        ],
                                    ),
                                ),
                            );

                        DB::table(
                            'summary_email_deliveries',
                        )
                            ->where(
                                'id',
                                $delivery->id,
                            )
                            ->update([
                                'status' => 'sent',
                                'sent_at' => $now,
                                'error_message' => null,
                                'updated_at' => $now,
                            ]);

                        $sent++;
                    } catch (Throwable $exception) {
                        DB::table(
                            'summary_email_deliveries',
                        )
                            ->where(
                                'id',
                                $delivery->id,
                            )
                            ->update([
                                'status' => 'failed',
                                'error_message' =>
                                    mb_substr(
                                        $exception
                                            ->getMessage(),
                                        0,
                                        2000,
                                    ),
                                'updated_at' => $now,
                            ]);

                        report($exception);

                        $failed++;
                    }
                },
            );

        $this->info(
            "summary:email {$period} | "
            ."sent={$sent} | "
            ."skipped={$skipped} | "
            ."failed={$failed}",
        );

        return $failed === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
