<?php

namespace App\Console\Commands;

use App\Models\DailyReviewSession;
use App\Models\User;
use App\Notifications\DailyReviewIncompleteNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DailyReviewReminder extends Command
{
    protected $signature =
        'daily-review:remind {--force : Ignore configured hour}';

    protected $description =
        'Create one internal reminder when today review is incomplete';

    public function handle(): int
    {
        if (
            ! config(
                'daily_review.reminder.enabled',
                true,
            )
        ) {
            $this->info('Recordatorio deshabilitado.');

            return self::SUCCESS;
        }

        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        $now = CarbonImmutable::now(
            $timezone,
        );

        $hour = max(
            0,
            min(
                23,
                (int) config(
                    'daily_review.reminder.hour',
                    17,
                ),
            ),
        );

        if (
            ! $this->option('force')
            && $now->hour < $hour
        ) {
            $this->info('Aún no corresponde recordar.');

            return self::SUCCESS;
        }

        $reviewDate = $now->toDateString();

        $userIds = DB::table(
            'organization_user',
        )
            ->where(
                'is_active',
                true,
            )
            ->distinct()
            ->pluck('user_id');

        $sent = 0;

        User::query()
            ->whereIn('id', $userIds)
            ->chunkById(
                100,
                function ($users) use (
                    $now,
                    $reviewDate,
                    &$sent,
                ): void {
                    foreach ($users as $user) {
                        $review = $this->reviewFor(
                            $user->id,
                            $reviewDate,
                            $now,
                        );

                        if (
                            $review->completed_at
                            || $review->reviewedCount() >= 4
                            || $review->reminder_sent_at
                        ) {
                            continue;
                        }

                        $claimed = DailyReviewSession::query()
                            ->whereKey($review->id)
                            ->whereNull(
                                'reminder_sent_at',
                            )
                            ->update([
                                'reminder_sent_at' => $now,
                            ]);

                        if ($claimed !== 1) {
                            continue;
                        }

                        try {
                            $user->notify(
                                new DailyReviewIncompleteNotification(
                                    $reviewDate,
                                    $review->reviewedCount(),
                                ),
                            );
                        } catch (Throwable $exception) {
                            DailyReviewSession::query()
                                ->whereKey($review->id)
                                ->update([
                                    'reminder_sent_at' => null,
                                ]);

                            throw $exception;
                        }

                        $sent++;
                    }
                },
            );

        $this->info(
            'Recordatorios internos creados: '.$sent,
        );

        return self::SUCCESS;
    }

    private function reviewFor(
        int $userId,
        string $reviewDate,
        CarbonImmutable $now,
    ): DailyReviewSession {
        $review = DailyReviewSession::query()
            ->where(
                'user_id',
                $userId,
            )
            ->whereDate(
                'review_date',
                $reviewDate,
            )
            ->first();

        if ($review) {
            return $review;
        }

        DB::table(
            'daily_review_sessions',
        )->insertOrIgnore([
            'user_id' => $userId,
            'review_date' => $reviewDate,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DailyReviewSession::query()
            ->where(
                'user_id',
                $userId,
            )
            ->whereDate(
                'review_date',
                $reviewDate,
            )
            ->firstOrFail();
    }
}
