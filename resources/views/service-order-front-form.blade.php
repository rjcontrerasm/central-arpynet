<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $serviceOrder ? 'Editar orden / servicio' : 'Nueva orden / servicio' }} · Central ARPYNET</title>
    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/service-order-front-form.css') }}?v={{ filemtime(public_path('central-assets/pages/service-order-front-form.css')) }}"
    >
</head>
<body>
<div class="shell">
    <x-operational-page-header
        active="services"
        :title="$serviceOrder ? 'Editar orden / servicio' : 'Nueva orden / servicio'"
        subtitle="Del compromiso comercial al cronograma, ejecución, conformidad y cobro."
    />

    @if(session('service_front_success'))<div class="success">{{ session('service_front_success') }}</div>@endif
    @if(session('service_milestone_success'))<div class="success">{{ session('service_milestone_success') }}</div>@endif
    @if($errors->any())<div class="errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
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
            <section class="form-section form-section-primary">
                <div class="form-section-heading">
                    <div>
                        <div class="section-kicker">Datos principales</div>
                        <h2>Orden / servicio</h2>
                    </div>
                    <span class="section-badge">01</span>
                </div>
                <div class="grid">
                <div class="field">
                    <label for="organization_id">Empresa / ámbito</label>
                    <select id="organization_id" name="organization_id" {{ $serviceOrder || ! $canWrite ? 'disabled' : '' }} required>
                        @foreach($writableOrganizations as $organization)<option value="{{ $organization->id }}" @selected($organizationId === (int)$organization->id)>{{ $organization->name }}</option>@endforeach
                    </select>
                    @if($serviceOrder)<input type="hidden" name="organization_id" value="{{ $serviceOrder->organization_id }}"><div class="help">El ámbito de un servicio existente no se cambia desde esta ficha.</div>@endif
                </div>

                <div class="field">
                    <div class="field-label-row">
                        <label for="client_id">Cliente</label>
                        @if($canWrite)
                            <button
                                class="inline-create-button"
                                type="button"
                                data-client-modal-open
                                aria-haspopup="dialog"
                            >
                                <span aria-hidden="true">+</span>
                                Nuevo cliente
                            </button>
                        @endif
                    </div>
                    <select id="client_id" name="client_id" {{ ! $canWrite ? 'disabled' : '' }} required>
                        <option value="">Seleccionar cliente</option>
                        @foreach($clientOptions as $id=>$client)
                            <option value="{{ $id }}" data-organizations="{{ implode(',',$client['organization_ids']) }}" @selected($selectedClient === (int)$id)>{{ $client['name'] }} — {{ implode(' · ',$client['organization_names']) }}</option>
                        @endforeach
                    </select>
                    <div class="help" data-client-help>Solo se muestran clientes asociados a la empresa seleccionada.</div>
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
            </section>

            <section class="form-section">
                <div class="form-section-heading">
                    <div>
                        <div class="section-kicker">Compromiso comercial</div>
                        <h2>Cotización y respaldo contractual</h2>
                    </div>
                    <span class="section-badge">02</span>
                </div>
                <div class="grid">
                    <div class="field"><label for="quotation_number">N.º de cotización</label><input id="quotation_number" name="quotation_number" maxlength="80" value="{{ old('quotation_number',$serviceOrder?->quotation_number) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                    <div class="field"><label for="quotation_date">Fecha de cotización</label><input id="quotation_date" type="date" name="quotation_date" value="{{ old('quotation_date',$serviceOrder?->quotation_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                    <div class="field">
                        <label for="contract_document_type">Respaldo contractual</label>
                        <select id="contract_document_type" name="contract_document_type" {{ ! $canWrite ? 'disabled' : '' }}>
                            @foreach(['none'=>'Sin contrato','contract'=>'Contrato','direct_order'=>'Orden directa','other'=>'Otro'] as $value=>$label)
                                <option value="{{ $value }}" @selected(old('contract_document_type',$serviceOrder?->contract_document_type ?? 'none') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label for="contract_number">N.º de contrato / referencia</label><input id="contract_number" name="contract_number" maxlength="120" value="{{ old('contract_number',$serviceOrder?->contract_number) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                    <div class="field"><label for="contract_date">Fecha de contrato</label><input id="contract_date" type="date" name="contract_date" value="{{ old('contract_date',$serviceOrder?->contract_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                    <div class="field"><label for="contract_amount">Monto contractual</label><input id="contract_amount" type="number" min="0" step="0.01" name="contract_amount" value="{{ old('contract_amount',$serviceOrder?->contract_amount) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                    <div class="field"><label for="start_date">Inicio</label><input id="start_date" type="date" name="start_date" value="{{ old('start_date',$serviceOrder?->start_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                    <div class="field"><label for="end_date">Fin previsto / contractual</label><input id="end_date" type="date" name="end_date" value="{{ old('end_date',$serviceOrder?->end_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                </div>
            </section>

            <details class="form-section form-section-collapsible" @if($serviceOrder && in_array($serviceOrder->stage,['report_submitted','conformity','invoiced','paid','closed'],true)) open @endif>
                <summary class="form-section-heading">
                    <div>
                        <div class="section-kicker">Control económico</div>
                        <h2>Monto, conformidad y facturación</h2>
                    </div>
                    <span class="section-badge">03</span>
                </summary>
                <div class="grid form-section-body">
                <div class="field"><label for="amount">Monto de la operación</label><input id="amount" type="number" min="0" step="0.01" name="amount" value="{{ old('amount',$serviceOrder?->amount) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="currency">Moneda</label><select id="currency" name="currency" {{ ! $canWrite ? 'disabled' : '' }} required>@foreach(['PEN'=>'Soles (PEN)','USD'=>'Dólares (USD)','EUR'=>'Euros (EUR)'] as $value=>$label)<option value="{{ $value }}" @selected(old('currency',$serviceOrder?->currency ?? 'PEN') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="field span-2"><label class="check"><input type="checkbox" name="includes_tax" value="1" @checked(old('includes_tax',$serviceOrder?->includes_tax ?? true)) {{ ! $canWrite ? 'disabled' : '' }}> Monto incluye IGV</label></div>
                <div class="field"><label for="report_submitted_date">Informe presentado</label><input id="report_submitted_date" type="date" name="report_submitted_date" value="{{ old('report_submitted_date',$serviceOrder?->report_submitted_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field"><label for="conformity_date">Conformidad recibida</label><input id="conformity_date" type="date" name="conformity_date" value="{{ old('conformity_date',$serviceOrder?->conformity_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field span-2"><div class="help">Las facturas se gestionan como registros independientes debajo de esta ficha para permitir facturación parcial o por ejercicios.</div></div>
                <div class="field"><label for="closed_date">Fecha de cierre</label><input id="closed_date" type="date" name="closed_date" value="{{ old('closed_date',$serviceOrder?->closed_date?->format('Y-m-d')) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                </div>
            </details>

            <details class="form-section form-section-collapsible" @if($serviceOrder?->drive_url || $serviceOrder?->notes) open @endif>
                <summary class="form-section-heading">
                    <div>
                        <div class="section-kicker">Soporte documental</div>
                        <h2>Documentos y notas</h2>
                    </div>
                    <span class="section-badge">04</span>
                </summary>
                <div class="grid form-section-body">
                <div class="field span-2"><label for="drive_url">Carpeta de Google Drive</label><input id="drive_url" type="url" name="drive_url" maxlength="255" value="{{ old('drive_url',$serviceOrder?->drive_url) }}" {{ ! $canWrite ? 'disabled' : '' }}></div>
                <div class="field span-2"><label for="notes">Notas</label><textarea id="notes" name="notes" {{ ! $canWrite ? 'disabled' : '' }}>{{ old('notes',$serviceOrder?->notes) }}</textarea></div>
                </div>
            </details>

            <div class="actions form-actions">
                @if($canWrite)<button class="primary" type="submit" data-busy-label="Guardando…">{{ $serviceOrder ? 'Guardar cambios' : 'Crear orden / servicio' }}</button>@endif
                <a class="secondary" href="{{ route('service-orders-ops.show') }}">Volver a servicios</a>
            </div>
        </form>
    </section>

    @if($serviceOrder)
        @php
            $contractBaseAmount = (float) (
                $serviceOrder->contract_amount
                ?? $serviceOrder->amount
                ?? 0
            );
            $orderedAmount = $serviceOrder->execution_ordered_amount;
            $invoicedAmount = $serviceOrder->invoiced_total;
            $paidAmount = $serviceOrder->paid_total;
            $pendingOrderAmount = max(0, $contractBaseAmount - $orderedAmount);
            $pendingInvoiceAmount = max(0, $contractBaseAmount - $invoicedAmount);
        @endphp

        <section class="panel commercial-panel" id="ejecucion-contractual">
            <div class="commercial-heading">
                <div>
                    <div class="section-title">Órdenes / ejecución presupuestal</div>
                    <div class="help">
                        Un contrato puede ejecutarse mediante una o varias órdenes,
                        incluso en ejercicios fiscales distintos.
                    </div>
                </div>
                <div class="financial-kpis">
                    <span><strong>{{ $serviceOrder->currency }} {{ number_format($orderedAmount,2,'.',',') }}</strong> ordenado</span>
                    <span><strong>{{ $serviceOrder->currency }} {{ number_format($pendingOrderAmount,2,'.',',') }}</strong> pendiente</span>
                </div>
            </div>

            <div class="commercial-list">
                @forelse($serviceOrder->executionOrders as $executionOrder)
                    <article class="commercial-card">
                        <div class="commercial-card-head">
                            <div>
                                <strong>
                                    {{ AppModelsServiceOrderExecutionOrder::documentTypeOptions()[$executionOrder->document_type] ?? 'Documento' }}
                                    {{ $executionOrder->document_number ?: 'pendiente de número' }}
                                </strong>
                                <div class="milestone-meta">
                                    <span>Ejercicio {{ $executionOrder->fiscal_year ?: 'sin definir' }}</span>
                                    <span>{{ $serviceOrder->currency }} {{ number_format((float)($executionOrder->amount ?? 0),2,'.',',') }}</span>
                                    @if($executionOrder->issued_date)<span>Emitida {{ $executionOrder->issued_date->format('d/m/Y') }}</span>@endif
                                </div>
                            </div>
                            <span class="milestone-status">
                                {{ AppModelsServiceOrderExecutionOrder::statusOptions()[$executionOrder->status] ?? $executionOrder->status }}
                            </span>
                        </div>

                        @if($canWrite)
                            <details class="commercial-editor">
                                <summary>Editar orden</summary>
                                <form method="POST" action="{{ route('service-order-execution-orders.update',[$serviceOrder,$executionOrder]) }}">
                                    @csrf
                                    <div class="grid milestone-grid">
                                        <div class="field"><label>Ejercicio fiscal</label><input type="number" min="2000" max="2100" name="fiscal_year" value="{{ $executionOrder->fiscal_year }}"></div>
                                        <div class="field"><label>Tipo</label><select name="document_type">@foreach(AppModelsServiceOrderExecutionOrder::documentTypeOptions() as $value=>$label)<option value="{{ $value }}" @selected($executionOrder->document_type === $value)>{{ $label }}</option>@endforeach</select></div>
                                        <div class="field"><label>N.º documento</label><input name="document_number" maxlength="120" value="{{ $executionOrder->document_number }}"></div>
                                        <div class="field"><label>Fecha de emisión</label><input type="date" name="issued_date" value="{{ $executionOrder->issued_date?->format('Y-m-d') }}"></div>
                                        <div class="field"><label>Monto</label><input type="number" min="0" step="0.01" name="amount" value="{{ $executionOrder->amount }}"></div>
                                        <div class="field"><label>Estado</label><select name="status">@foreach(AppModelsServiceOrderExecutionOrder::statusOptions() as $value=>$label)<option value="{{ $value }}" @selected($executionOrder->status === $value)>{{ $label }}</option>@endforeach</select></div>
                                        <div class="field"><label>Inicio</label><input type="date" name="start_date" value="{{ $executionOrder->start_date?->format('Y-m-d') }}"></div>
                                        <div class="field"><label>Fin</label><input type="date" name="end_date" value="{{ $executionOrder->end_date?->format('Y-m-d') }}"></div>
                                        <div class="field span-2"><label>Notas</label><textarea name="notes">{{ $executionOrder->notes }}</textarea></div>
                                    </div>
                                    <div class="actions"><button class="primary" type="submit" data-busy-label="Guardando…">Guardar orden</button></div>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <div class="milestone-empty">Aún no hay órdenes de ejecución registradas.</div>
                @endforelse
            </div>

            @if($canWrite)
                <details class="commercial-create">
                    <summary>+ Agregar orden de ejecución</summary>
                    <form method="POST" action="{{ route('service-order-execution-orders.store',$serviceOrder) }}">
                        @csrf
                        <div class="grid milestone-grid">
                            <div class="field"><label>Ejercicio fiscal</label><input type="number" min="2000" max="2100" name="fiscal_year" value="{{ now()->year }}"></div>
                            <div class="field"><label>Tipo</label><select name="document_type">@foreach(AppModelsServiceOrderExecutionOrder::documentTypeOptions() as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="field"><label>N.º documento</label><input name="document_number" maxlength="120" placeholder="Puede quedar pendiente"></div>
                            <div class="field"><label>Fecha de emisión</label><input type="date" name="issued_date"></div>
                            <div class="field"><label>Monto</label><input type="number" min="0" step="0.01" name="amount"></div>
                            <div class="field"><label>Estado</label><select name="status">@foreach(AppModelsServiceOrderExecutionOrder::statusOptions() as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="field"><label>Inicio</label><input type="date" name="start_date"></div>
                            <div class="field"><label>Fin</label><input type="date" name="end_date"></div>
                            <div class="field span-2"><label>Notas</label><textarea name="notes"></textarea></div>
                        </div>
                        <div class="actions"><button class="primary" type="submit" data-busy-label="Agregando…">Agregar orden</button></div>
                    </form>
                </details>
            @endif
        </section>

        <section class="panel commercial-panel" id="facturacion">
            <div class="commercial-heading">
                <div>
                    <div class="section-title">Facturación y cobranza</div>
                    <div class="help">
                        Registra tantas facturas como requiera el servicio y vincúlalas,
                        cuando corresponda, a una orden de ejecución.
                    </div>
                </div>
                <div class="financial-kpis">
                    <span><strong>{{ $serviceOrder->currency }} {{ number_format($invoicedAmount,2,'.',',') }}</strong> facturado</span>
                    <span><strong>{{ $serviceOrder->currency }} {{ number_format($paidAmount,2,'.',',') }}</strong> cobrado</span>
                    <span><strong>{{ $serviceOrder->currency }} {{ number_format($pendingInvoiceAmount,2,'.',',') }}</strong> por facturar</span>
                </div>
            </div>

            <div class="commercial-list">
                @forelse($serviceOrder->invoices as $invoice)
                    <article class="commercial-card">
                        <div class="commercial-card-head">
                            <div>
                                <strong>Factura {{ $invoice->number ?: 'pendiente de emisión' }}</strong>
                                <div class="milestone-meta">
                                    <span>{{ $serviceOrder->currency }} {{ number_format((float)($invoice->amount ?? 0),2,'.',',') }}</span>
                                    @if($invoice->executionOrder)<span>{{ $invoice->executionOrder->document_number ?: 'Orden '.$invoice->executionOrder->fiscal_year }}</span>@endif
                                    @if($invoice->issue_date)<span>Emitida {{ $invoice->issue_date->format('d/m/Y') }}</span>@endif
                                    @if($invoice->due_date)<span>Vence {{ $invoice->due_date->format('d/m/Y') }}</span>@endif
                                </div>
                            </div>
                            <span class="milestone-status {{ $invoice->display_status === 'Vencida' ? 'danger' : '' }}">{{ $invoice->display_status }}</span>
                        </div>

                        @if($canWrite)
                            <details class="commercial-editor">
                                <summary>Editar factura</summary>
                                <form method="POST" action="{{ route('service-order-invoices.update',[$serviceOrder,$invoice]) }}">
                                    @csrf
                                    <div class="grid milestone-grid">
                                        <div class="field"><label>N.º factura</label><input name="number" maxlength="120" value="{{ $invoice->number }}"></div>
                                        <div class="field"><label>Orden vinculada</label><select name="execution_order_id"><option value="">Sin orden específica</option>@foreach($serviceOrder->executionOrders as $orderOption)<option value="{{ $orderOption->id }}" @selected((int)$invoice->execution_order_id === (int)$orderOption->id)>{{ $orderOption->fiscal_year }} · {{ $orderOption->document_number ?: 'Pendiente' }}</option>@endforeach</select></div>
                                        <div class="field"><label>Fecha emisión</label><input type="date" name="issue_date" value="{{ $invoice->issue_date?->format('Y-m-d') }}"></div>
                                        <div class="field"><label>Vencimiento</label><input type="date" name="due_date" value="{{ $invoice->due_date?->format('Y-m-d') }}"></div>
                                        <div class="field"><label>Monto</label><input type="number" min="0" step="0.01" name="amount" value="{{ $invoice->amount }}"></div>
                                        <div class="field"><label>Estado</label><select name="status">@foreach(AppModelsServiceOrderInvoice::statusOptions() as $value=>$label)<option value="{{ $value }}" @selected($invoice->status === $value)>{{ $label }}</option>@endforeach</select></div>
                                        <div class="field"><label>Fecha pago</label><input type="date" name="paid_date" value="{{ $invoice->paid_date?->format('Y-m-d') }}"></div>
                                        <div class="field span-2"><label>Notas</label><textarea name="notes">{{ $invoice->notes }}</textarea></div>
                                    </div>
                                    <div class="actions"><button class="primary" type="submit" data-busy-label="Guardando…">Guardar factura</button></div>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <div class="milestone-empty">Aún no hay facturas registradas.</div>
                @endforelse
            </div>

            @if($canWrite)
                <details class="commercial-create">
                    <summary>+ Agregar factura</summary>
                    <form method="POST" action="{{ route('service-order-invoices.store',$serviceOrder) }}">
                        @csrf
                        <div class="grid milestone-grid">
                            <div class="field"><label>N.º factura</label><input name="number" maxlength="120" placeholder="Puede quedar pendiente"></div>
                            <div class="field"><label>Orden vinculada</label><select name="execution_order_id"><option value="">Sin orden específica</option>@foreach($serviceOrder->executionOrders as $orderOption)<option value="{{ $orderOption->id }}">{{ $orderOption->fiscal_year }} · {{ $orderOption->document_number ?: 'Pendiente' }}</option>@endforeach</select></div>
                            <div class="field"><label>Fecha emisión</label><input type="date" name="issue_date"></div>
                            <div class="field"><label>Vencimiento</label><input type="date" name="due_date"></div>
                            <div class="field"><label>Monto</label><input type="number" min="0" step="0.01" name="amount"></div>
                            <div class="field"><label>Estado</label><select name="status">@foreach(AppModelsServiceOrderInvoice::statusOptions() as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="field"><label>Fecha pago</label><input type="date" name="paid_date"></div>
                            <div class="field span-2"><label>Notas</label><textarea name="notes"></textarea></div>
                        </div>
                        <div class="actions"><button class="primary" type="submit" data-busy-label="Agregando…">Agregar factura</button></div>
                    </form>
                </details>
            @endif
        </section>

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

            <progress
                class="milestone-progress"
                value="{{ $milestoneProgress }}"
                max="100"
                aria-label="Avance de hitos: {{ $milestoneProgress }}%"
            >{{ $milestoneProgress }}%</progress>

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
                            <div class="milestone-status-stack">
                                <span class="milestone-status {{ $isOverdue ? 'danger' : '' }}">
                                    Contrato: {{ $milestone->contractual_status_label }}
                                </span>
                                <span class="milestone-status task-status">
                                    Tarea: {{ $milestone->task_status_label }}
                                </span>
                            </div>
                        </div>

                        @if($milestone->delivered_date || $milestone->conformity_date || $milestone->amount || $milestone->executionOrder)
                            <div class="milestone-contract">
                                @if($milestone->executionOrder)<span>Orden: {{ $milestone->executionOrder->fiscal_year }} · {{ $milestone->executionOrder->document_number ?: 'pendiente' }}</span>@endif
                                @if($milestone->delivered_date)<span>Entregado: {{ $milestone->delivered_date->format('d/m/Y') }}</span>@endif
                                @if($milestone->conformity_date)<span>Conformidad: {{ $milestone->conformity_date->format('d/m/Y') }}</span>@endif
                                @if($milestone->amount)<span>Monto: {{ $serviceOrder->currency }} {{ number_format((float)$milestone->amount,2,'.',',') }}</span>@endif
                            </div>
                        @endif

                        @if($canWrite)
                            <div class="milestone-quick-actions">
                                @if($task && $task->status !== 'completed' && $task->status !== 'cancelled')
                                    <form method="POST" action="{{ route('service-order-milestones.action',[$serviceOrder,$milestone]) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="complete_task">
                                        <button class="quick-action" type="submit" data-busy-label="Completando…">✓ Completar tarea</button>
                                    </form>
                                @endif
                                @if(! $milestone->delivered_date)
                                    <form method="POST" action="{{ route('service-order-milestones.action',[$serviceOrder,$milestone]) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="mark_delivered">
                                        <button class="quick-action" type="submit" data-busy-label="Marcando…">Marcar entregado</button>
                                    </form>
                                @endif
                                @if(! $milestone->conformity_date)
                                    <form method="POST" action="{{ route('service-order-milestones.action',[$serviceOrder,$milestone]) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="mark_conformity">
                                        <button class="quick-action" type="submit" data-busy-label="Registrando…">Registrar conformidad</button>
                                    </form>
                                @endif
                                @if($task)
                                    <a class="quick-action secondary-action" href="{{ route('daily-ops.show',['q'=>$task->title]) }}">Ver tarea</a>
                                @endif
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
                                        <div class="field"><label>Orden vinculada</label><select name="execution_order_id"><option value="">Sin orden específica</option>@foreach($serviceOrder->executionOrders as $orderOption)<option value="{{ $orderOption->id }}" @selected((int)$milestone->execution_order_id === (int)$orderOption->id)>{{ $orderOption->fiscal_year }} · {{ $orderOption->document_number ?: 'Pendiente' }}</option>@endforeach</select></div>
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
                            <div class="field"><label for="milestone_execution_order">Orden vinculada</label><select id="milestone_execution_order" name="execution_order_id"><option value="">Sin orden específica</option>@foreach($serviceOrder->executionOrders as $orderOption)<option value="{{ $orderOption->id }}">{{ $orderOption->fiscal_year }} · {{ $orderOption->document_number ?: 'Pendiente' }}</option>@endforeach</select></div>
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
    @if($canWrite)
        <dialog class="client-modal" data-client-modal>
            <form class="client-modal-card" method="POST" action="{{ route('client-ops.store') }}" data-client-inline-form>
                <div class="client-modal-head">
                    <div>
                        <div class="section-kicker">Sin salir de esta orden</div>
                        <h2>Nuevo cliente</h2>
                        <p>Se asociará a la empresa seleccionada y quedará disponible inmediatamente.</p>
                    </div>
                    <button class="modal-close" type="button" data-client-modal-close aria-label="Cerrar">×</button>
                </div>

                <div class="client-modal-context">
                    Empresa: <strong data-client-modal-organization>—</strong>
                </div>

                <div class="grid client-modal-grid">
                    <div class="field span-2">
                        <label for="quick_client_name">Nombre comercial</label>
                        <input id="quick_client_name" name="name" maxlength="255" required autocomplete="organization">
                    </div>
                    <div class="field">
                        <label for="quick_client_tax_id">RUC / documento</label>
                        <input id="quick_client_tax_id" name="tax_id" maxlength="20" inputmode="numeric">
                    </div>
                    <div class="field">
                        <label for="quick_client_legal_name">Razón social</label>
                        <input id="quick_client_legal_name" name="legal_name" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="quick_client_contact">Contacto</label>
                        <input id="quick_client_contact" name="contact_name" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="quick_client_email">Correo</label>
                        <input id="quick_client_email" type="email" name="email" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="quick_client_phone">Teléfono</label>
                        <input id="quick_client_phone" name="phone" maxlength="40">
                    </div>
                </div>

                <div class="client-inline-errors" data-client-inline-errors hidden></div>
                <div class="actions client-modal-actions">
                    <button class="secondary" type="button" data-client-modal-close>Cancelar</button>
                    <button class="primary" type="submit" data-busy-label="Creando…">Crear y seleccionar</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
<script src="{{ asset('central-assets/pages/service-order-front-form.js') }}?v={{ filemtime(public_path('central-assets/pages/service-order-front-form.js')) }}"></script>
<x-operational-theme />
<x-operational-interactions />
</body>
</html>