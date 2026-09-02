<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="color-scheme" content="light dark">
    <title>Vencimientos · Central ARPYNET</title>

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
        input {
            font: inherit;
        }

        .shell {
            width: min(100%, 1200px);
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .topbar,
        .hero,
        .card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .topbar { margin-bottom: 24px; }

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

        .hero {
            align-items: end;
            margin-bottom: 16px;
        }

        h1 {
            margin: 0;
            font-size: clamp(30px, 7vw, 46px);
            line-height: .98;
            letter-spacing: -.05em;
        }

        .subtitle,
        .meta,
        .stat-label,
        .money-label,
        .field {
            color: #94a3b8;
        }

        .subtitle {
            margin-top: 7px;
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
            gap: 8px;
            margin-bottom: 18px;
        }

        .filter-label {
            margin-top: 2px;
            color: #64748b;
            font-size: 10px;
            font-weight: 850;
            letter-spacing: .06em;
            text-transform: uppercase;
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
            font-size: 12px;
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
        .payment-form input {
            width: 100%;
            min-height: 40px;
            padding: 8px 10px;
            border: 1px solid #334155;
            border-radius: 10px;
            background: #11182b;
            color: #f8fafc;
        }

        .search button,
        .pay-button {
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

        .stats,
        .money-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-bottom: 18px;
        }

        .stat,
        .money,
        .card {
            border: 1px solid #24304b;
            background: #11182b;
        }

        .stat,
        .money {
            padding: 13px;
            border-radius: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 850;
            letter-spacing: -.05em;
            line-height: 1;
        }

        .stat-label,
        .money-label {
            margin-top: 5px;
            font-size: 11px;
        }

        .money-value {
            font-size: 19px;
            font-weight: 850;
        }

        .money-currency {
            margin-bottom: 7px;
            color: #93c5fd;
            font-size: 11px;
            font-weight: 850;
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
            align-items: start;
        }

        .title {
            font-weight: 800;
            line-height: 1.3;
        }

        .meta {
            margin-top: 4px;
            font-size: 12px;
            line-height: 1.45;
        }

        .pills,
        .actions {
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

        .pill.overdue,
        .pill.today,
        .pill.critical {
            background: #450a0a;
            color: #fecaca;
        }

        .pill.upcoming {
            background: #422006;
            color: #fde68a;
        }

        .pill.paid {
            background: #052e16;
            color: #bbf7d0;
        }

        .pill.skipped {
            background: #1e293b;
            color: #94a3b8;
        }

        .amount {
            margin-top: 10px;
            padding: 10px;
            border-radius: 11px;
            background: #0f172a;
            font-size: 12px;
        }

        details {
            margin-top: 10px;
            padding-top: 9px;
            border-top: 1px solid #24304b;
        }

        summary,
        .small-action {
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

        .payment-form {
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
            font-size: 10px;
            font-weight: 800;
        }

        .pay-button {
            min-height: 40px;
        }

        .small-action.skip {
            border-color: #92400e;
            color: #fde68a;
        }

        .small-action.reopen {
            border-color: #166534;
            color: #bbf7d0;
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
            .stats {
                grid-template-columns:
                    repeat(5, minmax(0, 1fr));
            }

            .money-grid {
                grid-template-columns:
                    repeat(4, minmax(0, 1fr));
            }

            .list {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .empty {
                grid-column: 1 / -1;
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
            .money-label,
            .field,
            .empty {
                color: #64748b;
            }

            .stat,
            .money,
            .card {
                background: #fff;
                border-color: #e2e8f0;
            }

            .chip,
            summary,
            .small-action {
                background: #fff;
                color: #475569;
                border-color: #cbd5e1;
            }

            .chip.active {
                background: #eff6ff;
                color: #1d4ed8;
                border-color: #60a5fa;
            }

            .pill {
                background: #f1f5f9;
                color: #475569;
            }

            .pill.overdue,
            .pill.today,
            .pill.critical {
                background: #fef2f2;
                color: #b91c1c;
            }

            .pill.upcoming {
                background: #fffbeb;
                color: #a16207;
            }

            .pill.paid {
                background: #f0fdf4;
                color: #166534;
            }

            .amount,
            .payment-form {
                background: #f8fafc;
            }

            .search input,
            .payment-form input {
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

        <x-operational-nav active="obligations" />
    </div>

    @if (session('obligation_success'))
        <div class="success">
            {{ session('obligation_success') }}
        </div>
    @endif

    <section class="hero">
        <div>
            <h1>Vencimientos</h1>

            <div class="subtitle">
                Obligaciones recurrentes, pagos y alertas
            </div>
        </div>

        <a
            class="admin-link"
            href="{{ url('/admin/obligaciones-recurrentes') }}"
        >
            + Nueva / administrar
        </a>
    </section>

    @php
        $base = array_filter([
            'scope' => $selectedScope,
            'focus' => $focus,
            'q' => $search !== '' ? $search : null,
        ]);

        $focuses = [
            'attention' => 'Requieren atención',
            'overdue' => 'Vencidos',
            'today' => 'Hoy',
            'upcoming' => 'Próximos',
            'pending' => 'Pendientes',
            'paid' => 'Pagados',
            'skipped' => 'Omitidos',
            'all' => 'Todos',
        ];
    @endphp

    <section class="filters">
        <div class="filter-label">Ámbito</div>

        <div class="scroll">
            <a
                class="chip {{
                    $selectedScope ? '' : 'active'
                }}"
                href="{{ route(
                    'obligation-ops.show',
                    array_filter([
                        'focus' => $focus,
                        'q' => $search !== ''
                            ? $search
                            : null,
                    ]),
                ) }}"
            >
                Todos los ámbitos
            </a>

            @foreach ($organizations as $organization)
                <a
                    class="chip {{
                        $selectedScope === $organization->id
                            ? 'active'
                            : ''
                    }}"
                    href="{{ route(
                        'obligation-ops.show',
                        array_filter([
                            'scope' => $organization->id,
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

        <div class="filter-label">Estado</div>

        <div class="scroll">
            @foreach ($focuses as $value => $label)
                <a
                    class="chip {{
                        $focus === $value
                            ? 'active'
                            : ''
                    }}"
                    href="{{ route(
                        'obligation-ops.show',
                        array_merge(
                            $base,
                            ['focus' => $value],
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
            action="{{ route('obligation-ops.show') }}"
        >
            @if ($selectedScope)
                <input
                    type="hidden"
                    name="scope"
                    value="{{ $selectedScope }}"
                >
            @endif

            <input
                type="hidden"
                name="focus"
                value="{{ $focus }}"
            >

            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="Buscar obligación, proveedor o referencia..."
            >

            <button type="submit">
                Buscar
            </button>
        </form>
    </section>

    <section class="stats">
        <div class="stat">
            <div class="stat-value">
                {{ $summary['overdue'] }}
            </div>
            <div class="stat-label">Vencidos</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $summary['today'] }}
            </div>
            <div class="stat-label">Hoy</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $summary['upcoming'] }}
            </div>
            <div class="stat-label">Próximos</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $summary['pending'] }}
            </div>
            <div class="stat-label">Pendientes</div>
        </div>

        <div class="stat">
            <div class="stat-value">
                {{ $summary['total'] }}
            </div>
            <div class="stat-label">Mostrados</div>
        </div>
    </section>

    @foreach ($moneySummary as $currency => $money)
        <div class="money-currency">
            Resumen {{ $currency }}
        </div>

        <section class="money-grid">
            @foreach ([
                'Esperado' => $money['expected'],
                'Pendiente' => $money['pending'],
                'Vencido' => $money['overdue'],
                'Pagado' => $money['paid'],
            ] as $label => $value)
                <div class="money">
                    <div class="money-value">
                        {{ $currency }}
                        {{ number_format(
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
    @endforeach

    <div class="list">
        @forelse ($occurrences as $occurrence)
            <article class="card">
                <div class="card-head">
                    <div>
                        <div class="title">
                            {{ $occurrence->obligation?->name
                                ?? 'Obligación' }}
                        </div>

                        <div class="meta">
                            {{ $occurrence->organization?->name
                                ?? 'Sin ámbito' }}

                            @if ($occurrence->obligation?->provider)
                                ·
                                {{ $occurrence->obligation->provider }}
                            @endif
                        </div>
                    </div>

                    <span
                        class="pill {{ $occurrence->ops_level }}"
                    >
                        {{ $occurrence->ops_label }}
                    </span>
                </div>

                <div class="pills">
                    <span class="pill">
                        Vence
                        {{ $occurrence->due_date->format(
                            'd/m/Y',
                        ) }}
                    </span>

                    @if ($occurrence->obligation?->is_critical)
                        <span class="pill critical">
                            Crítica
                        </span>
                    @endif

                    @if ($occurrence->obligation?->category)
                        <span class="pill">
                            {{ $occurrence->obligation->category }}
                        </span>
                    @endif
                </div>

                @if (
                    $occurrence->expected_amount !== null
                    || $occurrence->actual_amount !== null
                    || $occurrence->paid_date
                    || $occurrence->payment_reference
                )
                    <div class="amount">
                    @if ($occurrence->expected_amount !== null)
                        <div>
                            Esperado:
                            {{ $occurrence->currency }}
                            {{ number_format(
                                (float) $occurrence->expected_amount,
                                2,
                                '.',
                                ',',
                            ) }}
                        </div>
                    @endif

                    @if ($occurrence->actual_amount !== null)
                        <div class="meta">
                            Real:
                            {{ $occurrence->currency }}
                            {{ number_format(
                                (float) $occurrence->actual_amount,
                                2,
                                '.',
                                ',',
                            ) }}
                        </div>
                    @endif

                    @if ($occurrence->paid_date)
                        <div class="meta">
                            Pagado:
                            {{ $occurrence->paid_date->format(
                                'd/m/Y',
                            ) }}
                        </div>
                    @endif

                    @if ($occurrence->payment_reference)
                        <div class="meta">
                            Ref.:
                            {{ $occurrence->payment_reference }}
                        </div>
                    @endif
                    </div>
                @endif

                @if ($occurrence->status === 'pending')
                    <details>
                        <summary>Registrar pago</summary>

                        <form
                            class="payment-form"
                            method="POST"
                            action="{{ route(
                                'obligation-ops.update',
                                $occurrence,
                            ) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="action"
                                value="paid"
                            >

                            @if ($selectedScope)
                                <input
                                    type="hidden"
                                    name="scope"
                                    value="{{ $selectedScope }}"
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
                                Monto pagado

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="actual_amount"
                                    value="{{ $occurrence->expected_amount }}"
                                >
                            </label>

                            <label class="field">
                                Fecha de pago

                                <input
                                    type="date"
                                    name="paid_date"
                                    value="{{ $now->format('Y-m-d') }}"
                                >
                            </label>

                            <label class="field">
                                Referencia

                                <input
                                    type="text"
                                    name="payment_reference"
                                    maxlength="255"
                                    placeholder="Operación, factura, recibo..."
                                >
                            </label>

                            <label class="field">
                                URL del comprobante

                                <input
                                    type="url"
                                    name="receipt_url"
                                    placeholder="https://..."
                                >
                            </label>

                            <button
                                class="pay-button"
                                type="submit"
                            >
                                Registrar pago
                            </button>
                        </form>
                    </details>

                    <div class="actions">
                        <form
                            method="POST"
                            action="{{ route(
                                'obligation-ops.update',
                                $occurrence,
                            ) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="action"
                                value="skipped"
                            >

                            @if ($selectedScope)
                                <input
                                    type="hidden"
                                    name="scope"
                                    value="{{ $selectedScope }}"
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

                            <button
                                class="small-action skip"
                                type="submit"
                            >
                                Omitir
                            </button>
                        </form>
                    </div>
                @else
                    <div class="actions">
                        <form
                            method="POST"
                            action="{{ route(
                                'obligation-ops.update',
                                $occurrence,
                            ) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="action"
                                value="pending"
                            >

                            @if ($selectedScope)
                                <input
                                    type="hidden"
                                    name="scope"
                                    value="{{ $selectedScope }}"
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

                            <button
                                class="small-action reopen"
                                type="submit"
                            >
                                Reabrir
                            </button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="empty">
                No hay vencimientos que coincidan con estos filtros.
            </div>
        @endforelse
    </div>
</div>
    <x-operational-theme />
    <x-operational-interactions />
</body>
</html>
