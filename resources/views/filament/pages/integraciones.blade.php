<x-filament-panels::page>
    <div class="space-y-6">
        @if (session('google_calendar_success'))
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 text-sm text-success-700 dark:border-success-800 dark:bg-success-950 dark:text-success-300">
                {{ session('google_calendar_success') }}
            </div>
        @endif

        @if (session('google_calendar_error'))
            <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-300">
                {{ session('google_calendar_error') }}
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                Google Calendar
            </x-slot>

            <x-slot name="description">
                Conecta el calendario principal para sincronizar
                tareas, vencimientos y seguimientos de Central.
            </x-slot>

            <div class="space-y-4">
                @if (! $configured)
                    <div>
                        <strong>Estado:</strong>
                        pendiente de configurar credenciales OAuth.
                    </div>

                    <div class="text-sm text-gray-500">
                        Redirect URI:
                        <code>
                            https://central.arpynet.com/google-calendar/callback
                        </code>
                    </div>
                @elseif ($connection?->isConnected())
                    <div>
                        <strong>Estado:</strong>
                        conectado.
                    </div>

                    <div>
                        <strong>Calendario:</strong>
                        {{ $connection->calendar_summary ?: 'Principal' }}
                    </div>

                    <div>
                        <strong>Conectado:</strong>
                        {{ $connection->connected_at?->format('d/m/Y H:i') }}
                    </div>

                    <div>
                        <strong>Última sincronización:</strong>
                        {{ $connection->last_sync_at?->format('d/m/Y H:i') ?? 'Aún no ejecutada' }}
                    </div>

                    <form
                        method="POST"
                        action="{{ route('google-calendar.sync') }}"
                    >
                        @csrf

                        <x-filament::button
                            type="submit"
                        >
                            Sincronizar ahora
                        </x-filament::button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('google-calendar.disconnect') }}"
                    >
                        @csrf

                        <x-filament::button
                            type="submit"
                            color="danger"
                            outlined
                        >
                            Desconectar Google Calendar
                        </x-filament::button>
                    </form>
                @else
                    <div>
                        <strong>Estado:</strong>
                        listo para conectar.
                    </div>

                    <x-filament::button
                        tag="a"
                        href="{{ route('google-calendar.connect') }}"
                    >
                        Conectar Google Calendar
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">WhatsApp Cloud API</x-slot>
            <x-slot name="description">Estado operativo del canal WhatsApp de Central. Nunca se muestran secretos ni números completos.</x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <div><strong>Recepción:</strong> {{ $whatsapp['inbound_enabled'] ? 'activa' : 'inactiva' }}</div>
                    <div><strong>Envío:</strong> {{ $whatsapp['outbound_enabled'] ? 'activo' : 'inactivo' }}</div>
                    <div><strong>Token de envío:</strong> {{ $whatsapp['access_token_configured'] ? 'configurado' : 'no configurado' }}</div>
                    <div><strong>Phone Number ID:</strong> {{ $whatsapp['phone_number_id_configured'] ? 'configurado' : 'no configurado' }}</div>
                    <div><strong>Remitentes autorizados:</strong> {{ $whatsapp['allowed_senders'] }}</div>
                </div>
                <div class="space-y-2">
                    <div><strong>Último inbound:</strong> {{ $whatsapp['latest_inbound_status'] ?? 'sin registros' }}</div>
                    <div><strong>Última confirmación:</strong> {{ $whatsapp['latest_confirmation_status'] ?? 'sin registros' }}</div>
                    <div><strong>Resumen:</strong> {{ $whatsapp['summary_enabled'] ? 'activo' : 'inactivo' }}</div>
                    <div><strong>Plantilla resumen:</strong> {{ $whatsapp['summary_template'] }} ({{ $whatsapp['summary_language'] }})</div>
                    <div><strong>Último resumen:</strong> {{ $whatsapp['latest_summary_status'] ?? 'sin registros' }}</div>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="font-semibold">Alertas críticas</div>
                <div class="mt-2 grid gap-2 md:grid-cols-2">
                    <div><strong>Estado:</strong> {{ $whatsapp['critical_enabled'] ? 'activo' : 'inactivo' }}</div>
                    <div><strong>Plantilla:</strong> {{ $whatsapp['critical_template'] }} ({{ $whatsapp['critical_language'] }})</div>
                    <div><strong>Cooldown:</strong> {{ $whatsapp['critical_cooldown_minutes'] }} minutos</div>
                    <div><strong>Estados críticos activos:</strong> {{ $whatsapp['active_critical_states'] }}</div>
                </div>
            </div>

            <div class="mt-4 text-sm text-gray-500">Las funciones salientes permanecen deshabilitadas mientras no exista un System User Access Token permanente.</div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
