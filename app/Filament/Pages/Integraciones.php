<?php

namespace App\Filament\Pages;

use App\Models\WhatsappInboundMessage;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Integraciones extends Page
{
    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedLink;

    protected static ?string $navigationLabel =
        'Integraciones';

    protected static ?string $title =
        'Integraciones';

    protected static ?string $slug =
        'integraciones';

    protected static ?int $navigationSort =
        80;

    protected string $view =
        'filament.pages.integraciones';

    public function getViewData(): array
    {
        $connection = auth()->user()
            ?->googleCalendarConnection;

        $configured =
            filled(
                config(
                    'services.google_calendar.client_id',
                ),
            )
            && filled(
                config(
                    'services.google_calendar.client_secret',
                ),
            );

        $calendarConnected =
            $configured
            && (bool) $connection?->isConnected();

        $calendarHasCurrentError =
            $connection?->last_error_at
            && (
                ! $connection->last_sync_at
                || $connection->last_error_at
                    ->greaterThan(
                        $connection->last_sync_at,
                    )
            );

        $calendarStatus = match (true) {
            ! $configured => [
                'tone' => 'neutral',
                'label' => 'Pendiente de configuración',
                'detail' =>
                    'Faltan credenciales OAuth en Central.',
            ],
            $calendarConnected
                && $calendarHasCurrentError => [
                    'tone' => 'warning',
                    'label' => 'Revisar',
                    'detail' =>
                        'La última operación registró un error.',
                ],
            $calendarConnected => [
                'tone' => 'success',
                'label' => 'Operativo',
                'detail' =>
                    'Calendario conectado y disponible.',
            ],
            default => [
                'tone' => 'info',
                'label' => 'Listo para conectar',
                'detail' =>
                    'OAuth está configurado; falta autorizar la cuenta.',
            ],
        };

        $latestInbound =
            WhatsappInboundMessage::query()
                ->orderByDesc('id')
                ->first();

        $latestSummary =
            Schema::hasTable(
                'summary_whatsapp_deliveries',
            )
                ? DB::table(
                    'summary_whatsapp_deliveries',
                )
                    ->orderByDesc('id')
                    ->first()
                : null;

        $latestCritical =
            Schema::hasTable(
                'whatsapp_critical_alert_states',
            )
                ? DB::table(
                    'whatsapp_critical_alert_states',
                )
                    ->orderByDesc('updated_at')
                    ->first()
                : null;

        $activeCritical =
            Schema::hasTable(
                'whatsapp_critical_alert_states',
            )
                ? DB::table(
                    'whatsapp_critical_alert_states',
                )
                    ->where(
                        'last_level',
                        'critical',
                    )
                    ->count()
                : 0;

        $inboundEnabled =
            (bool) config(
                'whatsapp.enabled',
            );

        $outboundEnabled =
            (bool) config(
                'whatsapp.outbound_enabled',
            );

        $accessTokenConfigured =
            filled(
                config(
                    'whatsapp.access_token',
                ),
            );

        $outboundStatus = match (true) {
            $outboundEnabled
                && $accessTokenConfigured => [
                    'tone' => 'success',
                    'label' => 'Operativo',
                    'detail' =>
                        'Central puede enviar mensajes.',
            ],
            $outboundEnabled
                && ! $accessTokenConfigured => [
                    'tone' => 'danger',
                    'label' => 'Configuración incompleta',
                    'detail' =>
                        'El envío está activo sin una credencial válida.',
            ],
            ! $outboundEnabled
                && $accessTokenConfigured => [
                    'tone' => 'info',
                    'label' => 'Pausado',
                    'detail' =>
                        'Existe credencial, pero el envío está deshabilitado.',
            ],
            default => [
                'tone' => 'warning',
                'label' => 'Pendiente de Meta',
                'detail' =>
                    'Falta el System User Access Token permanente.',
            ],
        };

        $summaryEnabled =
            (bool) config(
                'central.summary_whatsapp.enabled',
            );

        $criticalEnabled =
            (bool) config(
                'central.critical_whatsapp.enabled',
            );

        return [
            'connection' => $connection,
            'configured' => $configured,
            'calendar' => [
                ...$calendarStatus,
                'connected' =>
                    $calendarConnected,
                'calendar_name' =>
                    $connection?->calendar_summary
                    ?: 'Principal',
                'connected_at' =>
                    $this->relative(
                        $connection?->connected_at,
                    ),
                'last_sync' =>
                    $this->relative(
                        $connection?->last_sync_at,
                    ),
                'last_error' =>
                    $calendarHasCurrentError
                        ? $this->relative(
                            $connection?->last_error_at,
                        )
                        : null,
            ],
            'whatsapp' => [
                'inbound_enabled' =>
                    $inboundEnabled,
                'inbound_tone' =>
                    $inboundEnabled
                        ? 'success'
                        : 'danger',
                'inbound_label' =>
                    $inboundEnabled
                        ? 'Recepción activa'
                        : 'Recepción inactiva',
                'outbound_enabled' =>
                    $outboundEnabled,
                'outbound' =>
                    $outboundStatus,
                'access_token_configured' =>
                    $accessTokenConfigured,
                'phone_number_id_configured' =>
                    filled(
                        config(
                            'whatsapp.phone_number_id',
                        ),
                    ),
                'allowed_senders' =>
                    count(
                        config(
                            'whatsapp.allowed_wa_ids',
                            [],
                        ),
                    ),
                'latest_inbound_status' =>
                    $latestInbound?->status,
                'latest_inbound_at' =>
                    $this->relative(
                        $latestInbound?->received_at,
                    ),
                'latest_confirmation_status' =>
                    $latestInbound
                        ?->confirmation_status,
                'summary_enabled' =>
                    $summaryEnabled,
                'summary_tone' =>
                    $summaryEnabled
                        ? 'success'
                        : 'warning',
                'summary_label' =>
                    $summaryEnabled
                        ? 'Activo'
                        : 'Preparado en Central',
                'summary_template' =>
                    config(
                        'central.summary_whatsapp.template',
                        'central_executive_summary',
                    ),
                'summary_language' =>
                    config(
                        'central.summary_whatsapp.language',
                        'es_PE',
                    ),
                'latest_summary_status' =>
                    $latestSummary?->status,
                'latest_summary_at' =>
                    $this->relative(
                        $latestSummary?->updated_at,
                    ),
                'critical_enabled' =>
                    $criticalEnabled,
                'critical_tone' =>
                    $criticalEnabled
                        ? 'success'
                        : 'warning',
                'critical_label' =>
                    $criticalEnabled
                        ? 'Activas'
                        : 'Preparadas en Central',
                'critical_template' =>
                    config(
                        'central.critical_whatsapp.template',
                        'central_critical_alert',
                    ),
                'critical_language' =>
                    config(
                        'central.critical_whatsapp.language',
                        'es_PE',
                    ),
                'critical_cooldown_minutes' =>
                    (int) config(
                        'central.critical_whatsapp.cooldown_minutes',
                        360,
                    ),
                'active_critical_states' =>
                    $activeCritical,
                'latest_critical_at' =>
                    $this->relative(
                        $latestCritical?->updated_at,
                    ),
            ],
        ];
    }

    private function relative(
        mixed $value,
    ): ?string {
        if (! $value) {
            return null;
        }

        $date = $value instanceof \DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse(
                (string) $value,
                config(
                    'app.timezone',
                    'America/Lima',
                ),
            );

        return $date
            ->locale('es')
            ->diffForHumans();
    }
}
