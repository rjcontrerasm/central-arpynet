<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Convertir tarea · Central ARPYNET</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, sans-serif; color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #0b1020; color: #f8fafc; }
        a { color: inherit; }
        .shell { width: min(100%, 760px); margin: 0 auto; padding: 28px 16px 60px; }
        .back { color: #93c5fd; text-decoration: none; font-size: 13px; font-weight: 750; }
        h1 { margin: 20px 0 6px; font-size: 34px; letter-spacing: -.04em; }
        .subtitle, .meta, .hint { color: #94a3b8; }
        .subtitle { font-size: 13px; }
        .task, .form { margin-top: 18px; padding: 16px; border: 1px solid #24304b; border-radius: 16px; background: #11182b; }
        .task-title { font-weight: 820; }
        .meta { margin-top: 5px; font-size: 12px; }
        .form { display: grid; gap: 14px; }
        label { display: grid; gap: 7px; font-size: 13px; font-weight: 750; }
        select, input { width: 100%; min-height: 46px; padding: 10px 12px; border: 1px solid #334155; border-radius: 11px; background: #0f172a; color: #f8fafc; font: inherit; }
        .hint { margin-top: 5px; font-size: 11px; }
        button { min-height: 50px; border: 0; border-radius: 12px; background: #2563eb; color: #fff; font: inherit; font-weight: 850; cursor: pointer; }
        .errors { margin-top: 14px; padding: 12px; border: 1px solid #991b1b; border-radius: 12px; background: #450a0a; color: #fecaca; }
        @media (prefers-color-scheme: light) {
            body { background: #f8fafc; color: #0f172a; }
            .task, .form { background: #fff; border-color: #e2e8f0; }
            select, input { background: #fff; color: #0f172a; border-color: #cbd5e1; }
        }
    </style>
</head>
<body>
<div class="shell">
    <a class="back" href="{{ route('daily-ops.show') }}">← Volver a Mi día</a>

    <h1>Convertir tarea</h1>
    <div class="subtitle">Promueve la tarea sin volver a ingresar la información.</div>

    <div class="task">
        <div class="task-title">{{ $task->title }}</div>
        <div class="meta">{{ $task->organization?->name ?? 'Sin ámbito' }}</div>
    </div>

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form class="form" method="POST" action="{{ route('task-conversion.store', $task) }}">
        @csrf

        <label>
            Convertir en
            <select name="target" id="conversion-target" required>
                <option value="project">Proyecto</option>
                <option value="service">Servicio / oportunidad</option>
                <option value="recurring">Tarea recurrente</option>
                <option value="waiting">Seguimiento en espera</option>
            </select>
        </label>

        <div id="service-fields" hidden>
            <label>
                Cliente
                <select name="client_id">
                    <option value="">Seleccionar cliente</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div id="recurring-fields" hidden>
            <label>
                Frecuencia
                <select name="frequency">
                    @foreach ($frequencies as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="hint">Empieza en la siguiente ocurrencia; la tarea actual se conserva.</div>
        </div>

        <div id="waiting-fields" hidden>
            <label>
                Seguimiento
                <input type="date" name="waiting_until" value="{{ $task->due_at?->format('Y-m-d') }}">
            </label>

            <label>
                Motivo
                <input type="text" name="waiting_reason" maxlength="255" placeholder="Esperando respuesta o aprobación">
            </label>
        </div>

        <button type="submit" data-busy-label="Convirtiendo…">Convertir</button>
    </form>
</div>

<script>
(() => {
    const target = document.getElementById('conversion-target');
    const groups = {
        service: document.getElementById('service-fields'),
        recurring: document.getElementById('recurring-fields'),
        waiting: document.getElementById('waiting-fields'),
    };

    const refresh = () => {
        Object.entries(groups).forEach(([key, element]) => {
            element.hidden = target.value !== key;
        });
    };

    target.addEventListener('change', refresh);
    refresh();
})();
</script>

<x-operational-interactions />
</body>
</html>
