<x-filament-panels::page>
    <style>
        .integration-grid {
            display: grid;
            gap: 1rem;
        }

        .integration-card {
            display: grid;
            gap: .95rem;
            padding: 1.1rem;
            border: 1px solid rgb(226 232 240);
            border-radius: 1rem;
            background: rgba(255, 255, 255, .7);
        }

        .integration-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .integration-title {
            font-weight: 750;
            color: rgb(15 23 42);
        }

        .integration-description {
            margin-top: .25rem;
            color: rgb(100 116 139);
            font-size: .875rem;
            line-height: 1.45;
        }

        .integration-status {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            flex: 0 0 auto;
            padding: .38rem .62rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .integration-status::before {
            content: '';
            width: .48rem;
            height: .48rem;
            border-radius: 999px;
            background: currentColor;
        }

        .integration-status.success {
            background: rgb(240 253 244);
            color: rgb(22 101 52);
        }

        .integration-status.warning {
            background: rgb(255 251 235);
            color: rgb(161 98 7);
        }

        .integration-status.danger {
            background: rgb(254 242 242);
            color: rgb(185 28 28);
        }

        .integration-status.info {
            background: rgb(239 246 255);
            color: rgb(29 78 216);
        }

        .integration-status.neutral {
            background: rgb(248 250 252);
            color: rgb(71 85 105);
        }

        .integration-meta {
            display: grid;
            gap: .55rem;
            color: rgb(71 85 105);
            font-size: .875rem;
        }

        .integration-meta-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }

        .integration-meta-row span:first-child {
            color: rgb(100 116 139);
        }

        .integration-meta-row strong {
            text-align: right;
            color: rgb(30 41 59);
        }

        .integration-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
            align-items: center;
        }

        .integration-technical {
            margin-top: .25rem;
            padding-top: .85rem;
            border-top: 1px solid rgb(226 232 240);
        }

        .integration-technical summary {
            cursor: pointer;
            color: rgb(100 116 139);
            font-size: .8rem;
            font-weight: 700;
        }

        .integration-note {
            padding: .9rem 1rem;
            border-radius: .85rem;
            background: rgb(248 250 252);
            color: rgb(71 85 105);
            font-size: .83rem;
            line-height: 1.5;
        }

        @media (min-width: 850px) {
            .integration-grid.two {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .integration-grid.three {
                grid-template-columns:
                    repeat(3, minmax(0, 1fr));
            }
        }

        @media (prefers-color-scheme: dark) {
            .integration-card {
                border-color: rgb(55 65 81);
                background: rgba(17, 24, 39, .72);
            }

            .integration-title,
            .integration-meta-row strong {
                color: rgb(241 245 249);
            }

            .integration-description,
            .integration-meta,
            .integration-meta-row span:first-child,
            .integration-technical summary {
                color: rgb(148 163 184);
            }

            .integration-status.success {
                background: rgba(20, 83, 45, .32);
                color: rgb(134 239 172);
            }

            .integration-status.warning {
                background: rgba(120, 53, 15, .32);
                color: rgb(253 230 138);
            }

            .integration-status.danger {
                background: rgba(127, 29, 29, .32);
                color: rgb(254 202 202);
            }

            .integration-status.info {
                background: rgba(30, 58, 138, .35);
                color: rgb(191 219 254);
            }

            .integration-status.neutral,
            .integration-note {
                background: rgba(30, 41, 59, .7);
                color: rgb(203 213 225);
            }

            .integration-technical {
                border-color: rgb(55 65 81);
            }
        }
    </style>

    <div class="space-y-6">
        @if (session('google_calendar_success'))
            <div
                class="rounded-xl border border-success-200 bg-success-50 p-4 text-sm text-success-700 dark:border-success-800 dark:bg-success-950 dark:text-success-300"
                role="status"
            >
                {{ session('google_calendar_success') }}
            </div>
        @endif

        @if (session('google_calendar_error'))
            <div
                class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-300"
                role="alert"
            >
                {{ session('google_calendar_error') }}
            </div>
        @endif

        <div>
            <h2 class="text-lg font-semibold">
                Estado de integraciones
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Una vista simple del estado operativo.
                Los detalles técnicos quedan disponibles sin exponer secretos.
            </p>
        </div>

        <div class="integration-grid two">
            <section class="integration-card">
                <div class="integration-card-head">
                    <div>
                        <div class="integration-title">
                            Google Calendar
                        </div>

                        <div class="integration-description">
                            Sincronización de tareas, vencimientos y seguimientos.
                        </div>
                    </div>

                    <span
                        class="integration-status {{ $calendar['tone'] }}"
                    >
                        {{ $calendar['label'] }}
                    </span>
                </div>

                <div class="integration-note">
                    {{ $calendar['detail'] }}
                </div>

                <div class="integration-meta">
                    @if ($calendar['connected'])
                        <div class="integration-meta-row">
                            <span>Calendario</span>
                            <strong>
                                {{ $calendar['calendar_name'] }}
                            </strong>
                        </div>

                        <div class="integration-meta-row">
                            <span>Última sincronización</span>
                            <strong>
                                {{ $calendar['last_sync'] ?? 'Aún no ejecutada' }}
                            </strong>
                        </div>

                        @if ($calendar['last_error'])
                            <div class="integration-meta-row">
                                <span>Último error</span>
                                <strong>
                                    {{ $calendar['last_error'] }}
                                </strong>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="integration-actions">
                    @if (! $configured)
                        <span class="text-sm text-gray-500">
                            Configuración pendiente.
                        </span>
                    @elseif ($calendar['connected'])
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
                                color="gray"
                                outlined
                            >
                                Desconectar
                            </x-filament::button>
                        </form>
                    @else
                        <x-filament::button
                            tag="a"
                            href="{{ route('google-calendar.connect') }}"
                        >
                            Conectar Google Calendar
                        </x-filament::button>
                    @endif
                </div>

                <details class="integration-technical">
                    <summary>Detalles técnicos</summary>

                    <div class="integration-meta mt-3">
                        <div class="integration-meta-row">
                            <span>OAuth</span>
                            <strong>
                                {{ $configured ? 'configurado' : 'pendiente' }}
                            </strong>
                        </div>

                        <div class="integration-meta-row">
                            <span>Conectado</span>
                            <strong>
                                {{ $calendar['connected'] ? 'sí' : 'no' }}
                            </strong>
                        </div>
                    </div>
                </details>
            </section>

            <section class="integration-card">
                <div class="integration-card-head">
                    <div>
                        <div class="integration-title">
                            WhatsApp
                        </div>

                        <div class="integration-description">
                            Captura móvil y confirmaciones de Central.
                        </div>
                    </div>

                    <span
                        class="integration-status {{ $whatsapp['inbound_tone'] }}"
                    >
                        {{ $whatsapp['inbound_label'] }}
                    </span>
                </div>

                <div class="integration-meta">
                    <div class="integration-meta-row">
                        <span>Último mensaje recibido</span>
                        <strong>
                            {{ $whatsapp['latest_inbound_at'] ?? 'Sin registros' }}
                        </strong>
                    </div>

                    <div class="integration-meta-row">
                        <span>Procesamiento</span>
                        <strong>
                            {{ $whatsapp['latest_inbound_status'] ?? 'Sin registros' }}
                        </strong>
                    </div>

                    <div class="integration-meta-row">
                        <span>Confirmación</span>
                        <strong>
                            {{ $whatsapp['latest_confirmation_status'] ?? 'Sin registros' }}
                        </strong>
                    </div>
                </div>

                <details class="integration-technical">
                    <summary>Detalles técnicos</summary>

                    <div class="integration-meta mt-3">
                        <div class="integration-meta-row">
                            <span>Phone Number ID</span>
                            <strong>
                                {{ $whatsapp['phone_number_id_configured'] ? 'configurado' : 'pendiente' }}
                            </strong>
                        </div>

                        <div class="integration-meta-row">
                            <span>Remitentes autorizados</span>
                            <strong>
                                {{ $whatsapp['allowed_senders'] }}
                            </strong>
                        </div>

                        <div class="integration-meta-row">
                            <span>Credencial de envío</span>
                            <strong>
                                {{ $whatsapp['access_token_configured'] ? 'configurada' : 'pendiente' }}
                            </strong>
                        </div>
                    </div>
                </details>
            </section>
        </div>

        <div class="integration-grid three">
            <section class="integration-card">
                <div class="integration-card-head">
                    <div class="integration-title">
                        Envío WhatsApp
                    </div>

                    <span
                        class="integration-status {{ $whatsapp['outbound']['tone'] }}"
                    >
                        {{ $whatsapp['outbound']['label'] }}
                    </span>
                </div>

                <div class="integration-note">
                    {{ $whatsapp['outbound']['detail'] }}
                </div>
            </section>

            <section class="integration-card">
                <div class="integration-card-head">
                    <div>
                        <div class="integration-title">
                            Resumen ejecutivo
                        </div>

                        <div class="integration-description">
                            Envío programado por WhatsApp.
                        </div>
                    </div>

                    <span
                        class="integration-status {{ $whatsapp['summary_tone'] }}"
                    >
                        {{ $whatsapp['summary_label'] }}
                    </span>
                </div>

                <div class="integration-meta">
                    <div class="integration-meta-row">
                        <span>Plantilla</span>
                        <strong>
                            {{ $whatsapp['summary_template'] }}
                        </strong>
                    </div>

                    <div class="integration-meta-row">
                        <span>Idioma</span>
                        <strong>
                            {{ $whatsapp['summary_language'] }}
                        </strong>
                    </div>

                    <div class="integration-meta-row">
                        <span>Último envío</span>
                        <strong>
                            {{ $whatsapp['latest_summary_at'] ?? 'Sin envíos' }}
                        </strong>
                    </div>
                </div>
            </section>

            <section class="integration-card">
                <div class="integration-card-head">
                    <div>
                        <div class="integration-title">
                            Alertas críticas
                        </div>

                        <div class="integration-description">
                            Avisos deduplicados de atención inmediata.
                        </div>
                    </div>

                    <span
                        class="integration-status {{ $whatsapp['critical_tone'] }}"
                    >
                        {{ $whatsapp['critical_label'] }}
                    </span>
                </div>

                <div class="integration-meta">
                    <div class="integration-meta-row">
                        <span>Cooldown</span>
                        <strong>
                            {{ intdiv($whatsapp['critical_cooldown_minutes'], 60) }} h
                        </strong>
                    </div>

                    <div class="integration-meta-row">
                        <span>Estados críticos activos</span>
                        <strong>
                            {{ $whatsapp['active_critical_states'] }}
                        </strong>
                    </div>

                    <div class="integration-meta-row">
                        <span>Última actividad</span>
                        <strong>
                            {{ $whatsapp['latest_critical_at'] ?? 'Sin registros' }}
                        </strong>
                    </div>
                </div>

                <details class="integration-technical">
                    <summary>Plantilla</summary>

                    <div class="integration-meta mt-3">
                        <div class="integration-meta-row">
                            <span>Nombre</span>
                            <strong>
                                {{ $whatsapp['critical_template'] }}
                            </strong>
                        </div>

                        <div class="integration-meta-row">
                            <span>Idioma</span>
                            <strong>
                                {{ $whatsapp['critical_language'] }}
                            </strong>
                        </div>
                    </div>
                </details>
            </section>
        </div>

        @if (! $whatsapp['outbound_enabled'])
            <div class="integration-note">
                WhatsApp saliente permanece apagado de forma segura.
                La recepción continúa operativa mientras Meta libera
                la creación del System User permanente.
            </div>
        @endif
    </div>
</x-filament-panels::page>
