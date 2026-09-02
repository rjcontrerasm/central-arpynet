<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappStatus extends Command
{
    protected $signature = 'whatsapp:status';
    protected $description = 'Show safe WhatsApp integration status without secrets';

    public function handle(): int
    {
        $this->line('WHATSAPP_ENABLED='.(config('whatsapp.enabled') ? 'YES' : 'NO'));
        $this->line('OUTBOUND_ENABLED='.(config('whatsapp.outbound_enabled') ? 'YES' : 'NO'));
        $this->line('ACCESS_TOKEN_CONFIGURED='.(filled(config('whatsapp.access_token')) ? 'YES' : 'NO'));
        $this->line('PHONE_NUMBER_ID_CONFIGURED='.(filled(config('whatsapp.phone_number_id')) ? 'YES' : 'NO'));
        $this->line('ALLOWED_WA_IDS='.count(config('whatsapp.allowed_wa_ids', [])));
        $this->line('SUMMARY_WHATSAPP_ENABLED='.(config('central.summary_whatsapp.enabled') ? 'YES' : 'NO'));
        $this->line('SUMMARY_TEMPLATE='.config('central.summary_whatsapp.template', 'central_executive_summary'));
        $this->line('SUMMARY_LANGUAGE='.config('central.summary_whatsapp.language', 'es_PE'));
        $this->line('CRITICAL_WHATSAPP_ENABLED='.(config('central.critical_whatsapp.enabled') ? 'YES' : 'NO'));
        $this->line('CRITICAL_TEMPLATE='.config('central.critical_whatsapp.template', 'central_critical_alert'));
        $this->line('CRITICAL_LANGUAGE='.config('central.critical_whatsapp.language', 'es_PE'));
        $this->line('CRITICAL_COOLDOWN_MINUTES='.config('central.critical_whatsapp.cooldown_minutes', 360));

        $latest = Schema::hasTable('whatsapp_inbound_messages')
            ? DB::table('whatsapp_inbound_messages')->orderByDesc('id')->first()
            : null;
        $this->line('LAST_INBOUND_STATUS='.($latest->status ?? 'NONE'));
        $this->line('LAST_CONFIRMATION_STATUS='.($latest?->confirmation_status ?? 'NONE'));

        $summary = Schema::hasTable('summary_whatsapp_deliveries')
            ? DB::table('summary_whatsapp_deliveries')->orderByDesc('id')->first()
            : null;
        $this->line('LAST_SUMMARY_STATUS='.($summary->status ?? 'NONE'));

        $active = Schema::hasTable('whatsapp_critical_alert_states')
            ? DB::table('whatsapp_critical_alert_states')->where('last_level', 'critical')->count()
            : 0;
        $this->line('ACTIVE_CRITICAL_STATES='.$active);
        $this->line('SECRET_VALUES_EXPOSED=NO');
        return self::SUCCESS;
    }
}
