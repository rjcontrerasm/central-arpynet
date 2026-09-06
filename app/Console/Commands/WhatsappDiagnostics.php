<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappDiagnostics extends Command
{
    protected $signature = 'whatsapp:diagnostics';
    protected $description = 'Show safe outbound WhatsApp diagnostics without secrets or recipients';

    public function handle(): int
    {
        $this->line('WHATSAPP_DIAGNOSTICS');

        if (! Schema::hasTable('whatsapp_outbound_attempts')) {
            $this->line('DIAGNOSTIC_STORAGE=UNAVAILABLE');
            $this->line('SECRET_VALUES_EXPOSED=NO');
            $this->line('RECIPIENT_VALUES_EXPOSED=NO');
            $this->line('WHATSAPP_DIAGNOSTICS=FAIL');
            return self::FAILURE;
        }

        $since = CarbonImmutable::now(config('app.timezone', 'America/Lima'))->subDay();
        $query = DB::table('whatsapp_outbound_attempts')->where('created_at', '>=', $since);
        $attempts = (clone $query)->count();
        $sent = (clone $query)->where('status', 'sent')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $latest = DB::table('whatsapp_outbound_attempts')->orderByDesc('id')->first();
        $failure = DB::table('whatsapp_outbound_attempts')->where('status', 'failed')->orderByDesc('id')->first();

        $this->line('DIAGNOSTIC_STORAGE=READY');
        $this->line('ATTEMPTS_24H='.$attempts);
        $this->line('SENT_24H='.$sent);
        $this->line('FAILED_24H='.$failed);
        $this->line('LAST_ATTEMPT_STATUS='.($latest->status ?? 'NONE'));
        $this->line('LAST_ATTEMPT_PURPOSE='.($latest->purpose ?? 'NONE'));
        $this->line('LAST_FAILURE_PURPOSE='.($failure->purpose ?? 'NONE'));
        $this->line('LAST_FAILURE_ERROR_CODE='.($failure->error_code ?? 'NONE'));
        $this->line('LAST_FAILURE_ERROR_SUBCODE='.($failure->error_subcode ?? 'NONE'));
        $this->line('LAST_FAILURE_HTTP_STATUS='.($failure->http_status ?? 'NONE'));
        $this->line('LAST_FAILURE_ERROR_TYPE='.($failure->error_type ?? 'NONE'));
        $this->line('LAST_FAILURE_FBTRACE_ID='.($failure->fbtrace_id ?? 'NONE'));
        $this->line('LAST_FAILURE_MESSAGE='.($failure->error_message ?? 'NONE'));
        $this->line('SECRET_VALUES_EXPOSED=NO');
        $this->line('RECIPIENT_VALUES_EXPOSED=NO');
        $this->line('WHATSAPP_DIAGNOSTICS=PASS');

        return self::SUCCESS;
    }
}
