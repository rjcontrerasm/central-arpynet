<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estado y recuperación · Central ARPYNET</title>
    <x-operational-theme />
    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/safety-recovery.css') }}?v=2.39.2"
    >
</head>
<body>
<div class="safety-page">
    <header class="safety-header">
        <div>
            <h1>Estado y recuperación</h1>
            <p>Señales operativas de seguridad, automatización y recuperación. Esta vista no expone secretos ni ejecuta reparaciones automáticas.</p>
            <span class="safety-status {{ $snapshot['status'] }}">{{ $snapshot['status_label'] }}</span>
        </div>
        <x-operational-nav active="safety" />
    </header>

    <section class="safety-kpis">
        <div class="safety-kpi"><span>Automatizaciones activas</span><strong>{{ $snapshot['counts']['automation_active'] }}</strong></div>
        <div class="safety-kpi"><span>Fallas automatización 24 h</span><strong>{{ $snapshot['counts']['automation_failed_24h'] }}</strong></div>
        <div class="safety-kpi"><span>Recurrencias sin generar</span><strong>{{ $snapshot['counts']['recurring_missing'] }}</strong></div>
        <div class="safety-kpi"><span>Vencimientos sin generar</span><strong>{{ $snapshot['counts']['obligation_missing'] }}</strong></div>
        <div class="safety-kpi"><span>Fallas WhatsApp 24 h</span><strong>{{ $snapshot['counts']['whatsapp_failed_24h'] }}</strong></div>
        <div class="safety-kpi"><span>Propuestas pendientes / stale</span><strong>{{ $snapshot['counts']['pending_proposals'] + $snapshot['counts']['stale_proposals'] }}</strong></div>
    </section>

    <div class="safety-grid">
        <section class="safety-card">
            <h2>Señales de recuperación</h2>
            @forelse($snapshot['run_issues'] as $run)
                <article class="safety-row">
                    <div>
                        <strong>{{ $run['title'] }}</strong>
                        <div class="safety-row-meta">{{ $run['organization'] }} · {{ $run['rule'] }}</div>
                        @if($run['has_internal_error'])
                            <div class="safety-row-meta">CENTRAL registró un detalle técnico interno. No se muestra en FRONT.</div>
                        @endif
                    </div>
                    <div class="safety-right">
                        <span class="safety-pill {{ $run['outcome'] }}">{{ $run['outcome_label'] }}</span>
                        <div class="safety-row-meta">{{ $run['evaluated_at']?->format('d/m/Y H:i') ?? '—' }}</div>
                    </div>
                </article>
            @empty
                <div class="safety-empty">No hay automatizaciones fallidas, bloqueadas o desactualizadas entre las ejecuciones recientes visibles.</div>
            @endforelse

            <div class="safety-note">Una falla visible aquí no se reintenta a ciegas. La recuperación debe volver a validar autorización, versión y condición actual antes de cualquier escritura.</div>
        </section>

        <div class="safety-grid">
            <section class="safety-card">
                <h2>Scheduler y recurrencias</h2>
                <div class="safety-health-line">
                    <span class="safety-status {{ $snapshot['scheduler']['status'] }}">{{ $snapshot['scheduler']['status_label'] }}</span>
                    <div class="safety-health-meta">
                        @if($snapshot['scheduler']['last_seen_at'])
                            Última señal {{ $snapshot['scheduler']['last_seen_at']->format('d/m/Y H:i:s') }}
                            · {{ $snapshot['scheduler']['age_minutes'] }} min
                        @else
                            Aún no existe una señal del scheduler.
                        @endif
                    </div>
                </div>

                <div class="safety-contract">
                    <div><strong>Tareas recurrentes activas</strong><span>{{ $snapshot['recurring']['active_rules'] }}</span></div>
                    <div><strong>Tareas sin generar</strong><span>{{ $snapshot['recurring']['missing_rules'] }}</span></div>
                    <div><strong>Vencimientos recurrentes activos</strong><span>{{ $snapshot['obligations']['active_rules'] }}</span></div>
                    <div><strong>Vencimientos sin generar</strong><span>{{ $snapshot['obligations']['missing_rules'] }}</span></div>
                    <div><strong>Última tarea recurrente</strong><span>{{ $snapshot['recurring']['last_generated_at']?->format('d/m/Y H:i') ?? 'Aún no registrada' }}</span></div>
                    <div><strong>Último vencimiento generado</strong><span>{{ $snapshot['obligations']['last_generated_at']?->format('d/m/Y H:i') ?? 'Aún no registrado' }}</span></div>
                </div>

                @if($snapshot['recurring']['issues']->isNotEmpty())
                    <h3>Recurrencias que requieren revisión</h3>
                    @foreach($snapshot['recurring']['issues'] as $issue)
                        <article class="safety-row">
                            <div>
                                <strong>{{ $issue['title'] }}</strong>
                                <div class="safety-row-meta">{{ $issue['organization'] }}</div>
                            </div>
                            <div class="safety-right">
                                <span class="safety-pill stale">Sin generar</span>
                                <div class="safety-row-meta">{{ $issue['scheduled_for']->format('d/m/Y') }}</div>
                            </div>
                        </article>
                    @endforeach
                @else
                    <div class="safety-empty">Las recurrencias activas visibles tienen sus ocurrencias esperadas generadas.</div>
                @endif

                @if($snapshot['obligations']['issues']->isNotEmpty())
                    <h3>Vencimientos recurrentes que requieren revisión</h3>
                    @foreach($snapshot['obligations']['issues'] as $issue)
                        <article class="safety-row">
                            <div>
                                <strong>{{ $issue['title'] }}</strong>
                                <div class="safety-row-meta">{{ $issue['organization'] }}</div>
                            </div>
                            <div class="safety-right">
                                <span class="safety-pill stale">Sin generar</span>
                                <div class="safety-row-meta">{{ $issue['due_date']->format('d/m/Y') }}</div>
                            </div>
                        </article>
                    @endforeach
                @else
                    <div class="safety-empty">Los vencimientos recurrentes visibles tienen su siguiente ocurrencia generada.</div>
                @endif

                <div class="safety-actions">
                    <a class="safety-action" href="{{ route('recurring-task-front.index') }}">Tareas recurrentes</a>
                    <a class="safety-action" href="{{ route('recurring-obligation-front.index') }}">Vencimientos recurrentes</a>
                </div>
            </section>

            <section class="safety-card">
                <h2>Base de datos</h2>
                <div class="safety-health-line">
                    <span class="safety-status {{ in_array($snapshot['database']['status'], ['healthy', 'watch', 'attention'], true) ? $snapshot['database']['status'] : 'watch' }}">{{ $snapshot['database']['status_label'] }}</span>
                    <div class="safety-health-meta">
                        @if($snapshot['database']['version'])
                            {{ $snapshot['database']['version'] }}
                        @else
                            Driver {{ $snapshot['database']['driver'] }}
                        @endif
                    </div>
                </div>

                @if($snapshot['database']['available'])
                    <div class="safety-contract">
                        <div><strong>Conexiones actuales</strong><span>{{ $snapshot['database']['threads_connected'] }} / {{ $snapshot['database']['max_connections'] }} · {{ number_format($snapshot['database']['current_percent'], 1) }}%</span></div>
                        <div><strong>Conexiones ejecutando</strong><span>{{ $snapshot['database']['threads_running'] }}</span></div>
                        <div><strong>Máximo observado</strong><span>{{ $snapshot['database']['max_used_connections'] }} / {{ $snapshot['database']['max_connections'] }} · {{ number_format($snapshot['database']['peak_percent'], 1) }}%</span></div>
                        <div><strong>Ámbito de la métrica</strong><span>Servidor MariaDB/MySQL completo</span></div>
                    </div>

                    @if($snapshot['database']['historical_near_limit'])
                        <div class="safety-note">El servidor alcanzó al menos 90% de su límite de conexiones desde el último arranque. Esto confirma presión histórica, pero no atribuye por sí solo el origen a CENTRAL.</div>
                    @else
                        <div class="safety-note">Estas métricas son globales del servidor y sirven para detectar presión de conexiones. No exponen credenciales ni identifican por sí solas qué aplicación consumió las conexiones.</div>
                    @endif
                @else
                    <div class="safety-empty">CENTRAL no pudo obtener métricas avanzadas de conexiones. La vista continúa operativa sin mostrar detalles internos del error.</div>
                @endif
            </section>

            <section class="safety-card">
                <h2>Recuperación disponible</h2>
                @if($snapshot['undo'])
                    <strong>{{ $snapshot['undo']['label'] }}</strong>
                    <p class="safety-muted">Undo seguro disponible hasta {{ $snapshot['undo']['expires_at']?->format('d/m/Y H:i:s') }}. El botón global de deshacer sigue aplicando las verificaciones de fingerprint y autorización.</p>
                @else
                    <div class="safety-empty">No hay una acción vigente para deshacer.</div>
                @endif
                <a class="safety-action" href="{{ route('audit-history.index') }}">Ver historial</a>
            </section>

            <section class="safety-card">
                <h2>Canales e integraciones</h2>

                <div class="safety-health-line">
                    <span class="safety-status {{ $snapshot['calendar']['status'] }}">{{ $snapshot['calendar']['status_label'] }}</span>
                    <div class="safety-health-meta">
                        @if($snapshot['calendar']['last_sync_at'])
                            Última sincronización {{ $snapshot['calendar']['last_sync_at']->format('d/m/Y H:i') }}
                        @else
                            Sin sincronización registrada
                        @endif
                    </div>
                </div>

                <div class="safety-health-line">
                    <span class="safety-status {{ $snapshot['whatsapp']['status'] }}">{{ $snapshot['whatsapp']['status_label'] }}</span>
                    <div class="safety-health-meta">
                        {{ $snapshot['whatsapp']['sent_24h'] }} enviados ·
                        {{ $snapshot['whatsapp']['failed_24h'] }} fallidos en 24 h
                    </div>
                </div>

                @if($snapshot['external_monitor']['visible'])
                    <div class="safety-health-line">
                        <span class="safety-status {{ $snapshot['external_monitor']['status'] }}">{{ $snapshot['external_monitor']['status_label'] }}</span>
                        <div class="safety-health-meta">
                            @if($snapshot['external_monitor']['last_success_at'])
                                Último éxito {{ $snapshot['external_monitor']['last_success_at']->format('d/m/Y H:i') }}
                                · {{ $snapshot['external_monitor']['last_item_count'] ?? 0 }} elementos
                            @else
                                Sin éxito registrado
                            @endif
                        </div>
                    </div>
                @endif

                <a class="safety-action" href="{{ route('operational-agenda.show') }}">Abrir Agenda</a>
            </section>
        </div>
    </div>

    <div class="safety-grid safety-grid-spaced">
        <section class="safety-card">
            <h2>Automatizaciones</h2>
            <div class="safety-health-line">
                <span class="safety-status {{ $snapshot['automations']['status'] }}">{{ $snapshot['automations']['status_label'] }}</span>
                <div class="safety-health-meta">
                    Última evaluación {{ $snapshot['automations']['last_evaluated_at']?->format('d/m/Y H:i') ?? 'no registrada' }}
                </div>
            </div>
            <div class="safety-contract">
                <div><strong>Reglas activas</strong><span>{{ $snapshot['automations']['active_rules'] }}</span></div>
                <div><strong>Ejecuciones 24 h</strong><span>{{ $snapshot['automations']['runs_24h'] }}</span></div>
                <div><strong>Fallidas 24 h</strong><span>{{ $snapshot['automations']['failed_24h'] }}</span></div>
                <div><strong>Bloqueadas / stale 24 h</strong><span>{{ $snapshot['automations']['blocked_24h'] + $snapshot['automations']['stale_24h'] }}</span></div>
            </div>
            <a class="safety-action" href="{{ route('automation-center.index') }}">Abrir Automatizaciones</a>
        </section>

        <section class="safety-card">
            <h2>Entregas de resumen</h2>
            <div class="safety-health-line">
                <span class="safety-status {{ $snapshot['summaries']['status'] }}">{{ $snapshot['summaries']['status_label'] }}</span>
            </div>
            <div class="safety-contract">
                <div>
                    <strong>Email</strong>
                    <span>
                        {{ $snapshot['summaries']['email_enabled'] ? 'Habilitado' : 'Deshabilitado' }}
                        · {{ $snapshot['summaries']['email']['status'] ?? 'sin entrega' }}
                        @if($snapshot['summaries']['email']['updated_at'] ?? null)
                            · {{ $snapshot['summaries']['email']['updated_at']->format('d/m H:i') }}
                        @endif
                    </span>
                </div>
                <div>
                    <strong>WhatsApp</strong>
                    <span>
                        {{ $snapshot['summaries']['whatsapp_enabled'] ? 'Habilitado' : 'Deshabilitado' }}
                        · {{ $snapshot['summaries']['whatsapp']['status'] ?? 'sin entrega' }}
                        @if($snapshot['summaries']['whatsapp']['updated_at'] ?? null)
                            · {{ $snapshot['summaries']['whatsapp']['updated_at']->format('d/m H:i') }}
                        @endif
                    </span>
                </div>
            </div>
            <a class="safety-action" href="{{ route('executive-summary.show') }}">Abrir Resumen</a>
        </section>
    </div>

    <div class="safety-grid safety-grid-spaced">
        <section class="safety-card">
            <h2>Propuestas que requieren contexto humano</h2>
            @forelse($snapshot['proposal_issues'] as $proposal)
                <article class="safety-row">
                    <div>
                        <strong>{{ $proposal['title'] }}</strong>
                        <div class="safety-row-meta">{{ $proposal['organization'] }} · {{ $proposal['action'] }}</div>
                    </div>
                    <div class="safety-right">
                        <span class="safety-pill {{ $proposal['status'] }}">{{ $proposal['status_label'] }}</span>
                        <div class="safety-row-meta">{{ $proposal['created_at']?->format('d/m/Y H:i') }}</div>
                    </div>
                </article>
            @empty
                <div class="safety-empty">No hay propuestas pendientes o stale visibles.</div>
            @endforelse
            <a class="safety-action" href="{{ route('agent-proposals.index') }}">Abrir Jarvis</a>
        </section>

        <section class="safety-card">
            <h2>Frontera de autonomía</h2>
            <div class="safety-contract">
                <div><strong>L1</strong><span>{{ $snapshot['autonomy']['level_one'] ? 'Activa · prepara propuesta' : 'Desactivada' }}</span></div>
                <div><strong>L2</strong><span>{{ $snapshot['autonomy']['level_two'] ? 'Activa · máx. '.$snapshot['autonomy']['level_two_daily_limit'].'/día por organización' : 'Desactivada' }}</span></div>
                <div><strong>L3</strong><span>{{ $snapshot['autonomy']['level_three'] ? 'Activa · máx. '.$snapshot['autonomy']['level_three_daily_limit'].'/día por organización' : 'Desactivada' }}</span></div>
                <div><strong>Canales externos autónomos</strong><span>{{ $snapshot['autonomy']['external_channels'] || $snapshot['autonomy']['network_calls'] ? 'Revisar contrato' : 'Bloqueados' }}</span></div>
                <div><strong>Deletes autónomos</strong><span>{{ $snapshot['autonomy']['delete_actions'] ? 'Revisar contrato' : 'Bloqueados' }}</span></div>
                <div><strong>Bulk autónomo</strong><span>{{ $snapshot['autonomy']['bulk_execution'] ? 'Revisar contrato' : 'Bloqueado' }}</span></div>
            </div>
            <p class="safety-muted">Contrato {{ $snapshot['autonomy']['contract'] }}. Scheduler de automatizaciones: {{ $snapshot['scheduler']['automation_enabled'] ? 'habilitado con guardia de solapamiento esperada' : 'deshabilitado' }}.</p>
            <a class="safety-action" href="{{ route('automation-center.index') }}">Abrir Automatizaciones</a>
        </section>
    </div>

    <p class="safety-muted safety-muted-spaced">Actualizado {{ $snapshot['generated_at']->format('d/m/Y H:i:s') }} · Los errores internos, credenciales y tokens no se renderizan en esta vista.</p>
</div>
<x-operational-interactions />
</body>
</html>
