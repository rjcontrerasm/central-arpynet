<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $subjectTitle }} · Colaboración · Central ARPYNET</title>

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/collaboration-thread.css') }}?v={{ filemtime(public_path('central-assets/pages/collaboration-thread.css')) }}"
    >
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand">Central ARPYNET</div>
        <x-operational-nav active="collaboration" />
    </div>

    <a class="back" href="{{ route('collaboration.index') }}">← Volver a colaboración</a>

    <section class="hero">
        <span class="badge">{{ $subjectLabel }}</span>
        <h1>{{ $subjectTitle }}</h1>
        <div class="meta">
            {{ $subject->organization?->name ?? 'Empresa' }} ·
            conversación operativa #{{ $subject->getKey() }}
        </div>
    </section>

    @if (session('collaboration_success'))
        <div class="success">{{ session('collaboration_success') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($canWrite)
        <form
            class="composer"
            method="POST"
            action="{{ route('collaboration.store', [
                'type' => $type,
                'id' => $subject->getKey(),
            ]) }}"
        >
            @csrf

            <div class="field">
                <label for="body">Comentario</label>
                <textarea
                    id="body"
                    name="body"
                    maxlength="5000"
                    required
                    placeholder="Escribe contexto, avance, bloqueo o decisión…"
                >{{ old('body') }}</textarea>
            </div>

            <div class="field">
                <label for="mentions">Mencionar personas</label>
                <select id="mentions" name="mentions[]" multiple>
                    @foreach ($members as $member)
                        <option
                            value="{{ $member->id }}"
                            @selected(in_array(
                                $member->id,
                                old('mentions', []),
                            ))
                        >
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
                <div class="hint">
                    Opcional. Usa Ctrl/Cmd para seleccionar varias personas. Las menciones generan una notificación interna.
                </div>
            </div>

            <button
                class="submit"
                type="submit"
                data-busy-label="Publicando…"
            >
                Publicar comentario
            </button>
        </form>
    @else
        <div class="readonly">
            Tienes acceso de solo lectura en esta empresa. Puedes consultar la conversación, pero no publicar comentarios.
        </div>
    @endif

    <div class="thread">
        @forelse ($comments as $comment)
            <article class="comment" id="comentario-{{ $comment->id }}">
                <div class="comment-head">
                    <div class="author">{{ $comment->author?->name ?? 'Usuario' }}</div>
                    <div class="meta">{{ $comment->created_at->format('d/m/Y H:i') }}</div>
                </div>

                <div class="body">{{ $comment->body }}</div>

                @if ($comment->mentions->isNotEmpty())
                    <div class="mentions">
                        Menciones: {{ $comment->mentions->pluck('name')->join(', ') }}
                    </div>
                @endif
            </article>
        @empty
            <div class="empty">
                Aún no hay comentarios. Esta conversación está lista para comenzar.
            </div>
        @endforelse
    </div>
</div>

<x-operational-theme />
<x-operational-interactions />
</body>
</html>
