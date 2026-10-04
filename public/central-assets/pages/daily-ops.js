/* Central ARPYNET 2.44.0 — live task completion */

(() => {
    const install = () => {
        if (window.__centralDailyCompletionInstalled) {
            return;
        }

        window.__centralDailyCompletionInstalled = true;

        let lastCompletion = null;
        let undoExpiryTimer = null;

        const parseJsonResponse = async (response) => {
            const contentType = response.headers.get(
                'content-type',
            ) || '';

            if (! contentType.includes('application/json')) {
                const body = await response.text();

                throw new Error(
                    response.ok
                        ? 'El servidor devolvió una respuesta inesperada.'
                        : `Error ${response.status}: el servidor no devolvió JSON.`,
                );
            }

            return response.json();
        };

        const existingUndoToast = document.querySelector(
            '.global-undo-bar:not(#daily-live-undo-toast)',
        );

        if (
            existingUndoToast
            && existingUndoToast.parentElement !== document.body
        ) {
            document.body.appendChild(existingUndoToast);
        }

        if (existingUndoToast) {
            document.body.classList.add(
                'has-global-undo-toast',
            );
        }

        const reducedMotion = () => window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        const csrfToken = (form) => (
            form.querySelector('input[name="_token"]')?.value
            || ''
        );

        const clearPreviousCompletion = () => {
            if (! lastCompletion) {
                return;
            }

            lastCompletion.placeholder?.remove();
            lastCompletion = null;
        };

        const ensureToast = () => {
            let toast = document.getElementById(
                'daily-live-undo-toast',
            );

            if (toast) {
                return toast;
            }

            toast = document.createElement('div');
            toast.id = 'daily-live-undo-toast';
            toast.className = 'global-undo-bar daily-live-undo-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            toast.hidden = true;
            toast.innerHTML = `
                <span
                    class="global-undo-status-icon"
                    aria-hidden="true"
                >
                    <svg viewBox="0 0 24 24">
                        <path d="m5 12 4 4L19 6"/>
                    </svg>
                </span>
                <span class="global-undo-message">
                    Tarea completada.
                </span>
                <button
                    class="global-undo-button"
                    type="button"
                >
                    <svg
                        class="global-undo-button-icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M9 7 4 12l5 5"/>
                        <path d="M4 12h9a7 7 0 0 1 7 7"/>
                    </svg>
                    <span>Deshacer</span>
                </button>
            `;

            document.body.appendChild(toast);

            toast.querySelector(
                '.global-undo-button',
            )?.addEventListener('click', async () => {
                if (
                    ! lastCompletion
                    || ! lastCompletion.undo
                ) {
                    return;
                }

                const button = toast.querySelector(
                    '.global-undo-button',
                );

                button.disabled = true;
                button.classList.add('is-busy');

                try {
                    const response = await fetch(
                        lastCompletion.undo.url,
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': lastCompletion.csrf,
                                'X-Central-Live-Action': '1',
                            },
                            body: new URLSearchParams({
                                undo_id: String(
                                    lastCompletion.undo.id,
                                ),
                                _live: '1',
                            }),
                        },
                    );

                    const payload = await parseJsonResponse(
                        response,
                    );

                    if (! response.ok || ! payload.ok) {
                        throw new Error(
                            payload.message
                            || 'No se pudo deshacer.',
                        );
                    }

                    const {
                        card,
                        parent,
                        placeholder,
                    } = lastCompletion;

                    card.classList.remove(
                        'daily-task-completing',
                        'daily-task-leaving',
                    );

                    if (
                        parent.isConnected
                        && placeholder.isConnected
                    ) {
                        parent.insertBefore(
                            card,
                            placeholder,
                        );
                        placeholder.remove();
                    }

                    toast.classList.add(
                        'global-undo-bar--restored',
                    );
                    toast.querySelector(
                        '.global-undo-message',
                    ).textContent = payload.message
                        || 'Acción deshecha.';

                    window.setTimeout(() => {
                        toast.hidden = true;
                        toast.classList.remove(
                            'global-undo-bar--restored',
                        );
                    }, 1800);

                    window.clearTimeout(
                        undoExpiryTimer,
                    );
                    lastCompletion = null;
                } catch (error) {
                    toast.querySelector(
                        '.global-undo-message',
                    ).textContent = error.message
                        || 'No se pudo deshacer.';
                } finally {
                    button.disabled = false;
                    button.classList.remove('is-busy');
                }
            });

            return toast;
        };

        const showUndoToast = (
            undo,
            label,
            csrf,
            state,
        ) => {
            const toast = ensureToast();

            toast.hidden = false;
            toast.classList.remove(
                'global-undo-bar--restored',
            );
            toast.querySelector(
                '.global-undo-message',
            ).textContent = `${label || 'Tarea completada'}.`;

            lastCompletion = {
                ...state,
                undo,
                csrf,
            };

            window.clearTimeout(
                undoExpiryTimer,
            );

            const expiresAt = undo?.expires_at
                ? Date.parse(undo.expires_at)
                : Number.NaN;

            if (Number.isFinite(expiresAt)) {
                const delay = Math.max(
                    0,
                    expiresAt - Date.now(),
                );

                undoExpiryTimer = window.setTimeout(
                    () => {
                        if (
                            lastCompletion?.undo?.id
                            === undo.id
                        ) {
                            toast.hidden = true;
                            lastCompletion?.placeholder?.remove();
                            lastCompletion = null;
                        }
                    },
                    delay,
                );
            }
        };

        const addConfetti = (card) => {
            const stage = document.createElement('span');
            stage.className = 'daily-complete-confetti';
            stage.setAttribute('aria-hidden', 'true');

            for (let index = 0; index < 12; index += 1) {
                const particle = document.createElement('i');
                stage.appendChild(particle);
            }

            card.appendChild(stage);

            window.setTimeout(() => {
                stage.remove();
            }, 950);
        };

        document.querySelectorAll(
            '.action-form input[name="action"][value="complete"]',
        ).forEach((input) => {
            const form = input.closest('form');
            const card = form?.closest('.item');

            if (! form || ! card) {
                return;
            }

            form.dataset.liveComplete = 'true';

            form.addEventListener(
                'submit',
                async (event) => {
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    if (form.dataset.submitting === 'yes') {
                        return;
                    }

                    clearPreviousCompletion();

                    const button = form.querySelector(
                        'button[type="submit"]',
                    );
                    const parent = card.parentNode;
                    const placeholder = document.createComment(
                        'central-completed-task',
                    );
                    const csrf = csrfToken(form);

                    parent.insertBefore(
                        placeholder,
                        card,
                    );

                    form.dataset.submitting = 'yes';
                    button.disabled = true;

                    card.classList.add(
                        'daily-task-completing',
                    );

                    if (! reducedMotion()) {
                        addConfetti(card);
                    }

                    window.requestAnimationFrame(() => {
                        window.requestAnimationFrame(() => {
                            card.classList.add(
                                'daily-task-leaving',
                            );
                        });
                    });

                    const removeDelay = reducedMotion()
                        ? 0
                        : 900;

                    const removeTimer = window.setTimeout(
                        () => {
                            if (card.isConnected) {
                                card.remove();
                            }
                        },
                        removeDelay,
                    );

                    try {
                        const formData = new FormData(form);
                        formData.set('_live', '1');

                        const response = await fetch(
                            form.action,
                            {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-Central-Live-Action': '1',
                                },
                                body: formData,
                            },
                        );

                        const payload = await parseJsonResponse(
                            response,
                        );

                        if (! response.ok || ! payload.ok) {
                            throw new Error(
                                payload.message
                                || 'No se pudo completar la tarea.',
                            );
                        }

                        if (payload.undo) {
                            showUndoToast(
                                payload.undo,
                                payload.label,
                                csrf,
                                {
                                    card,
                                    parent,
                                    placeholder,
                                },
                            );
                        } else {
                            placeholder.remove();
                        }
                    } catch (error) {
                        window.clearTimeout(removeTimer);

                        card.classList.remove(
                            'daily-task-completing',
                            'daily-task-leaving',
                        );

                        if (
                            ! card.isConnected
                            && parent.isConnected
                            && placeholder.isConnected
                        ) {
                            parent.insertBefore(
                                card,
                                placeholder,
                            );
                        }

                        placeholder.remove();

                        const toast = ensureToast();
                        toast.hidden = false;
                        toast.querySelector(
                            '.global-undo-message',
                        ).textContent = error.message
                            || 'No se pudo completar la tarea.';

                        window.setTimeout(() => {
                            toast.hidden = true;
                        }, 2600);
                    } finally {
                        delete form.dataset.submitting;
                        button.disabled = false;
                    }
                },
                { capture: true },
            );
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            install,
            { once: true },
        );
    } else {
        install();
    }
})();
