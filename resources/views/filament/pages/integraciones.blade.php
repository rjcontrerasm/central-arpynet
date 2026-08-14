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
    </div>
</x-filament-panels::page>
