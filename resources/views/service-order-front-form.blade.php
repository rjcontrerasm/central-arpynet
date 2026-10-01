<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $serviceOrder ? 'Editar servicio' : 'Nuevo servicio' }} · Central ARPYNET</title>
    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/service-order-front-form.css') }}?v={{ filemtime(public_path('central-assets/pages/service-order-front-form.css')) }}"
    >
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="services" />
    </div>

    @if(session('service_front_success'))<div class="success">{{ session('service_front_success') }}</div>@endif
    @if(session('service_milestone_success'))<div class="success">{{ session('service_milestone_success') }}</div>@endif
    @if($errors->any())<div class="errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="hero"><div><h1>{{ $serviceOrder ? 'Editar servicio' : 'Nuevo servicio' }}</h1><div class="subtitle">Seguimiento comercial, ejecución y finanzas desde CENTRAL Front.</div></div></section>
    @if(! $canWrite)<div class="readonly">Tienes acceso de solo lectura a este servicio.</div>@endif

    @php
        $organizationId=(int)old('organization_id',$serviceOrder?->organization_id ?? $defaultOrganizationId);
        $selectedClient=(int)old('client_id',$serviceOrder?->client_id ?? 0);
        $selectedAssignee=(int)old('assigned_to',$serviceOrder?->assigned_to ?? auth()->id());
        $selectedWorkTeam=(int)old('work_team_id',$serviceOrder?->work_team_id ?? 0);
    @endphp

    <section class="panel">
        <form method="POST" action="{{ $serviceOrder ? route('service-order-front.update',$serviceOrder) : route('service-order-front.store') }}">
            @csrf
            <div class="section-title">Servicio</div>
            <div class="grid">
                <div class="field">
                    <label for="organization_id">Empresa / ámbito</label>
                    <select id="organization_id" name="organization_id" {{ $serviceOrder || ! $canWrite ? 'disabled' : '' }} required>
                        @foreach($writableOrganizations as $organization)<option value="{{ $organization->id }}" @selected($organizationId === (int)$organization->id)>{{ $organization->name }}</option>@endforeach
                    </select>
                    @if($serviceOrder)<input type="hidden" name="organization_id" value="{{ $serviceOrder->organization_id }}"><div class="help">El ámbito de un servicio existente no se cambia desde esta ficha.</div>@endif
                </div>

                <div class="field">
                    <label for="client_id">Cliente</label>
                    <select id="client_id" name="client_id" {{ ! $canWrite ? 'disabled' : '' }} required>
                        <option value="">Seleccionar cliente</option>
                        @foreach($clientOptions as $id=>$client)
                            <option value="{{ $id }}" data-organizations="{{ implode(',',$client['organization_ids']) }}" @selected($selectedClient === (int)$id)>{{ $client['name'] }} — {{ implode(' · ',$client['organization_names']) }}</option>
                        @endforeach
                    </select>
                    <div class="help">Solo se habilitan clientes asociados a la empresa seleccionada.</div>
                </div>

                <div class="field span-2"><label for="title">Servicio / asunto</label><input id="title" name="title" maxlength="255" required value="{{ old('title',$serviceOrder?->title) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="stage">Etapa</label><select id="stage" name="stage" {{ ! $canWrite ? 'disabled' : '' }} required>@foreach(\App\Models\ServiceOrder::stageOptions() as $value=>$label)<option value="{{ $value }}" @selected(old('stage',$serviceOrder?->stage ?? 'opportunity') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="assigned_to">Responsable</label><select id="assigned_to" name="assigned_to" {{ ! $canWrite ? 'disabled' : '' }}><option value="">Sin asignar</option>@foreach($assigneeOptions as $id=>$assignee)<option value="{{ $id }}" data-organizations="{{ implode(',',$assignee['organization_ids']) }}" data-work-teams="{{ implode(',',$assignee['work_team_ids']) }}" @selected($selectedAssignee === (int)$id)>{{ $assignee['name'] }}</option>@endforeach</select></div>
                <div class="field">
                    <label for="work_team_id">Equipo de trabajo</label>
                    <select id="work_team_id" name="work_team_id" {{ ! $canWrite ? 'disabled' : '' }}>
                        <option value="">Sin equipo · visible por empresa</option>
                        @foreach($workTeamOptions as $id=>$team)
                            <option value="{{ $id }}" @selected($selectedWorkTeam === (int)$id)>{{ $team['name'] }}</option>
                        @endforeach
                    </select>
                    <div class="help">Si seleccionas un equipo, los hitos nuevos se crearán como tareas compartidas con ese equipo.</div>
                </div>
                <div class="field span-2"><label for="description">Descripción</label><textarea id="description" name="description" {{ ! $canWrite ? 'disabled' : '' }}>{{ old('description',$serviceOrder?->description) }}</textarea></div>
                <div class="field"><label for="next_action">Próxima acción</label><input id="next_action" name="next_action" maxlength="255" value="{{ old('next_action',$serviceOrder?->next_action) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="next_action_at">Fecha de seguimiento</label><input id="next_action_at" type="datetime-local" name="next_action_at" value="{{ old('next_action_at',$serviceOrder?->next_action_at?->format('Y-m-d\TH:i')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
            </div>

            <div class="section-title">Cotización y orden</div>
            <div class="grid">
                <div class="field"><label for="quotation_number">N.º de cotización</label><input id="quotation_number" name="quotation_number" maxlength="80" value="{{ old('quotation_number',$serviceOrder?->quotation_number) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="quotation_date">Fecha de cotización</label><input id="quotation_date" type="date" name="quotation_date" value="{{ old('quotation_date',$serviceOrder?->quotation_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="order_number">N.º de orden</label><input id="order_number" name="order_number" maxlength="100" value="{{ old('order_number',$serviceOrder?->order_number) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="order_received_date">Recepción de orden</label><input id="order_received_date" type="date" name="order_received_date" value="{{ old('order_received_date',$serviceOrder?->order_received_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="start_date">Inicio</label><input id="start_date" type="date" name="start_date" value="{{ old('start_date',$serviceOrder?->start_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="end_date">Fin previsto / contractual</label><input id="end_date" type="date" name="end_date" value="{{ old('end_date',$serviceOrder?->end_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
            </div>

            <div class="section-title">Monto y facturación</div>
            <div class="grid">
                <div class="field"><label for="amount">Monto de la operación</label><input id="amount" type="number" min="0" step="0.01" name="amount" value="{{ old('amount',$serviceOrder?->amount) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="currency">Moneda</label><select id="currency" name="currency" {{ ! $canWrite ? 'disabled' : '' }} required>@foreach(['PEN'=>'Soles (PEN)','USD'=>'Dólares (USD)','EUR'=>'Euros (EUR)'] as $value=>$label)<option value="{{ $value }}" @selected(old('currency',$serviceOrder?->currency ?? 'PEN') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="field span-2"><label class="check"><input type="checkbox" name="includes_tax" value="1" @checked(old('includes_tax',$serviceOrder?->includes_tax ?? true)) {{ ! $canWrite ? 'disabled' : '' }}> Monto incluye IGV</label></div>
                <div class="field"><label for="report_submitted_date">Informe presentado</label><input id="report_submitted_date" type="date" name="report_submitted_date" value="{{ old('report_submitted_date',$serviceOrder?->report_submitted_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="conformity_date">Conformidad recibida</label><input id="conformity_date" type="date" name="conformity_date" value="{{ old('conformity_date',$serviceOrder?->conformity_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="invoice_number">Factura</label><input id="invoice_number" name="invoice_number" maxlength="100" value="{{ old('invoice_number',$serviceOrder?->invoice_number) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="invoice_date">Fecha de factura</label><input id="invoice_date" type="date" name="invoice_date" value="{{ old('invoice_date',$serviceOrder?->invoice_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="invoice_due_date">Vencimiento de factura</label><input id="invoice_due_date" type="date" name="invoice_due_date" value="{{ old('invoice_due_date',$serviceOrder?->invoice_due_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="invoice_amount">Monto facturado</label><input id="invoice_amount" type="number" min="0" step="0.01" name="invoice_amount" value="{{ old('invoice_amount',$serviceOrder?->invoice_amount) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="paid_date">Fecha de pago</label><input id="paid_date" type="date" name="paid_date" value="{{ old('paid_date',$serviceOrder?->paid_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="closed_date">Fecha de cierre</label><input id="closed_date" type="date" name="closed_date" value="{{ old('closed_date',$serviceOrder?->closed_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
            </div>

            <div class="section-title">Documentos y notas</div>
            <div class="grid">
                <div class="field span-2"><label for="drive_url">Carpeta de Google Drive</label><input id="drive_url" type="url" name="drive_url" maxlength="255" value="{{ old('drive_url',$serviceOrder?->drive_url) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field span-2"><label for="notes">Notas</label><textarea id="notes" name="notes" {{ ! $canWrite ? 'disabled' : '' }}>{{ old('notes',$serviceOrder?->notes) }}</textarea></div>
            </div>

            <div class="actions">
                @if($canWrite)<button class="primary" type="submit" data-busy-label="Guardando…">{{ $serviceOrder ? 'Guardar cambios' : 'Crear servicio' }}</button>@endif
                <a class="secondary" href="{{ route('service-orders-ops.show') }}">Volver a servicios</a>
            </div>
        </form>
    </section>

    @if($serviceOrder)
        @php
            $milestoneCount = $serviceOrder->milestones->count();
            $milestoneCompleted = $serviceOrder->milestones
                ->filter(
                    fn ($milestone) =>
                        $milestone->conformity_date
                        || $milestone->task?->status === 'completed',
                )
                ->count();
            $milestoneOverdue = $serviceOrder->milestones
                ->filter(
                    fn ($milestone) => $milestone->is_overdue,
                )
                ->count();
            $milestoneProgress = $milestoneCount > 0
                ? (int) round(($milestoneCompleted / $milestoneCount) * 100)
                : 0;
            $eligibleMilestoneAssignees = collect($assigneeOptions)
                ->filter(
                    fn ($assignee) =>
                        ! $serviceOrder->work_team_id
                        || in_array(
                            (int) $serviceOrder->work_team_id,
                            $assignee['work_team_ids'],
                            true,
                        ),
                );
        @endphp

        <section class="panel milestone-panel" id="cronograma">
            <div class="milestone-heading">
                <div>
                    <div class="section-title milestone-title">Cronograma / hitos</div>
                    <div class="help">
                        Cada hito genera una tarea operativa vinculada a esta orden.
                        Las fechas son contractuales y no se interpretan como recurrencia mensual.
                    </div>
                </div>
                <div class="milestone-summary">
                    <strong>{{ $milestoneCompleted }}/{{ $milestoneCount }}</strong>
                    <span>completados</span>
                    @if($milestoneOverdue > 0)
                        <span class="milestone-overdue">{{ $milestoneOverdue }} vencido{{ $milestoneOverdue === 1 ? '' : 's' }}</span>
                    @endif
                </div>
            </div>

            <div class="milestone-progress" aria-label="Avance de hitos: {{ $milestoneProgress }}%">
                <span style="width: {{ $milestoneProgress }}%"></span>
            </div>

            <div class="milestone-list">
                @forelse($serviceOrder->milestones as $milestone)
                    @php
                        $task = $milestone->task;
                        $isOverdue = $milestone->is_overdue;
                    @endphp
                    <article class="milestone-card {{ $isOverdue ? 'is-overdue' : '' }}">
                        <div class="milestone-main">
                            <div class="milestone-number">{{ $milestone->sequence }}</div>
                            <div class="milestone-copy">
                                <strong>{{ $milestone->title }}</strong>
                                <div class="milestone-meta">
                                    <span>{{ $milestone->contractual_due_date ? 'Vence '.$milestone->contractual_due_date->format('d/m/Y') : 'Sin fecha contractual' }}</span>
                                    <span>{{ $task?->assignee?->name ?? 'Sin responsable' }}</span>
                                    @if($serviceOrder->workTeam)<span>{{ $serviceOrder->workTeam->name }}</span>@endif
                                </div>
                            </div>
                            <span class="milestone-status {{ $isOverdue ? 'danger' : '' }}">
                                {{ $milestone->operational_status_label }}
                            </span>
                        </div>

                        @if($milestone->delivered_date || $milestone->conformity_date || $milestone->amount)
                            <div class="milestone-contract">
                                @if($milestone->delivered_date)<span>Entregado: {{ $milestone->delivered_date->format('d/m/Y') }}</span>@endif
                                @if($milestone->conformity_date)<span>Conformidad: {{ $milestone->conformity_date->format('d/m/Y') }}</span>@endif
                                @if($milestone->amount)<span>Monto: {{ $serviceOrder->currency }} {{ number_format((float)$milestone->amount,2,'.',',') }}</span>@endif
                            </div>
                        @endif

                        @if($canWrite)
                            <details class="milestone-editor">
                                <summary>Editar hito</summary>
                                <form method="POST" action="{{ route('service-order-milestones.update',[$serviceOrder,$milestone]) }}">
                                    @csrf
                                    <div class="grid milestone-grid">
                                        <div class="field span-2"><label>Título</label><input name="title" required maxlength="255" value="{{ $milestone->title }}"></div>
                                        <div class="field"><label>Fecha contractual</label><input type="date" name="contractual_due_date" value="{{ $milestone->contractual_due_date?->format('Y-m-d') }}"></div>
                                        <div class="field"><label>Responsable</label><select name="assigned_to">@foreach($eligibleMilestoneAssignees as $id=>$assignee)<option value="{{ $id }}" @selected((int)$task?->assigned_to === (int)$id)>{{ $assignee['name'] }}</option>@endforeach</select></div>
                                        <div class="field"><label>Prioridad</label><select name="urgency">@foreach(['low'=>'Baja','normal'=>'Normal','high'=>'Alta','critical'=>'Crítica'] as $value=>$label)<option value="{{ $value }}" @selected(($task?->urgency ?? 'normal') === $value)>{{ $label }}</option>@endforeach</select></div>
                                        <div class="field"><label>Monto asociado</label><input type="number" min="0" step="0.01" name="amount" value="{{ $milestone->amount }}"></div>
                                        <div class="field"><label>Fecha de entrega</label><input type="date" name="delivered_date" value="{{ $milestone->delivered_date?->format('Y-m-d') }}"></div>
                                        <div class="field"><label>Fecha de conformidad</label><input type="date" name="conformity_date" value="{{ $milestone->conformity_date?->format('Y-m-d') }}"></div>
                                        <div class="field span-2"><label>Descripción</label><textarea name="description">{{ $milestone->description }}</textarea></div>
                                        <div class="field span-2"><label>Notas contractuales</label><textarea name="notes">{{ $milestone->notes }}</textarea></div>
                                    </div>
                                    <div class="actions"><button class="primary" type="submit" data-busy-label="Guardando…">Guardar hito</button></div>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <div class="milestone-empty">
                        Esta orden todavía no tiene hitos. Agrega el primer entregable para generar su tarea operativa.
                    </div>
                @endforelse
            </div>

            @if($canWrite)
                <div class="milestone-create">
                    <div class="section-title">Agregar hito / entregable</div>
                    <form method="POST" action="{{ route('service-order-milestones.store',$serviceOrder) }}">
                        @csrf
                        <div class="grid milestone-grid">
                            <div class="field span-2"><label for="milestone_title">Título del entregable</label><input id="milestone_title" name="title" required maxlength="255" placeholder="Ej. Entregable 1 · Informe de implementación"></div>
                            <div class="field"><label for="milestone_due">Fecha contractual</label><input id="milestone_due" type="date" name="contractual_due_date"></div>
                            <div class="field"><label for="milestone_assignee">Responsable</label><select id="milestone_assignee" name="assigned_to">@foreach($eligibleMilestoneAssignees as $id=>$assignee)<option value="{{ $id }}" @selected((int)$serviceOrder->assigned_to === (int)$id)>{{ $assignee['name'] }}</option>@endforeach</select></div>
                            <div class="field"><label for="milestone_urgency">Prioridad</label><select id="milestone_urgency" name="urgency"><option value="normal">Normal</option><option value="high">Alta</option><option value="critical">Crítica</option><option value="low">Baja</option></select></div>
                            <div class="field"><label for="milestone_amount">Monto asociado</label><input id="milestone_amount" type="number" min="0" step="0.01" name="amount"></div>
                            <div class="field span-2"><label for="milestone_description">Descripción</label><textarea id="milestone_description" name="description"></textarea></div>
                            <div class="field span-2"><label for="milestone_notes">Notas contractuales</label><textarea id="milestone_notes" name="notes"></textarea></div>
                        </div>
                        <div class="actions">
                            <button class="primary" type="submit" data-busy-label="Creando…">Agregar hito y crear tarea</button>
                        </div>
                    </form>
                </div>
            @endif
        </section>
    @endif
</div>
<script src="{{ asset('central-assets/pages/service-order-front-form.js') }}?v={{ filemtime(public_path('central-assets/pages/service-order-front-form.js')) }}"></script>
<x-operational-theme />
<x-operational-interactions />
</body>
</html>