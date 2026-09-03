<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WhatsappOutboundService;
use App\Support\ExecutiveSummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsappCriticalAlerts extends Command
{
    protected $signature = 'alerts:whatsapp-critical {--force : Ignore cooldown and send current critical items}';
    protected $description = 'Send deduplicated critical operational alerts by WhatsApp';

    public function handle(ExecutiveSummaryBuilder $builder, WhatsappOutboundService $outbound): int
    {
        if (! config('central.critical_whatsapp.enabled', false)) {
            $this->info('alerts:whatsapp-critical disabled by configuration');
            return self::SUCCESS;
        }

        if (! config('whatsapp.outbound_enabled')) {
            $this->info('alerts:whatsapp-critical blocked: outbound disabled');
            return self::SUCCESS;
        }

        $recipient = collect(config('whatsapp.allowed_wa_ids', []))->first();
        $email = strtolower(trim((string) config('central.critical_whatsapp.user_email', '')));

        if ($email === '' || ! is_scalar($recipient) || trim((string) $recipient) === '') {
            $this->error('Critical WhatsApp recipient is not configured.');
            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            $this->error('Critical WhatsApp user was not found.');
            return self::FAILURE;
        }

        $organizationIds = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        if ($organizationIds->isEmpty()) {
            $this->error('Critical WhatsApp user has no active organizations.');
            return self::FAILURE;
        }

        $now = CarbonImmutable::now(config('app.timezone', 'America/Lima'));
        $summary = $builder->build($organizationIds, null, 'today', $now);
        $maxItems = max(1, min(12, (int) config('central.critical_whatsapp.max_items', 5)));
        $allCritical = collect(
            $summary['attention_all']
            ?? $summary['attention']
            ?? [],
        )
            ->where('level', 'critical')
            ->values();
        $critical = $allCritical->take($maxItems)->values();
        $cooldownMinutes = max(15, (int) config('central.critical_whatsapp.cooldown_minutes', 360));
        $retryMinutes = max(15, (int) config('central.critical_whatsapp.retry_minutes', 60));
        $template = trim((string) config('central.critical_whatsapp.template', 'central_critical_alert'));
        $language = trim((string) config('central.critical_whatsapp.language', 'es_PE'));

        $activeFingerprints = $allCritical
            ->map(fn (array $item): string => ($item['type'] ?? 'unknown').':'.(string) ($item['id'] ?? 0))
            ->all();
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($critical as $item) {
            $fingerprint = ($item['type'] ?? 'unknown').':'.(string) ($item['id'] ?? 0);
            $stateHash = hash('sha256', json_encode([
                'level' => $item['level'] ?? null,
                'rank' => $item['rank'] ?? null,
                'title' => $item['title'] ?? null,
                'reasons' => $item['reasons'] ?? [],
                'date_label' => $item['date_label'] ?? null,
                'meta' => $item['meta'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

            $state = DB::table('whatsapp_critical_alert_states')
                ->where('user_id', $user->id)
                ->where('fingerprint', $fingerprint)
                ->first();

            $send = $this->option('force') || $this->shouldSend(
                $state,
                $stateHash,
                $now,
                $cooldownMinutes,
                $retryMinutes,
            );

            if (! $send) {
                $skipped++;
                continue;
            }

            $reason = collect($item['reasons'] ?? [])->filter()->implode(' · ');
            if ($reason === '') {
                $reason = 'Requiere atención inmediata';
            }

            $result = $outbound->sendTemplate(
                (string) $recipient,
                $template,
                $language,
                [
                    Str::limit((string) ($item['type_label'] ?? 'Elemento'), 80, ''),
                    Str::limit((string) ($item['title'] ?? 'Sin título'), 180, ''),
                    Str::limit((string) ($item['organization'] ?? 'Sin ámbito'), 100, ''),
                    Str::limit($reason, 220, ''),
                    Str::limit((string) ($item['date_label'] ?? 'Sin fecha'), 100, ''),
                    (string) ($item['url'] ?? route('executive-summary.show', ['period' => 'today'])),
                ],
            );

            $base = [
                'subject_type' => (string) ($item['type'] ?? 'unknown'),
                'subject_id' => (int) ($item['id'] ?? 0),
                'organization_id' => isset($item['organization_id']) ? (int) $item['organization_id'] : null,
                'title_sha256' => hash('sha256', (string) ($item['title'] ?? '')),
                'last_level' => 'critical',
                'last_state_hash' => $stateHash,
                'last_attempt_at' => $now,
                'resolved_at' => null,
                'updated_at' => $now,
            ];

            if ($result['status'] === 'sent') {
                $sent++;
                $values = array_merge($base, [
                    'last_sent_at' => $now,
                    'last_message_id' => $result['message_id'],
                    'last_error_code' => null,
                    'sent_count' => (int) ($state?->sent_count ?? 0) + 1,
                    'failed_count' => (int) ($state?->failed_count ?? 0),
                ]);
            } else {
                $failed++;
                $values = array_merge($base, [
                    'last_sent_at' => $state?->last_sent_at,
                    'last_message_id' => $state?->last_message_id,
                    'last_error_code' => $result['error_code'] ?? 'unknown',
                    'sent_count' => (int) ($state?->sent_count ?? 0),
                    'failed_count' => (int) ($state?->failed_count ?? 0) + 1,
                ]);
            }

            if ($state) {
                DB::table('whatsapp_critical_alert_states')->where('id', $state->id)->update($values);
            } else {
                DB::table('whatsapp_critical_alert_states')->insert(array_merge($values, [
                    'user_id' => $user->id,
                    'fingerprint' => $fingerprint,
                    'created_at' => $now,
                ]));
            }
        }

        $query = DB::table('whatsapp_critical_alert_states')
            ->where('user_id', $user->id)
            ->where('last_level', 'critical');
        if ($activeFingerprints !== []) {
            $query->whereNotIn('fingerprint', $activeFingerprints);
        }
        $resolved = $query->update([
            'last_level' => 'resolved',
            'resolved_at' => $now,
            'updated_at' => $now,
        ]);

        $this->info(
            'alerts:whatsapp-critical'
            .' | critical='.$critical->count()
            .' | sent='.$sent
            .' | skipped='.$skipped
            .' | failed='.$failed
            .' | resolved='.$resolved
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function shouldSend(
        ?object $state,
        string $stateHash,
        CarbonImmutable $now,
        int $cooldownMinutes,
        int $retryMinutes,
    ): bool {
        if (! $state) {
            return true;
        }
        if ($state->last_level !== 'critical' || $state->last_state_hash !== $stateHash) {
            return true;
        }
        if (filled($state->last_error_code) && $state->last_attempt_at) {
            return CarbonImmutable::parse($state->last_attempt_at)->lte($now->subMinutes($retryMinutes));
        }
        if (! $state->last_sent_at) {
            return true;
        }
        return CarbonImmutable::parse($state->last_sent_at)->lte($now->subMinutes($cooldownMinutes));
    }
}
