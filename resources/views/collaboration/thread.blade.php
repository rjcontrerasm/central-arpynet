<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $subjectTitle }} · Colaboración · Central ARPYNET</title>

    <x-operational-theme />

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/collaboration-thread.css') }}?v={{ filemtime(public_path('central-assets/pages/collaboration-thread.css')) }}"
    >
</head>
<body>
<div class="shell collaboration-shell">
    <x-operational-page-header
        active="collaboration"
        title="Colaboración"
        subtitle="Conversación operativa y menciones internas."
    >
        <x-slot:actions>
            <a
                class="collaboration-back"
                href="{{ route('collaboration.index') }}"
            >
                ← Volver a colaboración
            </a>
        </x-slot:actions>
    </x-operational-page-header>

    <section class="collaboration-subject">
        <span class="collaboration-badge">
            {{ $subjectLabel }}
        </span>

        <h1>{{ $subjectTitle }}</h1>

        <div class="collaboration-subject-meta">
            {{ $subject->organization?->name ?? 'Empresa' }}
            · conversación operativa #{{ $subject->getKey() }}
        </div>
    </section>

    @if (session('collaboration_success'))
        <div class="collaboration-feedback success">
            {{ session('collaboration_success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="collaboration-feedback error">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($canWrite)
        @php
            $oldMentionIds = collect(old('mentions', []))
                ->map(fn ($id) => (int) $id)
                ->all();

            $mentionableMembers = $members->reject(
                fn ($member) =>
                    (int) $member->id
                    === (int) auth()->id(),
            );
        @endphp

        <form
            class="collaboration-composer"
            method="POST"
            action="{{ route('collaboration.store', [
                'type' => $type,
                'id' => $subject->getKey(),
            ]) }}"
        >
            @csrf

            <div class="collaboration-composer-head">
                <div>
                    <div class="collaboration-composer-title">
                        Escribe un comentario
                    </div>
                    <div class="collaboration-composer-subtitle">
                        Comparte un avance, bloqueo, contexto o decisión.
                    </div>
                </div>
            </div>

            <div class="collaboration-field">
                <label
                    class="collaboration-label"
                    for="body"
                >
                    Comentario
                </label>

                <textarea
                    class="collaboration-textarea"
                    id="body"
                    name="body"
                    maxlength="5000"
                    required
                    placeholder="Ej. Ya envié el sustento. Falta confirmar la fecha con el cliente."
                >{{ old('body') }}</textarea>
            </div>

            <div class="collaboration-field">
                <div class="collaboration-label">
                    Avisar a
                </div>

                @if ($mentionableMembers->isNotEmpty())
                    <div
                        class="collaboration-people"
                        role="group"
                        aria-label="Personas a notificar"
                    >
                        @foreach ($mentionableMembers as $member)
                            <label class="collaboration-person">
                                <input
                                    type="checkbox"
                                    name="mentions[]"
                                    value="{{ $member->id }}"
                                    @checked(in_array(
                                        (int) $member->id,
                                        $oldMentionIds,
                                        true,
                                    ))
                                >

                                <span class="collaboration-person-chip">
                                    <span
                                        class="collaboration-person-avatar"
                                        aria-hidden="true"
                                    >
                                        {{ mb_strtoupper(
                                            mb_substr(
                                                trim($member->name),
                                                0,
                                                1,
                                            ),
                                        ) }}
                                    </span>

                                    <span>{{ $member->name }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="collaboration-hint">
                        Opcional. Haz clic en una o varias personas para notificarles este comentario.
                    </div>
                @else
                    <div class="collaboration-hint">
                        No hay otras personas disponibles para notificar en esta conversación.
                    </div>
                @endif
            </div>

            <div class="collaboration-composer-footer">
                <div class="collaboration-hint">
                    Solo recibirán notificación las personas que selecciones.
                </div>

                <button
                    class="collaboration-submit"
                    type="submit"
                    data-busy-label="Publicando…"
                >
                    Publicar comentario
                </button>
            </div>
        </form>
    @else
        <div class="collaboration-readonly">
            Tienes acceso de solo lectura. Puedes consultar la conversación, pero no publicar comentarios.
        </div>
    @endif

    <section class="collaboration-thread">
        <div class="collaboration-thread-head">
            <h2 class="collaboration-thread-title">
                Conversación
            </h2>

            <div class="collaboration-thread-count">
                {{ $comments->count() }}
                {{ $comments->count() === 1 ? 'comentario' : 'comentarios' }}
            </div>
        </div>

        <div class="collaboration-comments">
            @forelse ($comments as $comment)
                <article
                    class="collaboration-comment"
                    id="comentario-{{ $comment->id }}"
                >
                    <div class="collaboration-comment-head">
                        <div class="collaboration-comment-author">
                            {{ $comment->author?->name ?? 'Usuario' }}
                        </div>

                        <div class="collaboration-comment-time">
                            {{ $comment->created_at->format('d/m/Y H:i') }}
                        </div>
                    </div>

                    <div class="collaboration-comment-body">
                        {{ $comment->body }}
                    </div>

                    @if ($comment->mentions->isNotEmpty())
                        <div class="collaboration-comment-mentions">
                            <span>Avisó a:</span>

                            @foreach ($comment->mentions as $mentioned)
                                <span class="collaboration-mention-pill">
                                    {{ $mentioned->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <div class="collaboration-empty">
                    Aún no hay comentarios. Esta conversación está lista para comenzar.
                </div>
            @endforelse
        </div>
    </section>
</div>

<x-operational-interactions />
</body>
</html>
