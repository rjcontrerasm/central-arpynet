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

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/audit-history.css') }}?v=2.39.2"
    >
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
    <x-operational-theme />
    <x-operational-interactions />
</body>
</html>
