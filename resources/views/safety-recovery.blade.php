<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estado y recuperación · Central ARPYNET</title>
    <x-operational-theme />
    <style>
        .safety-page{width:min(1200px,calc(100% - 28px));margin:0 auto;padding:22px 0 42px}
        .safety-header{display:flex;gap:18px;align-items:flex-start;justify-content:space-between;margin-bottom:18px}
        .safety-header h1{margin:0 0 6px;font-size:26px}.safety-header p,.safety-muted{color:var(--op-muted,#94a3b8);font-size:12px;line-height:1.55}
        .safety-status{display:inline-flex;align-items:center;gap:7px;margin-top:8px;border:1px solid var(--op-border,#334155);border-radius:999px;padding:5px 9px;font-size:11px;font-weight:850}
        .safety-status.healthy{border-color:#16a34a;color:#86efac}.safety-status.watch{border-color:#d97706;color:#fcd34d}.safety-status.attention{border-color:#dc2626;color:#fca5a5}
        .safety-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px}
        .safety-kpi,.safety-card{border:1px solid var(--op-border,#334155);border-radius:16px;background:var(--op-card,#0f172a)}
        .safety-kpi{padding:14px}.safety-kpi strong{display:block;margin-top:5px;font-size:22px}.safety-kpi span{font-size:11px;color:var(--op-muted,#94a3b8);font-weight:780}
        .safety-grid{display:grid;grid-template-columns:1fr;gap:14px}.safety-card{padding:16px}.safety-card h2{margin:0 0 11px;font-size:17px}.safety-card h3{margin:16px 0 8px;font-size:13px}
        .safety-row{display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:12px;padding:11px 0;border-top:1px solid var(--op-border,#334155);font-size:12px}.safety-row:first-of-type{border-top:0}.safety-row strong{display:block;margin-bottom:4px}.safety-row-meta{color:var(--op-muted,#94a3b8);line-height:1.45}.safety-right{text-align:right}
        .safety-pill{display:inline-flex;border:1px solid var(--op-border,#334155);border-radius:999px;padding:4px 8px;font-size:10px;font-weight:850}.safety-pill.failed{border-color:#dc2626;color:#fca5a5}.safety-pill.blocked,.safety-pill.stale{border-color:#d97706;color:#fcd34d}.safety-pill.pending{border-color:#2563eb;color:#bfdbfe}
        .safety-contract{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.safety-contract div{border:1px solid var(--op-border,#334155);border-radius:12px;padding:11px}.safety-contract strong{display:block;margin-bottom:4px;font-size:12px}.safety-contract span{font-size:11px;color:var(--op-muted,#94a3b8)}
        .safety-action{display:inline-flex;align-items:center;min-height:36px;border:1px solid var(--op-border,#334155);border-radius:10px;padding:7px 10px;color:inherit;text-decoration:none;font-size:12px;font-weight:800}.safety-action:hover{border-color:#3b82f6}
        .safety-empty{padding:8px 0;color:var(--op-muted,#94a3b8);font-size:12px}.safety-note{margin-top:12px;border:1px solid var(--op-border,#334155);border-radius:12px;padding:11px 12px;color:var(--op-muted,#94a3b8);font-size:11px;line-height:1.5}
        .safety-health-line{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}.safety-health-line .safety-status{margin-top:0}.safety-health-meta{font-size:11px;color:var(--op-muted,#94a3b8);text-align:right}
        @media(min-width:900px){.safety-grid{grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr)}}
        @media(max-width:720px){.safety-header{display:grid}.safety-row{grid-template-columns:1fr}.safety-right{text-align:left}.safety-contract{grid-template-columns:1fr}.safety-health-line{align-items:flex-start;flex-direction:column}.safety-health-meta{text-align:left}}
        @media(prefers-color-scheme:light){.safety-status.healthy{color:#15803d}.safety-status.watch{color:#a16207}.safety-status.attention{color:#b91c1c}.safety-pill.failed{color:#b91c1c}.safety-pill.blocked,.safety-pill.stale{color:#a16207}.safety-pill.pending{color:#1d4ed8}}
    </style>
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
        <div class="safety-kpi"><span>Fallidas recientes</span><strong>{{ $snapshot['counts']['failed_runs'] }}</strong></div>
        <div class="safety-kpi"><span>Bloqueadas / stale</span><strong>{{ $snapshot['counts']['blocked_runs'] + $snapshot['counts']['stale_runs'] }}</strong></div>
        <div class="safety-kpi"><span>Propuestas pendientes</span><strong>{{ $snapshot['counts']['pending_proposals'] }}</strong></div>
        <div class="safety-kpi"><span>Propuestas stale</span><strong>{{ $snapshot['counts']['stale_proposals'] }}</strong></div>
        <div class="safety-kpi"><span>Recurrencias activas</span><strong>{{ $snapshot['counts']['recurring_active'] }}</strong></div>
        <div class="safety-kpi"><span>Recurrencias sin generar</span><strong>{{ $snapshot['counts']['recurring_missing'] }}</strong></div>
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
                    <div><strong>Reglas recurrentes activas</strong><span>{{ $snapshot['recurring']['active_rules'] }}</span></div>
                    <div><strong>Reglas con generación pendiente</strong><span>{{ $snapshot['recurring']['missing_rules'] }}</span></div>
                    <div><strong>Última tarea recurrente generada</strong><span>{{ $snapshot['recurring']['last_generated_at']?->format('d/m/Y H:i') ?? 'Aún no registrada' }}</span></div>
                    <div><strong>Automatizaciones programadas</strong><span>{{ $snapshot['scheduler']['automation_enabled'] ? 'Habilitadas' : 'Deshabilitadas' }}</span></div>
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

                <a class="safety-action" href="{{ route('recurring-task-front.index') }}">Abrir Tareas recurrentes</a>
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
                <h2>Integraciones</h2>
                @if(!$snapshot['calendar']['connected'])
                    <div class="safety-empty">Google Calendar no está conectado.</div>
                @elseif($snapshot['calendar']['degraded'])
                    <strong>Google Calendar requiere revisión</strong>
                    <p class="safety-muted">Último error registrado: {{ $snapshot['calendar']['last_error_at']?->format('d/m/Y H:i') }}. El detalle técnico permanece oculto.</p>
                @else
                    <strong>Google Calendar sin error activo</strong>
                    <p class="safety-muted">Última sincronización: {{ $snapshot['calendar']['last_sync_at']?->format('d/m/Y H:i') ?? 'Aún no registrada' }}.</p>
                @endif
                <a class="safety-action" href="{{ route('operational-agenda.show') }}">Abrir Agenda</a>
            </section>
        </div>
    </div>

    <div class="safety-grid" style="margin-top:14px">
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

    <p class="safety-muted" style="margin-top:14px">Actualizado {{ $snapshot['generated_at']->format('d/m/Y H:i:s') }} · Los errores internos, credenciales y tokens no se renderizan en esta vista.</p>
</div>
<x-operational-interactions />
</body>
</html>
