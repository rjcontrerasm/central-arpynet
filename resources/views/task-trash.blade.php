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

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/task-trash.css') }}?v={{ filemtime(public_path('central-assets/pages/task-trash.css')) }}"
    >
</head>

<body>
<div class="shell">
    <x-operational-page-header
        active="trash"
        title="Papelera"
        subtitle="Restaurar es reversible. Eliminar definitivamente no se puede deshacer."
    />

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
