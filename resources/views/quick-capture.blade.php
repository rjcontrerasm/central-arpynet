<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="color-scheme"
        content="light dark"
    >
    <title>Captura rápida · Central ARPYNET</title>

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

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #0b1020;
            color: #f8fafc;
        }

        a {
            color: inherit;
        }

        .shell {
            width: min(100%, 760px);
            margin: 0 auto;
            min-height: 100vh;
            padding: 18px 16px 40px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 24px;
        }

        .brand {
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .back {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 14px;
        }

        .hero {
            margin-bottom: 20px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: clamp(28px, 7vw, 40px);
            line-height: 1;
            letter-spacing: -0.05em;
        }

        .hero p {
            margin: 0;
            color: #94a3b8;
            line-height: 1.5;
        }

        .card {
            background: #11182b;
            border: 1px solid #24304b;
            border-radius: 22px;
            padding: 18px;
            box-shadow:
                0 18px 60px rgba(0, 0, 0, .18);
        }

        .stack {
            display: grid;
            gap: 16px;
        }

        label {
            display: grid;
            gap: 7px;
            font-weight: 700;
            font-size: 14px;
        }

        input,
        select {
            width: 100%;
            border: 1px solid #334155;
            border-radius: 14px;
            background: #0f172a;
            color: #f8fafc;
            min-height: 48px;
            padding: 11px 13px;
            font: inherit;
            outline: none;
        }

        input:focus,
        select:focus {
            border-color: #60a5fa;
            box-shadow:
                0 0 0 3px rgba(96, 165, 250, .16);
        }

        #title {
            min-height: 62px;
            font-size: 18px;
            font-weight: 700;
        }

        .chips {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .chip {
            position: relative;
        }

        .chip input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .chip span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 9px 10px;
            border: 1px solid #334155;
            border-radius: 13px;
            color: #cbd5e1;
            background: #0f172a;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
        }

        .chip input:checked + span {
            border-color: #60a5fa;
            background: #172554;
            color: #dbeafe;
        }

        .grid2 {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .advanced {
            border-top: 1px solid #24304b;
            padding-top: 2px;
        }

        .advanced summary {
            cursor: pointer;
            list-style: none;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 750;
            padding: 10px 0 4px;
        }

        .advanced summary::-webkit-details-marker {
            display: none;
        }

        .advanced summary::after {
            content: ' +';
            color: #60a5fa;
        }

        .advanced[open] summary::after {
            content: ' −';
        }

        .advanced .grid2 {
            margin-top: 10px;
        }

        .advanced-hint {
            margin-top: 8px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.45;
        }

        .submit {
            width: 100%;
            border: 0;
            border-radius: 15px;
            min-height: 54px;
            background: #2563eb;
            color: white;
            font: inherit;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
        }

        .submit:active {
            transform: translateY(1px);
        }

        .success,
        .errors {
            margin-bottom: 16px;
            padding: 13px 15px;
            border-radius: 14px;
            line-height: 1.4;
        }

        .success {
            border: 1px solid #166534;
            background: #052e16;
            color: #bbf7d0;
        }

        .errors {
            border: 1px solid #991b1b;
            background: #450a0a;
            color: #fecaca;
        }

        .recent {
            margin-top: 22px;
        }

        .recent h2 {
            margin: 0 0 12px;
            font-size: 17px;
        }

        .recent-list {
            display: grid;
            gap: 8px;
        }

        .recent-item {
            padding: 12px 13px;
            background: #11182b;
            border: 1px solid #24304b;
            border-radius: 14px;
        }

        .recent-title {
            font-weight: 700;
            line-height: 1.35;
        }

        .recent-meta {
            margin-top: 4px;
            color: #94a3b8;
            font-size: 12px;
        }

        .custom-date {
            display: none;
        }

        .custom-date.visible {
            display: grid;
        }

        @media (min-width: 640px) {
            .shell {
                padding-top: 28px;
            }

            .chips {
                grid-template-columns:
                    repeat(5, minmax(0, 1fr));
            }

            .card {
                padding: 24px;
            }
        }

        @media (prefers-color-scheme: light) {
            body {
                background: #f8fafc;
                color: #0f172a;
            }

            .hero p,
            .back,
            .recent-meta {
                color: #64748b;
            }

            .card,
            .recent-item {
                background: white;
                border-color: #e2e8f0;
            }

            input,
            select,
            .chip span {
                background: white;
                color: #0f172a;
                border-color: #cbd5e1;
            }

            .chip input:checked + span {
                background: #eff6ff;
                color: #1d4ed8;
                border-color: #3b82f6;
            }

            .success {
                background: #f0fdf4;
                color: #166534;
            }

            .errors {
                background: #fef2f2;
                color: #991b1b;
            }
        }
    </style>
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>

        <x-operational-nav active="capture" />
    </div>

    <section class="hero">
        <h1>Captura rápida</h1>
        <p>
            Escribe la tarea, elige cuándo y guarda.
            Lo demás puede esperar.
        </p>
    </section>

    @if (session('quick_capture_success'))
        <div class="success">
            {{ session('quick_capture_success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="errors">
            <strong>Revisa estos datos:</strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        class="card stack"
        method="POST"
        action="{{ route('quick-capture.store') }}"
        autocomplete="off"
    >
        @csrf

        <label>
            ¿Qué tienes que hacer?

            <input
                id="title"
                name="title"
                type="text"
                maxlength="255"
                placeholder="Ej. Enviar informe SUNARP"
                value="{{ old('title') }}"
                autofocus
                required
            >
        </label>

        <label>
            Empresa / ámbito

            <select
                name="organization_id"
                required
            >
                @foreach ($organizations as $organization)
                    <option
                        value="{{ $organization->id }}"
                        @selected(
                            (string) old(
                                'organization_id',
                                $defaultOrganizationId,
                            )
                            === (string) $organization->id
                        )
                    >
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <div>
            <div
                style="
                    font-size:14px;
                    font-weight:700;
                    margin-bottom:7px;
                "
            >
                ¿Cuándo?
            </div>

            <div class="chips">
                @foreach ([
                    'today' => 'Hoy',
                    'tomorrow' => 'Mañana',
                    'next_week' => '1 semana',
                    'none' => 'Sin fecha',
                    'custom' => 'Elegir',
                ] as $value => $label)
                    <label class="chip">
                        <input
                            type="radio"
                            name="due_mode"
                            value="{{ $value }}"
                            @checked(
                                old('due_mode', 'today')
                                === $value
                            )
                        >
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <label
            id="custom-date-wrapper"
            class="custom-date"
        >
            Fecha

            <input
                type="date"
                name="due_date"
                value="{{ old('due_date') }}"
            >
        </label>

        <details class="advanced">
            <summary>Opciones avanzadas</summary>

            <div class="advanced-hint">
                Normal funciona para la mayoría de tareas.
                Ajusta urgencia o impacto solo cuando realmente
                necesites alterar la prioridad.
            </div>

            <div class="grid2">
                <label>
                    Urgencia

                    <select name="urgency">
                        @foreach ([
                            'low' => 'Baja',
                            'normal' => 'Normal',
                            'high' => 'Alta',
                            'critical' => 'Crítica',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(
                                    old('urgency', 'normal')
                                    === $value
                                )
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Impacto

                    <select name="impact">
                        @foreach ([
                            'low' => 'Bajo',
                            'normal' => 'Normal',
                            'high' => 'Alto',
                            'critical' => 'Crítico',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(
                                    old('impact', 'normal')
                                    === $value
                                )
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </details>

        <button
            class="submit"
            type="submit"
        >
            Guardar tarea
        </button>
    </form>

    @if ($recentTasks->isNotEmpty())
        <section class="recent">
            <h2>Últimas capturas</h2>

            <div class="recent-list">
                @foreach ($recentTasks as $task)
                    <div class="recent-item">
                        <div class="recent-title">
                            {{ $task->title }}
                        </div>

                        <div class="recent-meta">
                            {{ $task->organization?->name ?? 'Sin ámbito' }}

                            @if ($task->due_at)
                                · vence
                                {{ $task->due_at->format('d/m/Y') }}
                            @else
                                · sin fecha
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

<script>
    const radios = document.querySelectorAll(
        'input[name="due_mode"]'
    );

    const customDate = document.getElementById(
        'custom-date-wrapper'
    );

    function refreshCustomDate() {
        const selected = document.querySelector(
            'input[name="due_mode"]:checked'
        );

        customDate.classList.toggle(
            'visible',
            selected && selected.value === 'custom'
        );
    }

    radios.forEach((radio) => {
        radio.addEventListener(
            'change',
            refreshCustomDate
        );
    });

    refreshCustomDate();
</script>
</body>
</html>
