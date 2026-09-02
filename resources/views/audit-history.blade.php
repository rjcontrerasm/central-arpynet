<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="color-scheme" content="light dark">

    <title>Historial · Central ARPYNET</title>

    <style>
        :root {
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            color-scheme: light dark;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #0b1020;
            color: #f8fafc;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .shell {
            width: min(100%, 1040px);
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .topbar,
        .hero,
        .audit-head,
        .stats {
            display: flex;
            gap: 12px;
        }

        .topbar,
        .hero,
        .audit-head {
            align-items: center;
            justify-content: space-between;
        }

        .topbar {
            margin-bottom: 24px;
        }

        .brand {
            font-weight: 850;
            letter-spacing: -.03em;
        }

        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
            color: #94a3b8;
            font-size: 13px;
        }

        .nav a.active {
            color: #fff;
            font-weight: 800;
        }

        .hero {
            align-items: end;
            margin-bottom: 16px;
        }

        h1 {
            margin: 0;
            font-size: clamp(31px, 7vw, 46px);
            line-height: 1;
            letter-spacing: -.05em;
        }

        .subtitle {
            margin-top: 7px;
            color: #94a3b8;
            font-size: 13px;
        }

        .stats {
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .stat {
            min-width: 125px;
            padding: 12px 14px;
            border: 1px solid #24304b;
            border-radius: 14px;
            background: #11182b;
        }

        .stat strong {
            display: block;
            font-size: 23px;
            letter-spacing: -.04em;
        }

        .stat span {
            color: #94a3b8;
            font-size: 11px;
        }

        .filters {
            display: grid;
            grid-template-columns:
                minmax(180px, 1.4fr)
                repeat(4, minmax(120px, .7fr))
                auto;
            gap: 8px;
            margin-bottom: 18px;
        }

        .filters input,
        .filters select {
            width: 100%;
            min-height: 40px;
            padding: 9px 10px;
            border: 1px solid #334155;
            border-radius: 10px;
            background: #0f172a;
            color: #e2e8f0;
        }

        .filters button {
            min-height: 40px;
            padding: 9px 15px;
            border: 0;
            border-radius: 10px;
            background: #e2e8f0;
            color: #0f172a;
            font-weight: 800;
            cursor: pointer;
        }

        .list {
            display: grid;
            gap: 9px;
        }

        .audit {
            padding: 14px;
            border: 1px solid #24304b;
            border-radius: 15px;
            background: #11182b;
        }

        .audit-head {
            align-items: start;
        }

        .title {
            font-weight: 800;
            line-height: 1.3;
        }

        .meta,
        .source,
        .empty {
            color: #94a3b8;
        }

        .meta {
            margin-top: 5px;
            font-size: 12px;
        }

        .badge {
            flex: 0 0 auto;
            padding: 5px 8px;
            border: 1px solid #334155;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .badge.created {
            border-color: #166534;
            color: #86efac;
        }

        .badge.updated {
            border-color: #a16207;
            color: #fde68a;
        }

        .badge.deleted {
            border-color: #991b1b;
            color: #fca5a5;
        }

        .fields {
            display: grid;
            gap: 5px;
            margin-top: 12px;
        }

        .field {
            padding: 8px 10px;
            border-radius: 9px;
            background: #0c1325;
            font-size: 11px;
            line-height: 1.45;
        }

        .field-name {
            font-weight: 800;
        }

        .old {
            color: #fca5a5;
        }

        .new {
            color: #86efac;
        }

        .source {
            margin-top: 10px;
            font-size: 10px;
        }

        .empty {
            padding: 28px;
            border: 1px dashed #334155;
            border-radius: 15px;
            text-align: center;
            font-size: 12px;
        }

        .pagination {
            margin-top: 18px;
        }

        @media (max-width: 860px) {
            .filters {
                grid-template-columns: 1fr 1fr;
            }

            .filters .search {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 520px) {
            .hero {
                align-items: start;
                flex-direction: column;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .filters .search {
                grid-column: auto;
            }

            .stat {
                flex: 1 1 120px;
            }
        }

        @media (prefers-color-scheme: light) {
            body {
                background: #f8fafc;
                color: #0f172a;
            }

            .nav,
            .subtitle,
            .meta,
            .source,
            .empty,
            .stat span {
                color: #64748b;
            }

            .nav a.active {
                color: #0f172a;
            }

            .stat,
            .audit {
                background: #fff;
                border-color: #e2e8f0;
            }

            .filters input,
            .filters select {
                background: #fff;
                border-color: #cbd5e1;
                color: #0f172a;
            }

            .filters button {
                background: #0f172a;
                color: #fff;
            }

            .field {
                background: #f8fafc;
            }
        }
    </style>
</head>

<body>
@php
    $eventLabels = [
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
    ];

    $fieldLabels = [
        'organization_id' => 'Ámbito',
        'project_id' => 'Proyecto',
        'client_id' => 'Cliente',
        'recurring_obligation_id' => 'Obligación',
        'title' => 'Título',
        'name' => 'Nombre',
        'legal_name' => 'Razón social',
        'tax_id' => 'RUC',
        'category' => 'Categoría',
        'type' => 'Tipo',
        'horizon' => 'Horizonte',
        'status' => 'Estado',
        'stage' => 'Etapa',
        'urgency' => 'Urgencia',
        'impact' => 'Impacto',
        'severity' => 'Severidad',
        'start_date' => 'Inicio',
        'target_date' => 'Objetivo',
        'due_at' => 'Vence',
        'due_date' => 'Vence',
        'next_action' => 'Siguiente acción',
        'next_action_at' => 'Fecha siguiente acción',
        'waiting_since' => 'En espera desde',
        'waiting_until' => 'Seguimiento',
        'amount' => 'Monto',
        'invoice_amount' => 'Facturado',
        'expected_amount' => 'Monto esperado',
        'actual_amount' => 'Monto real',
        'currency' => 'Moneda',
        'quotation_number' => 'Cotización',
        'quotation_date' => 'Fecha cotización',
        'order_number' => 'Orden',
        'order_received_date' => 'Orden recibida',
        'report_submitted_date' => 'Informe presentado',
        'conformity_date' => 'Conformidad',
        'invoice_number' => 'Factura',
        'invoice_date' => 'Fecha factura',
        'invoice_due_date' => 'Vence factura',
        'paid_date' => 'Pagado',
        'closed_date' => 'Cerrado',
        'frequency' => 'Frecuencia',
        'anchor_date' => 'Fecha base',
        'end_date' => 'Fin',
        'reminder_days_before' => 'Aviso previo',
        'is_critical' => 'Crítico',
        'is_active' => 'Activo',
        'is_private' => 'Privado',
        'provider' => 'Proveedor',
        'reference' => 'Referencia',
        'payment_reference' => 'Referencia de pago',
    ];

    $formatValue = static function ($value) {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value === true || $value === 1 || $value === '1') {
            return 'Sí';
        }

        if ($value === false || $value === 0 || $value === '0') {
            return 'No';
        }

        if (is_array($value)) {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
            );
        }

        return (string) $value;
    };
@endphp

<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <x-operational-nav active="history" />
    </div>

    <section class="hero">
        <div>
            <h1>Historial</h1>

            <div class="subtitle">
                Cambios operativos registrados en Central.
            </div>
        </div>
    </section>

    <div class="stats">
        <div class="stat">
            <strong>{{ $todayCount }}</strong>
            <span>cambios hoy</span>
        </div>

        <div class="stat">
            <strong>{{ $total }}</strong>
            <span>en el filtro actual</span>
        </div>
    </div>

    <form
        method="GET"
        action="/historial"
        class="filters"
    >
        <input
            class="search"
            type="search"
            name="q"
            value="{{ $filters['q'] }}"
            placeholder="Buscar tarea, proyecto, servicio..."
        >

        <select name="scope">
            <option value="">Todos los ámbitos</option>

            @foreach ($organizations as $organization)
                <option
                    value="{{ $organization->id }}"
                    @selected(
                        $filters['scope']
                        === $organization->id
                    )
                >
                    {{ $organization->name }}
                </option>
            @endforeach
        </select>

        <select name="type">
            <option value="">Todos los tipos</option>

            @foreach ($types as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(
                        $filters['type']
                        === $value
                    )
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <select name="event">
            <option value="">Todos los eventos</option>

            @foreach ($eventLabels as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(
                        $filters['event']
                        === $value
                    )
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <select name="days">
            @foreach ([
                '7' => '7 días',
                '30' => '30 días',
                '90' => '90 días',
                'all' => 'Todo',
            ] as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(
                        $filters['days']
                        === $value
                    )
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <button type="submit">Filtrar</button>
    </form>

    <div class="list">
        @forelse ($changes as $change)
            @php
                $payload = $change->changes ?? [];
                $updatedFields =
                    $payload['fields'] ?? [];
                $snapshot =
                    $payload['new']
                    ?? $payload['old']
                    ?? [];
            @endphp

            <article class="audit">
                <div class="audit-head">
                    <div>
                        <div class="title">
                            {{ $types[$change->subject_type]
                                ?? $change->subject_type }}
                            ·
                            {{ $change->subject_label }}
                        </div>

                        <div class="meta">
                            {{ $change->organization?->name
                                ?? 'Sin ámbito' }}
                            ·
                            {{ $change->occurred_at
                                ->timezone(
                                    config(
                                        'app.timezone',
                                        'America/Lima'
                                    )
                                )
                                ->format('d/m/Y H:i') }}
                            ·
                            {{ $change->user?->name
                                ?? 'Sistema' }}
                        </div>
                    </div>

                    <span
                        class="badge {{ $change->event }}"
                    >
                        {{ $eventLabels[$change->event]
                            ?? $change->event }}
                    </span>
                </div>

                @if (! empty($updatedFields))
                    <div class="fields">
                        @foreach ($updatedFields as $field => $values)
                            <div class="field">
                                <span class="field-name">
                                    {{ $fieldLabels[$field]
                                        ?? $field }}:
                                </span>

                                <span class="old">
                                    {{ $formatValue(
                                        $values['old']
                                        ?? null
                                    ) }}
                                </span>

                                →

                                <span class="new">
                                    {{ $formatValue(
                                        $values['new']
                                        ?? null
                                    ) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @elseif (! empty($snapshot))
                    <div class="fields">
                        @foreach (
                            array_slice(
                                $snapshot,
                                0,
                                6,
                                true
                            )
                            as $field => $value
                        )
                            <div class="field">
                                <span class="field-name">
                                    {{ $fieldLabels[$field]
                                        ?? $field }}:
                                </span>

                                {{ $formatValue($value) }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="source">
                    Origen:
                    {{ $change->source === 'system'
                        ? 'Sistema'
                        : 'Usuario' }}
                </div>
            </article>
        @empty
            <div class="empty">
                No hay cambios para este filtro.
            </div>
        @endforelse
    </div>

    <div class="pagination">
        {{ $changes->links() }}
    </div>
</div>
</body>
</html>
