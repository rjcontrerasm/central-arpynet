<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class WhatsappSecurityAudit extends Command
{
    protected $signature = 'whatsapp:security-audit';
    protected $description = 'Audit WhatsApp security configuration without exposing secrets';

    public function handle(): int
    {
        $enabled = (bool) config('whatsapp.enabled');
        $verifyToken = trim((string) config('whatsapp.verify_token', ''));
        $appSecret = trim((string) config('whatsapp.app_secret', ''));
        $allowed = config('whatsapp.allowed_wa_ids', []);
        $outbound = (bool) config('whatsapp.outbound_enabled');
        $accessToken = trim((string) config('whatsapp.access_token', ''));
        $phoneNumberId = trim((string) config('whatsapp.phone_number_id', ''));

        $checks = [
            'INBOUND_VERIFY_TOKEN' => ! $enabled || $verifyToken !== '',
            'INBOUND_APP_SECRET' => ! $enabled || (strlen($appSecret) >= 16 && strlen($appSecret) <= 128),
            'ALLOWED_SENDERS' => ! $enabled || (is_array($allowed) && count($allowed) >= 1),
            'OUTBOUND_CREDENTIAL_CONSISTENCY' => $outbound
                ? ($accessToken !== '' && $phoneNumberId !== '')
                : $accessToken === '',
        ];

        $envPath = base_path('.env');
        $perms = is_file($envPath) ? (fileperms($envPath) & 0777) : null;
        $checks['ENV_FILE_PERMISSIONS'] = $perms !== null && in_array($perms, [0600, 0640], true);

        $example = @file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) ?: [];
        $secretKeys = ['WHATSAPP_VERIFY_TOKEN', 'WHATSAPP_APP_SECRET', 'WHATSAPP_ACCESS_TOKEN'];
        $exampleSafe = true;
        foreach ($example as $line) {
            foreach ($secretKeys as $key) {
                if (str_starts_with($line, $key.'=') && trim(substr($line, strlen($key) + 1)) !== '') {
                    $exampleSafe = false;
                }
            }
        }
        $checks['ENV_EXAMPLE_SECRETS_EMPTY'] = $exampleSafe;

        $columns = Schema::hasTable('whatsapp_inbound_messages')
            ? Schema::getColumnListing('whatsapp_inbound_messages')
            : [];
        $checks['NO_RAW_MESSAGE_COLUMNS'] = array_intersect(
            ['payload', 'raw_payload', 'message_text', 'text', 'body'],
            $columns,
        ) === [];
        $checks['SUMMARY_LANGUAGE_ES_PE'] = config('central.summary_whatsapp.language', 'es_PE') === 'es_PE';

        $failed = 0;
        foreach ($checks as $name => $pass) {
            $this->line($name.'='.($pass ? 'PASS' : 'FAIL'));
            if (! $pass) {
                $failed++;
            }
        }
        $this->line('SECRET_VALUES_EXPOSED=NO');
        $this->line('SECURITY_AUDIT='.($failed === 0 ? 'PASS' : 'FAIL'));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
