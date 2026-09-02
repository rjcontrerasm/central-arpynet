<?php

namespace App\Filament\Pages;

use App\Models\WhatsappInboundMessage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Integraciones extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedLink;
    protected static ?string $navigationLabel = 'Integraciones';
    protected static ?string $title = 'Integraciones';
    protected static ?string $slug = 'integraciones';
    protected static ?int $navigationSort = 80;
    protected string $view = 'filament.pages.integraciones';

    public function getViewData(): array
    {
        $connection = auth()->user()?->googleCalendarConnection;
        $latestInbound = WhatsappInboundMessage::query()->orderByDesc('id')->first();
        $latestSummary = Schema::hasTable('summary_whatsapp_deliveries')
            ? DB::table('summary_whatsapp_deliveries')->orderByDesc('id')->first()
            : null;
        $activeCritical = Schema::hasTable('whatsapp_critical_alert_states')
            ? DB::table('whatsapp_critical_alert_states')->where('last_level', 'critical')->count()
            : 0;

        return [
            'connection' => $connection,
            'configured' => filled(config('services.google_calendar.client_id'))
                && filled(config('services.google_calendar.client_secret')),
            'whatsapp' => [
                'inbound_enabled' => (bool) config('whatsapp.enabled'),
                'outbound_enabled' => (bool) config('whatsapp.outbound_enabled'),
                'access_token_configured' => filled(config('whatsapp.access_token')),
                'phone_number_id_configured' => filled(config('whatsapp.phone_number_id')),
                'allowed_senders' => count(config('whatsapp.allowed_wa_ids', [])),
                'summary_enabled' => (bool) config('central.summary_whatsapp.enabled'),
                'summary_template' => config('central.summary_whatsapp.template', 'central_executive_summary'),
                'summary_language' => config('central.summary_whatsapp.language', 'es_PE'),
                'critical_enabled' => (bool) config('central.critical_whatsapp.enabled'),
                'critical_template' => config('central.critical_whatsapp.template', 'central_critical_alert'),
                'critical_language' => config('central.critical_whatsapp.language', 'es_PE'),
                'critical_cooldown_minutes' => (int) config('central.critical_whatsapp.cooldown_minutes', 360),
                'latest_inbound_status' => $latestInbound?->status,
                'latest_confirmation_status' => $latestInbound?->confirmation_status,
                'latest_summary_status' => $latestSummary?->status,
                'active_critical_states' => $activeCritical,
            ],
        ];
    }
}
