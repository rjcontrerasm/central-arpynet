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
    <title>Papelera · Central ARPYNET</title>

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

        .shell {
            width: min(100%, 1200px);
            margin: 0 auto;
            padding: 24px 16px 70px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 26px;
        }

        .brand {
            font-weight: 850;
            letter-spacing: -.03em;
        }

        h1 {
            margin: 0;
            font-size: 38px;
            letter-spacing: -.05em;
        }

        .subtitle {
            margin-top: 6px;
            color: #94a3b8;
            font-size: 13px;
        }

        .success {
            margin: 16px 0;
            padding: 11px 13px;
            border: 1px solid #166534;
            border-radius: 12px;
            background: #052e16;
            color: #bbf7d0;
            font-size: 12px;
            font-weight: 750;
        }

        .list {
            display: grid;
            gap: 10px;
            margin-top: 20px;
        }

        .item {
            padding: 15px;
            border: 1px solid #24304b;
            border-radius: 16px;
            background: #11182b;
        }

        .title {
            font-weight: 820;
        }

        .meta {
            margin-top: 5px;
            color: #94a3b8;
            font-size: 12px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        button,
        input {
            font: inherit;
        }

        button {
            min-height: 38px;
            padding: 7px 11px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 820;
        }

        .restore {
            border: 0;
            background: #2563eb;
            color: #fff;
        }

        .purge {
            border: 1px solid #7f1d1d;
            background: #450a0a;
            color: #fecaca;
        }

        .purge-form {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .purge-form input {
            width: 105px;
            min-height: 38px;
            padding: 7px 9px;
            border: 1px solid #7f1d1d;
            border-radius: 9px;
            background: #0f172a;
            color: #f8fafc;
            font-size: 11px;
        }

        .empty {
            padding: 24px;
            border: 1px dashed #334155;
            border-radius: 16px;
            color: #94a3b8;
            text-align: center;
        }

        @media (prefers-color-scheme: light) {
            body {
                background: #f8fafc;
                color: #0f172a;
            }

            .item {
                background: #fff;
                border-color: #e2e8f0;
            }

            .purge-form input {
                background: #fff;
                color: #0f172a;
            }
        }
    </style>
</head>

<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">
            Central ARPYNET
        </div>

        <x-operational-nav active="trash" />
    </div>

    <h1>Papelera</h1>

    <div class="subtitle">
        Restaurar es reversible. Eliminar definitivamente no se puede deshacer.
    </div>

    @if (session('trash_success'))
        <div class="success">
            {{ session('trash_success') }}
        </div>
    @endif

    <div class="list">
        @forelse ($tasks as $task)
            <article
                class="item"
                data-operational-card
            >
                <div class="title">
                    {{ $task->title }}
                </div>

                <div class="meta">
                    {{ $task->organization?->name ?? 'Sin ámbito' }}
                    · eliminada
                    {{ $task->deleted_at?->diffForHumans() }}
                </div>

                <div class="actions">
                    <form
                        method="POST"
                        action="{{ route(
                            'task-lifecycle.restore',
                            $task->id,
                        ) }}"
                    >
                        @csrf

                        <button
                            class="restore"
                            type="submit"
                            data-busy-label="Restaurando…"
                        >
                            Restaurar
                        </button>
                    </form>

                    <form
                        class="purge-form"
                        method="POST"
                        action="{{ route(
                            'task-lifecycle.purge',
                            $task->id,
                        ) }}"
                        data-confirm="Esta eliminación es definitiva y no se puede deshacer. ¿Continuar?"
                    >
                        @csrf

                        <input
                            type="text"
                            name="confirmation"
                            placeholder="ELIMINAR"
                            autocomplete="off"
                            required
                        >

                        <button
                            class="purge"
                            type="submit"
                            data-busy-label="Eliminando…"
                        >
                            Eliminar definitivamente
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <div class="empty">
                La papelera está vacía.
            </div>
        @endforelse
    </div>
</div>

<x-operational-theme />
<x-operational-interactions />
</body>
</html>
