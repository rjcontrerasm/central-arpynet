<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Automatizaciones · Central ARPYNET</title>
    <x-operational-theme />
    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/automation-center.css') }}?v=2.39.2"
    >
</head>
<body>
<div class="automation-page">
    <header class="automation-header">
        <div>
            <h1>Automatizaciones</h1>
            <p>Reglas internas, controladas y auditables. L1 prepara propuestas; L2 puede iniciar tareas críticas pendientes; L3 puede crear una tarea interna de cobranza desde una factura vencida sin siguiente acción. Cada nivel conserva límites, audit y undo; los canales externos permanecen fuera de la autonomía.</p>
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
                Las reglas nuevas nacen inactivas. L1 solo prepara propuestas. L2 requiere opt-in explícito y puede ejecutar únicamente pending → in_progress en tareas críticas con evidencia alta y score ≥92, máximo 3 veces por organización y día. L3 requiere su propio opt-in y solo puede crear una tarea interna de cobranza para un servicio facturado, impago, vencido y sin siguiente acción, con evidencia alta, score ≥95, máximo 2 veces por organización y día. L3 no modifica el servicio ni sus datos financieros. Todos los writes autónomos exigen undo y los canales externos siguen fuera de la autonomía.
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
                    @if(in_array($run->rule?->action_key, [
                        'waiting.return_to_daily',
                        'project.create_task',
                        'service.create_task',
                        'obligation.create_task',
                    ], true))
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
