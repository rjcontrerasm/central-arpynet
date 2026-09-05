<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Automatizaciones · Central ARPYNET</title>
    <x-operational-theme />
    <style>
        .automation-page{width:min(1200px,calc(100% - 28px));margin:0 auto;padding:22px 0 40px}
        .automation-header{display:flex;gap:18px;align-items:flex-start;justify-content:space-between;margin-bottom:18px}
        .automation-header h1{margin:0 0 6px;font-size:26px}
        .automation-header p,.automation-meta,.automation-safety,.automation-empty{color:var(--op-muted,#94a3b8);font-size:12px}
        .automation-grid{display:grid;grid-template-columns:1fr;gap:14px}
        .automation-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}
        .automation-card,.automation-kpi{border:1px solid var(--op-border,#334155);border-radius:16px;background:var(--op-card,#0f172a)}
        .automation-kpi{padding:14px}.automation-kpi strong{display:block;margin-top:5px;font-size:22px}
        .automation-card{padding:16px}.automation-card h2{margin:0 0 12px;font-size:17px}
        .automation-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .automation-field{display:grid;gap:6px}.automation-field label{color:var(--op-muted,#94a3b8);font-size:12px;font-weight:800}
        .automation-field input,.automation-field select{width:100%;min-height:42px;border:1px solid var(--op-border,#334155);border-radius:10px;padding:8px 10px;background:transparent;color:inherit}
        .automation-wide{grid-column:1/-1}
        .automation-button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;border:1px solid #3b82f6;border-radius:10px;padding:7px 11px;background:#172554;color:#dbeafe;font:inherit;font-size:12px;font-weight:850;cursor:pointer;text-decoration:none}
        .automation-button.secondary{border-color:var(--op-border,#334155);background:transparent;color:inherit}
        .automation-rule{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,.9fr) minmax(120px,.4fr) auto;gap:12px;align-items:center;padding:13px 0;border-top:1px solid var(--op-border,#334155)}
        .automation-rule:first-of-type{border-top:0}.automation-rule strong{display:block;margin-bottom:4px}
        .automation-actions{display:flex;flex-wrap:wrap;gap:7px;justify-content:flex-end}
        .automation-pill{display:inline-flex;align-items:center;border:1px solid var(--op-border,#334155);border-radius:999px;padding:4px 8px;font-size:11px;font-weight:850}
        .automation-pill.active{border-color:#16a34a;color:#86efac}.automation-pill.inactive{color:var(--op-muted,#94a3b8)}
        .automation-flash{margin-bottom:14px;border:1px solid #2563eb;border-radius:12px;padding:12px 14px;background:rgba(30,64,175,.18);font-size:13px}
        .automation-run-row{display:grid;grid-template-columns:1fr 180px 140px;gap:10px;padding:10px 0;border-top:1px solid var(--op-border,#334155);font-size:12px}
        .automation-safety{margin-top:10px;line-height:1.5}
        @media(min-width:980px){.automation-grid{grid-template-columns:minmax(320px,.8fr) minmax(0,1.45fr);align-items:start}}
        @media(max-width:760px){.automation-header{display:grid}.automation-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.automation-form-grid,.automation-rule,.automation-run-row{grid-template-columns:1fr}.automation-wide{grid-column:auto}.automation-actions{justify-content:flex-start}}
    </style>
</head>
<body>
<div class="automation-page">
    <header class="automation-header">
        <div>
            <h1>Automatizaciones</h1>
            <p>Reglas internas, controladas y auditables. El scheduler y los canales externos siguen deshabilitados.</p>
        </div>
        <x-operational-nav active="automations" />
    </header>

    @if(session('automation_success'))
        <div class="automation-flash">{{ session('automation_success') }}</div>
    @endif
    @if($errors->any())
        <div class="automation-flash">{{ $errors->first() }}</div>
    @endif

    @if(session('automation_preview'))
        @php($preview=session('automation_preview'))
        <div class="automation-flash">
            <strong>Vista previa · {{ $preview['rule_name'] }}</strong>
            <div>Coincidencias: {{ $preview['matches'] }}</div>
            @foreach($preview['examples'] as $example)
                <div>{{ $example['title'] }} · {{ $example['reason'] }}</div>
            @endforeach
        </div>
    @endif

    @if(session('automation_run'))
        @php($run=session('automation_run'))
        <div class="automation-flash">
            <strong>Evaluación completada · {{ $run['name'] }}</strong>
            <div>
                Ejecutadas: {{ $run['executed'] }}
                · Confirmación: {{ $run['pending_confirmation'] }}
                · Preview: {{ $run['previewed'] }}
                · Duplicadas: {{ $run['duplicates'] }}
                · Fallidas: {{ $run['failed'] }}
            </div>
        </div>
    @endif

    <section class="automation-kpis">
        <div class="automation-kpi"><span class="automation-meta">Reglas</span><strong>{{ $counts['total'] }}</strong></div>
        <div class="automation-kpi"><span class="automation-meta">Activas</span><strong>{{ $counts['active'] }}</strong></div>
        <div class="automation-kpi"><span class="automation-meta">Automáticas</span><strong>{{ $counts['automatic'] }}</strong></div>
        <div class="automation-kpi"><span class="automation-meta">Por confirmar</span><strong>{{ $counts['pending_confirmation'] }}</strong></div>
    </section>

    <div class="automation-grid">
        <section class="automation-card">
            <h2>Nueva regla</h2>
            <form method="POST" action="{{ route('automation-center.store') }}">
                @csrf
                <div class="automation-form-grid">
                    <div class="automation-field automation-wide">
                        <label for="name">Nombre</label>
                        <input id="name" name="name" value="{{ old('name') }}" placeholder="Ej. Cobranza vencida ARPYNET" required>
                    </div>
                    <div class="automation-field automation-wide">
                        <label for="organization_id">Organización</label>
                        <select id="organization_id" name="organization_id" required>
                            @foreach($organizations as $organization)
                                <option value="{{ $organization->id }}" @selected((int)old('organization_id',auth()->user()->current_organization_id)===$organization->id)>
                                    {{ $organization->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="automation-field automation-wide">
                        <label for="trigger_key">Cuando ocurra</label>
                        <select id="trigger_key" name="trigger_key" required>
                            @foreach($triggers as $key=>$trigger)
                                <option value="{{ $key }}" @selected(old('trigger_key')===$key)>{{ $trigger['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="automation-field automation-wide">
                        <label for="action_key">Entonces</label>
                        <select id="action_key" name="action_key" required>
                            @foreach($actions as $key=>$action)
                                <option value="{{ $key }}" @selected(old('action_key')===$key)>{{ $action['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="automation-field">
                        <label for="mode">Modo</label>
                        <select id="mode" name="mode" required>
                            <option value="preview">Vista previa</option>
                            <option value="confirmation">Requiere confirmación</option>
                            <option value="automatic">Automático seguro</option>
                        </select>
                    </div>
                    <div class="automation-field">
                        <label for="days">Días de anticipación</label>
                        <input id="days" name="days" type="number" min="0" max="30" value="{{ old('days',7) }}">
                    </div>
                    <div class="automation-wide">
                        <button class="automation-button" type="submit">Crear regla</button>
                    </div>
                </div>
            </form>
            <div class="automation-safety">
                Las reglas nuevas nacen inactivas. El modo automático solo está permitido para notificaciones internas de facturación, cobranza y vencimientos.
            </div>
        </section>

        <section class="automation-card">
            <h2>Reglas configuradas</h2>
            @forelse($rules as $rule)
                <article class="automation-rule">
                    <div>
                        <strong>{{ $rule->name }}</strong>
                        <div class="automation-meta">
                            {{ $rule->organization?->name }}
                            · {{ $triggers[$rule->trigger_key]['label'] ?? $rule->trigger_key }}
                            → {{ $actions[$rule->action_key]['label'] ?? $rule->action_key }}
                        </div>
                    </div>
                    <div class="automation-meta">
                        Modo: {{ $rule->mode }}<br>
                        Coincidencias: {{ $rule->preview_matches ?? '—' }}<br>
                        Última evaluación: {{ $rule->last_evaluated_at?->format('d/m/Y H:i') ?? 'Nunca' }}
                    </div>
                    <div>
                        <span class="automation-pill {{ $rule->is_active ? 'active' : 'inactive' }}">
                            {{ $rule->is_active ? 'Activa' : 'Inactiva' }}
                        </span>
                    </div>
                    <div class="automation-actions">
                        <form method="POST" action="{{ route('automation-center.preview',$rule) }}">
                            @csrf
                            <button class="automation-button secondary" type="submit">Vista previa</button>
                        </form>
                        @if($rule->is_active)
                            <form method="POST" action="{{ route('automation-center.run',$rule) }}">
                                @csrf
                                <button class="automation-button" type="submit" data-busy-label="Evaluando…">Evaluar ahora</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('automation-center.toggle',$rule) }}">
                            @csrf
                            <button class="automation-button secondary" type="submit">
                                {{ $rule->is_active ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="automation-empty">Todavía no hay reglas configuradas.</div>
            @endforelse
        </section>
    </div>

    <section class="automation-card" style="margin-top:14px">
        <h2>Confirmaciones pendientes</h2>
        @forelse($pendingConfirmations as $run)
            <div class="automation-run-row">
                <div>
                    <strong>{{ $run->rule?->name ?? 'Regla' }}</strong>
                    <div class="automation-meta">
                        {{ data_get($run->payload,'title',$run->subject_type.' #'.$run->subject_id) }}
                    </div>
                </div>
                <div class="automation-actions">
                    @if($run->rule?->action_key === 'waiting.return_to_daily')
                        <form method="POST" action="{{ route('automation-center.confirm',$run) }}">
                            @csrf
                            <button class="automation-button" type="submit" data-busy-label="Confirmando…">Confirmar</button>
                        </form>
                    @else
                        <span class="automation-pill">Sin ejecución segura todavía</span>
                    @endif
                    <form method="POST" action="{{ route('automation-center.reject',$run) }}">
                        @csrf
                        <button class="automation-button secondary" type="submit">Rechazar</button>
                    </form>
                </div>
                <div class="automation-meta">
                    {{ $run->evaluated_at?->format('d/m/Y H:i') }}
                </div>
            </div>
        @empty
            <div class="automation-empty">No hay decisiones pendientes.</div>
        @endforelse
    </section>

    <section class="automation-card" style="margin-top:14px">
        <h2>Ejecuciones recientes</h2>
        @forelse($recentRuns as $run)
            <div class="automation-run-row">
                <div>
                    <strong>{{ $run->rule?->name ?? 'Regla' }}</strong>
                    <div class="automation-meta">
                        {{ data_get($run->payload,'title',$run->subject_type.' #'.$run->subject_id) }}
                    </div>
                </div>
                <div><span class="automation-pill">{{ $run->outcome }}</span></div>
                <div class="automation-meta">{{ $run->evaluated_at?->format('d/m/Y H:i') }}</div>
            </div>
        @empty
            <div class="automation-empty">Aún no hay ejecuciones registradas.</div>
        @endforelse
    </section>
</div>
<x-operational-interactions />
</body>
</html>
