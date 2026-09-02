<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WhatsappOutboundService;
use App\Support\ExecutiveSummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WhatsappExecutiveSummary extends Command
{
    protected $signature =
        'summary:whatsapp
        {period=today : today or week}
        {--force : Resend even if already sent today}';

    protected $description =
        'Send executive summary by WhatsApp';

    public function handle(
        ExecutiveSummaryBuilder $builder,
        WhatsappOutboundService $outbound,
    ): int {
        if (
            ! config(
                'central.summary_whatsapp.enabled',
                false,
            )
        ) {
            $this->info(
                'summary:whatsapp disabled by configuration',
            );

            return self::SUCCESS;
        }

        $period = (string) $this->argument(
            'period',
        );

        if (! in_array(
            $period,
            ['today', 'week'],
            true,
        )) {
            $this->error(
                'Period must be today or week.',
            );

            return self::FAILURE;
        }

        $email = strtolower(
            trim(
                (string) config(
                    'central.summary_whatsapp.user_email',
                    '',
                ),
            ),
        );

        $recipient = collect(
            config(
                'whatsapp.allowed_wa_ids',
                [],
            ),
        )->first();

        if (
            $email === ''
            || ! is_scalar($recipient)
            || trim((string) $recipient) === ''
        ) {
            $this->error(
                'WhatsApp summary recipient is not configured.',
            );

            return self::FAILURE;
        }

        $user = User::query()
            ->whereRaw(
                'LOWER(email) = ?',
                [$email],
            )
            ->first();

        if (! $user) {
            $this->error(
                'WhatsApp summary user was not found.',
            );

            return self::FAILURE;
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
            $this->error(
                'WhatsApp summary user has no active organizations.',
            );

            return self::FAILURE;
        }

        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $delivery = DB::table(
            'summary_whatsapp_deliveries',
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
            $this->info(
                "summary:whatsapp {$period} | skipped=1",
            );

            return self::SUCCESS;
        }

        $summary = $builder->build(
            $organizationIds,
            null,
            $period,
            $now,
        );

        $counts = $summary['counts'];

        $template = trim(
            (string) config(
                'central.summary_whatsapp.template',
                '',
            ),
        );

        $language = trim(
            (string) config(
                'central.summary_whatsapp.language',
                'es',
            ),
        );

        $result = $outbound->sendTemplate(
            (string) $recipient,
            $template,
            $language,
            [
                $period === 'week'
                    ? 'Semana'
                    : 'Hoy',
                (int) $counts['critical'],
                (int) $counts['attention'],
                (int) $counts['tasks_due'],
                (int) $counts['waiting_followups'],
                (int) $counts['service_actions'],
                (int) $counts['obligations'],
                (int) $counts['projects'],
                route(
                    'executive-summary.show',
                    ['period' => $period],
                ),
            ],
        );

        $values = [
            'recipient_sha256' =>
                hash(
                    'sha256',
                    (string) $recipient,
                ),
            'status' => $result['status'],
            'message_id' =>
                $result['message_id'],
            'sent_at' =>
                $result['status'] === 'sent'
                    ? $now
                    : null,
            'error_code' =>
                $result['error_code'],
            'updated_at' => $now,
        ];

        if ($delivery) {
            DB::table(
                'summary_whatsapp_deliveries',
            )
                ->where(
                    'id',
                    $delivery->id,
                )
                ->update($values);
        } else {
            DB::table(
                'summary_whatsapp_deliveries',
            )->insert(
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
        }

        $this->info(
            "summary:whatsapp {$period} | "
            ."status={$result['status']}",
        );

        return $result['status'] === 'sent'
            ? self::SUCCESS
            : self::FAILURE;
    }
}
