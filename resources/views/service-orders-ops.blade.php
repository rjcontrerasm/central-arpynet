<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="color-scheme" content="light dark">
    <title>Servicios · Central ARPYNET</title>

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
            width: min(100%, 1120px);
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .topbar,
        .hero,
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
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
            font-size: 13px;
            color: #94a3b8;
        }

        .hero {
            align-items: end;
            margin-bottom: 16px;
        }

        h1 {
            margin: 0;
            font-size: clamp(30px, 7vw, 46px);
            letter-spacing: -.05em;
            line-height: .98;
        }

        .subtitle {
            margin-top: 7px;
            color: #94a3b8;
            font-size: 13px;
        }

        .admin-link {
            padding: 11px 14px;
            border-radius: 12px;
            background: #2563eb;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
        }

        .success {
            margin-bottom: 14px;
            padding: 12px 14px;
            border: 1px solid #166534;
            border-radius: 13px;
            background: #052e16;
            color: #bbf7d0;
            font-size: 13px;
            font-weight: 750;
        }

        .filters {
            display: grid;
            gap: 9px;
            margin-bottom: 16px;
        }

        .scroll {
            display: flex;
            gap: 7px;
            overflow-x: auto;
            padding-bottom: 2px;
        }

        .chip {
            flex: 0 0 auto;
            padding: 7px 10px;
            border: 1px solid #334155;
            border-radius: 999px;
            background: #0f172a;
            color: #cbd5e1;
            font-size: 11px;
            font-weight: 750;
        }

        .chip.active {
            border-color: #60a5fa;
            background: #172554;
            color: #dbeafe;
        }

        .search {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
        }

        .search input,
        .editor input,
        .editor select {
            width: 100%;
            min-height: 40px;
            padding: 8px 10px;
            border: 1px solid #334155;
            border-radius: 10px;
            background: #11182b;
            color: #f8fafc;
        }

        .search button,
        .save {
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        .search button {
            padding: 8px 13px;
        }

        .money-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-bottom: 22px;
        }

        .money {
            padding: 13px;
            border: 1px solid #24304b;
            border-radius: 15px;
            background: #11182b;
        }

        .money-value {
            font-size: 20px;
            font-weight: 850;
            letter-spacing: -.035em;
        }

        .money-label {
            margin-top: 5px;
            color: #94a3b8;
            font-size: 11px;
        }

        .finance-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .finance-grid .full {
            grid-column: 1 / -1;
        }

        .pill.overdue {
            background: #450a0a;
            color: #fecaca;
        }

        .pill.receivable {
            background: #172554;
            color: #bfdbfe;
        }

        .pill.paid {
            background: #052e16;
            color: #bbf7d0;
        }

        .stats {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-bottom: 22px;
        }

        .stat,
        .card {
            border: 1px solid #24304b;
            background: #11182b;
        }

        .stat {
            padding: 13px;
            border-radius: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 850;
            letter-spacing: -.05em;
            line-height: 1;
        }

        .stat-label {
            margin-top: 5px;
            color: #94a3b8;
            font-size: 11px;
        }

        .list {
            display: grid;
            gap: 10px;
        }

        .card {
            padding: 14px;
            border-radius: 16px;
        }

        .card-head {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 10px;
        }

        .title {
            font-weight: 800;
            line-height: 1.3;
        }

        .meta {
            margin-top: 4px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.45;
        }

        .pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .pill {
            padding: 4px 8px;
            border-radius: 999px;
            background: #1e293b;
            color: #cbd5e1;
            font-size: 10px;
            font-weight: 800;
        }

        .pill.critical {
            background: #450a0a;
            color: #fecaca;
        }

        .pill.attention {
            background: #431407;
            color: #fed7aa;
        }

        .pill.watch {
            background: #422006;
            color: #fde68a;
        }

        .next {
            margin-top: 10px;
            padding: 10px;
            border-radius: 11px;
            background: #0f172a;
            font-size: 12px;
        }

        .reason {
            margin-top: 5px;
            color: #fbbf24;
        }

        details {
            margin-top: 10px;
            border-top: 1px solid #24304b;
            padding-top: 9px;
        }

        summary {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            padding: 6px 10px;
            border: 1px solid #334155;
            border-radius: 10px;
            background: #0f172a;
            color: #cbd5e1;
            list-style: none;
            cursor: pointer;
            font-size: 11px;
            font-weight: 800;
        }

        summary::-webkit-details-marker {
            display: none;
        }

        .editor {
            display: grid;
            gap: 9px;
            margin-top: 9px;
            padding: 11px;
            border-radius: 12px;
            background: #0f172a;
        }

        .field {
            display: grid;
            gap: 4px;
            color: #94a3b8;
            font-size: 10px;
            font-weight: 800;
        }

        .save {
            min-height: 40px;
        }

        .empty {
            padding: 24px 16px;
            border: 1px dashed #334155;
            border-radius: 16px;
            color: #94a3b8;
            text-align: center;
            font-size: 13px;
        }

        @media (min-width: 760px) {
            .money-grid,
            .stats {
                grid-template-columns:
                    repeat(5, minmax(0, 1fr));
            }

            .list {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
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
            .stat-label,
            .field,
            .empty {
                color: #64748b;
            }

            .money,
            .stat,
            .card {
                background: #fff;
                border-color: #e2e8f0;
            }

            .chip,
            summary {
                background: #fff;
                color: #475569;
                border-color: #cbd5e1;
            }

            .chip.active {
                background: #eff6ff;
                color: #1d4ed8;
                border-color: #60a5fa;
            }

            .next,
            .editor {
                background: #f8fafc;
            }

            .search input,
            .editor input,
            .editor select {
                background: #fff;
                color: #0f172a;
                border-color: #cbd5e1;
            }
        }
    </style>
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <nav class="nav">
            <a href="{{ route('daily-ops.show') }}">
                Mi día
            </a>

            <a href="{{ route('service-orders-ops.show') }}">
                Servicios
            </a>

            <a href="{{ route('quick-capture.show') }}">
                Captura
            </a>

            <a href="{{ url('/admin') }}">
                Panel →
            </a>
        </nav>
    </div>

    @if (session('ops_success'))
        <div class="success">
            {{ session('ops_success') }}
        </div>
    @endif

    <section class="hero">
        <div>
            <h1>Servicios</h1>

            <div class="subtitle">
                Órdenes, siguiente acción y estancamiento
            </div>
        </div>

        <a
            class="admin-link"
            href="{{ url('/admin/ordenes-servicio') }}"
        >
            + Nueva / administrar
        </a>
    </section>

    @php
        $base = array_filter([
            'scope' => $selectedScope,
            'stage' => $selectedStage,
            'focus' => $focus,
            'finance' => $finance,
            'q' => $search !== '' ? $search : null,
        ]);
    @endphp

    <section class="filters">
        <div class="scroll">
            <a
                class="chip {{
                    $selectedScope ? '' : 'active'
                }}"
                href="{{ route(
                    'service-orders-ops.show',
                    array_filter([
                        'stage' => $selectedStage,
                        'focus' => $focus,
                        'finance' => $finance,
                        'q' => $search !== ''
                            ? $search
                            : null,
                    ]),
                ) }}"
            >
                Todos
            </a>

            @foreach ($organizations as $organization)
                <a
                    class="chip {{
                        $selectedScope === $organization->id
                            ? 'active'
                            : ''
                    }}"
                    href="{{ route(
                        'service-orders-ops.show',
                        array_filter([
                            'scope' => $organization->id,
                            'stage' => $selectedStage,
                            'focus' => $focus,
                            'q' => $search !== ''
                                ? $search
                                : null,
                        ]),
                    ) }}"
                >
                    {{ $organization->name }}
                </a>
            @endforeach
        </div>

        <div class="scroll">
            <a
                class="chip {{
                    $focus === 'attention'
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'service-orders-ops.show',
                    array_merge(
                        $base,
                        ['focus' => 'attention'],
                    ),
                ) }}"
            >
                Requieren atención
            </a>

            <a
                class="chip {{
                    $focus === 'all'
                        ? 'active'
                        : ''
                }}"
                href="{{ route(
                    'service-orders-ops.show',
                    array_merge(
                        $base,
                        ['focus' => 'all'],
                    ),
                ) }}"
            >
                Todas
            </a>

            @foreach ($stageOptions as $value => $label)
                <a
                    class="chip {{
                        $selectedStage === $value
                            ? 'active'
                            : ''
                    }}"
                    href="{{ route(
                        'service-orders-ops.show',
                        array_merge(
                            $base,
                            ['stage' => $value],
                        ),
                    ) }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="scroll">
            @foreach ([
                'all' => 'Finanzas: todas',
                'pending_invoice' => 'Por facturar',
                'receivable' => 'Por cobrar',
                'overdue' => 'Cobro vencido',
                'paid' => 'Pagado',
            ] as $value => $label)
                <a
                    class="chip {{
                        $finance === $value
                            ? 'active'
                            : ''
                    }}"
                    href="{{ route(
                        'service-orders-ops.show',
                        array_merge(
                            $base,
                            ['finance' => $value],
                        ),
                    ) }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form
            class="search"
            method="GET"
            action="{{ route('service-orders-ops.show') }}"
        >
            @if ($selectedScope)
                <input
                    type="hidden"
                    name="scope"
                    value="{{ $selectedScope }}"
                >
            @endif

            @if ($selectedStage)
                <input
                    type="hidden"
                    name="stage"
                    value="{{ $selectedStage }}"
                >
            @endif

            <input
                type="hidden"
                name="focus"
                value="{{ $focus }}"
            >

            <input
                type="hidden"
                name="finance"
                value="{{ $finance }}"
            >

            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar título, orden o cotización..."
            >

            <button type="submit">
                Buscar
            </button>
        </form>
    </section>

    <section class="money-grid">
        @foreach ([
            'Monto servicios' => $financialSummary[
                'service_amount'
            ],
            'Facturado' => $financialSummary['invoiced'],
            'Por cobrar' => $financialSummary['outstanding'],
            'Vencido' => $financialSummary['overdue'],
            'Pagado' => $financialSummary['paid'],
        ] as $label => $value)
            <div class="money">
                <div class="money-value">
                    S/ {{ number_format(
                        $value,
                        2,
                        '.',
                        ',',
                    ) }}
                </div>
                <div class="money-label">
                    {{ $label }}
                </div>
            </div>
        @endforeach
    </section>

    <section class="stats">
        <div class="stat">
            <div class="stat-value">
                {{ $summary['critical'] }}
            </div>
            <div class="stat-label">Críticas</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $summary['attention'] }}
            </div>
            <div class="stat-label">A vigilar</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $summary['execution'] }}
            </div>
            <div class="stat-label">En ejecución</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $summary['invoice'] }}
            </div>
            <div class="stat-label">Facturadas</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $summary['total'] }}
            </div>
            <div class="stat-label">Mostradas</div>
        </div>
    </section>

    <div class="list">
        @forelse ($orders as $order)
            <article class="card">
                <div class="card-head">
                    <div>
                        <div class="title">
                            {{ $order->title }}
                        </div>

                        <div class="meta">
                            {{ $order->organization?->name
                                ?? 'Sin ámbito' }}
                            ·
                            {{ $order->client?->name
                                ?? 'Sin cliente' }}
                        </div>
                    </div>

                    <span
                        class="pill {{ $order->ops_level }}"
                    >
                        {{ $order->ops_label }}
                    </span>
                </div>

                <div class="pills">
                    <span
                        class="pill {{ $order->fin_status }}"
                    >
                        {{ $order->fin_label }}
                    </span>

                    <span class="pill">
                        {{
                            $stageOptions[$order->stage]
                                ?? $order->stage
                        }}
                    </span>

                    <span class="pill">
                        {{ $order->ops_days_in_stage }}
                        días en etapa
                    </span>

                    @if ($order->order_number)
                        <span class="pill">
                            OS {{ $order->order_number }}
                        </span>
                    @endif
                </div>

                <div class="next">
                    <strong>Siguiente acción:</strong>
                    {{ $order->next_action
                        ?: 'Sin definir' }}

                    @if ($order->next_action_at)
                        <div class="meta">
                            {{
                                $order->next_action_at->format(
                                    'd/m/Y H:i',
                                )
                            }}
                        </div>
                    @endif

                    @if ($order->end_date)
                        <div class="meta">
                            Fin contractual:
                            {{
                                $order->end_date->format(
                                    'd/m/Y',
                                )
                            }}
                        </div>
                    @endif

                    @foreach (
                        $order->ops_reasons
                        as $reason
                    )
                        <div class="reason">
                            {{ $reason }}
                        </div>
                    @endforeach
                </div>

                <div class="next">
                    <strong>Finanzas:</strong>

                    @if ($order->fin_service_amount > 0)
                        <div class="meta">
                            Servicio:
                            {{ $order->currency }}
                            {{ number_format(
                                $order->fin_service_amount,
                                2,
                                '.',
                                ',',
                            ) }}
                        </div>
                    @endif

                    @if ($order->fin_is_invoiced)
                        <div class="meta">
                            Factura:
                            {{ $order->invoice_number
                                ?: 'sin número' }}
                            ·
                            {{ $order->currency }}
                            {{ number_format(
                                $order->fin_invoice_amount,
                                2,
                                '.',
                                ',',
                            ) }}
                        </div>
                    @endif

                    @if ($order->invoice_due_date)
                        <div class="meta">
                            Vence:
                            {{ $order->invoice_due_date->format(
                                'd/m/Y',
                            ) }}
                        </div>
                    @endif

                    @if ($order->paid_date)
                        <div class="meta">
                            Pagado:
                            {{ $order->paid_date->format(
                                'd/m/Y',
                            ) }}
                        </div>
                    @endif
                </div>

                <details>
                    <summary>
                        Actualizar finanzas
                    </summary>

                    <form
                        class="editor"
                        method="POST"
                        action="{{ route(
                            'service-orders-finance.update',
                            $order,
                        ) }}"
                    >
                        @csrf

                        @if ($selectedScope)
                            <input
                                type="hidden"
                                name="scope"
                                value="{{ $selectedScope }}"
                            >
                        @endif

                        @if ($selectedStage)
                            <input
                                type="hidden"
                                name="filter_stage"
                                value="{{ $selectedStage }}"
                            >
                        @endif

                        <input
                            type="hidden"
                            name="focus"
                            value="{{ $focus }}"
                        >

                        <input
                            type="hidden"
                            name="finance"
                            value="{{ $finance }}"
                        >

                        @if ($search !== '')
                            <input
                                type="hidden"
                                name="q"
                                value="{{ $search }}"
                            >
                        @endif

                        <div class="finance-grid">
                            <label class="field">
                                Moneda
                                <select name="currency">
                                    @foreach ([
                                        'PEN' => 'PEN',
                                        'USD' => 'USD',
                                    ] as $value => $label)
                                        <option
                                            value="{{ $value }}"
                                            @selected(
                                                $order->currency
                                                === $value
                                            )
                                        >
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="field">
                                Monto servicio
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="amount"
                                    value="{{ $order->amount }}"
                                >
                            </label>

                            <label class="field full">
                                N.° factura
                                <input
                                    type="text"
                                    name="invoice_number"
                                    value="{{ $order->invoice_number }}"
                                    maxlength="100"
                                >
                            </label>

                            <label class="field">
                                Fecha factura
                                <input
                                    type="date"
                                    name="invoice_date"
                                    value="{{ $order->invoice_date?->format(
                                        'Y-m-d',
                                    ) }}"
                                >
                            </label>

                            <label class="field">
                                Monto factura
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="invoice_amount"
                                    value="{{ $order->invoice_amount }}"
                                >
                            </label>

                            <label class="field">
                                Vence factura
                                <input
                                    type="date"
                                    name="invoice_due_date"
                                    value="{{ $order->invoice_due_date?->format(
                                        'Y-m-d',
                                    ) }}"
                                >
                            </label>

                            <label class="field">
                                Fecha pago
                                <input
                                    type="date"
                                    name="paid_date"
                                    value="{{ $order->paid_date?->format(
                                        'Y-m-d',
                                    ) }}"
                                >
                            </label>

                            <label class="field full">
                                <span>
                                    Incluye impuestos
                                </span>

                                <input
                                    type="hidden"
                                    name="includes_tax"
                                    value="0"
                                >

                                <input
                                    type="checkbox"
                                    name="includes_tax"
                                    value="1"
                                    @checked($order->includes_tax)
                                >
                            </label>
                        </div>

                        <button
                            class="save"
                            type="submit"
                        >
                            Guardar finanzas
                        </button>
                    </form>
                </details>

                <details>
                    <summary>
                        Actualizar seguimiento
                    </summary>

                    <form
                        class="editor"
                        method="POST"
                        action="{{ route(
                            'service-orders-ops.update',
                            $order,
                        ) }}"
                    >
                        @csrf

                        @if ($selectedScope)
                            <input
                                type="hidden"
                                name="scope"
                                value="{{ $selectedScope }}"
                            >
                        @endif

                        @if ($selectedStage)
                            <input
                                type="hidden"
                                name="filter_stage"
                                value="{{ $selectedStage }}"
                            >
                        @endif

                        <input
                            type="hidden"
                            name="focus"
                            value="{{ $focus }}"
                        >

                        @if ($search !== '')
                            <input
                                type="hidden"
                                name="q"
                                value="{{ $search }}"
                            >
                        @endif

                        <label class="field">
                            Etapa

                            <select name="stage" required>
                                @foreach (
                                    $stageOptions
                                    as $value => $label
                                )
                                    <option
                                        value="{{ $value }}"
                                        @selected(
                                            $order->stage === $value
                                        )
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            Siguiente acción

                            <input
                                type="text"
                                name="next_action"
                                value="{{ $order->next_action }}"
                                maxlength="255"
                                placeholder="Ej. enviar informe"
                            >
                        </label>

                        <label class="field">
                            Fecha y hora

                            <input
                                type="datetime-local"
                                name="next_action_at"
                                value="{{ $order->next_action_at?->format(
                                    'Y-m-d\TH:i',
                                ) }}"
                            >
                        </label>

                        <button
                            class="save"
                            type="submit"
                        >
                            Guardar seguimiento
                        </button>
                    </form>
                </details>
            </article>
        @empty
            <div class="empty">
                No hay órdenes que coincidan con estos filtros.
                Para registrar la primera, usa
                “Nueva / administrar”.
            </div>
        @endforelse
    </div>
</div>
</body>
</html>
