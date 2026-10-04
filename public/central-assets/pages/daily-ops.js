/* Central ARPYNET 2.44.0 — live task completion */

(() => {
    const install = () => {
        if (window.__centralDailyCompletionInstalled) {
            return;
        }

        window.__centralDailyCompletionInstalled = true;

        let lastCompletion = null;
        let completionStack = [];
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

        const taskCards = (parent) => (
            parent
                ? [...parent.children].filter(
                    (element) => element.classList?.contains('item'),
                )
                : []
        );

        const settleReflowAnimations = (parent) => {
            taskCards(parent).forEach((element) => {
                const animation =
                    element.__centralDailyReflowAnimation;

                if (! animation) {
                    return;
                }

                try {
                    animation.finish();
                } catch (error) {
                    // A cancelled animation has nothing left to settle.
                }

                animation.cancel();
                delete element.__centralDailyReflowAnimation;
            });
        };

        const captureTaskPositions = (parent) => {
            settleReflowAnimations(parent);

            return new Map(
                taskCards(parent).map((element) => [
                    element,
                    element.getBoundingClientRect(),
                ]),
            );
        };

        const animateTaskReflow = (
            parent,
            previousPositions,
        ) => {
            if (
                reducedMotion()
                || ! parent?.isConnected
                || ! previousPositions
            ) {
                return;
            }

            taskCards(parent).forEach((element) => {
                const before = previousPositions.get(element);

                if (! before) {
                    return;
                }

                const after = element.getBoundingClientRect();
                const deltaX = before.left - after.left;
                const deltaY = before.top - after.top;

                if (
                    Math.abs(deltaX) < 0.5
                    && Math.abs(deltaY) < 0.5
                ) {
                    return;
                }

                const settleY = deltaY > 0
                    ? -3
                    : (deltaY < 0 ? 3 : 0);

                const animation = element.animate(
                    [
                        {
                            transform:
                                `translate(${deltaX}px, ${deltaY}px)`,
                        },
                        {
                            offset: 0.78,
                            transform:
                                `translate(0, ${settleY}px)`,
                        },
                        {
                            transform: 'translate(0, 0)',
                        },
                    ],
                    {
                        duration: 460,
                        easing:
                            'cubic-bezier(.2, .82, .25, 1)',
                    },
                );

                element.__centralDailyReflowAnimation =
                    animation;

                animation.addEventListener(
                    'finish',
                    () => {
                        if (
                            element.__centralDailyReflowAnimation
                            === animation
                        ) {
                            delete element
                                .__centralDailyReflowAnimation;
                        }
                    },
                    { once: true },
                );
            });
        };

        const resetCompletedCard = (card) => {
            if (! card) {
                return;
            }

            card.classList.remove(
                'daily-task-completing',
                'daily-task-leaving',
            );
            card.style.removeProperty(
                '--daily-task-exit-height',
            );
        };

        const pulseRestoredCard = (card) => {
            if (! card || reducedMotion()) {
                return;
            }

            card.classList.remove(
                'daily-task-restoring',
            );
            void card.offsetWidth;
            card.classList.add(
                'daily-task-restoring',
            );

            window.setTimeout(() => {
                card.classList.remove(
                    'daily-task-restoring',
                );
            }, 560);
        };

        const csrfToken = (form) => (
            form.querySelector('input[name="_token"]')?.value
            || ''
        );

        const clearPreviousCompletion = () => {
            completionStack.forEach((entry) => {
                entry.placeholder?.remove();
            });

            completionStack = [];
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
                    <strong class="global-undo-title">
                        Tarea completada
                    </strong>
                    <span class="global-undo-detail">
                        Puedes deshacer la acción.
                    </span>
                    <span
                        class="global-undo-progress"
                        aria-hidden="true"
                    >
                        <span class="global-undo-progress-fill"></span>
                    </span>
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

                    const completed =
                        lastCompletion;
                    const {
                        card,
                        parent,
                        placeholder,
                        removeTimer,
                    } = completed;

                    if (removeTimer) {
                        window.clearTimeout(removeTimer);
                    }

                    if (
                        card
                        && parent?.isConnected
                        && placeholder?.isConnected
                    ) {
                        if (card.isConnected) {
                            card.classList.remove(
                                'daily-task-leaving',
                            );

                            window.setTimeout(() => {
                                resetCompletedCard(card);
                                pulseRestoredCard(card);
                            }, reducedMotion() ? 0 : 480);
                        } else {
                            const before =
                                captureTaskPositions(parent);

                            resetCompletedCard(card);
                            parent.insertBefore(
                                card,
                                placeholder,
                            );
                            placeholder.remove();

                            animateTaskReflow(
                                parent,
                                before,
                            );
                            pulseRestoredCard(card);
                        }
                    } else {
                        resetCompletedCard(card);
                    }

                    completionStack =
                        completionStack.filter(
                            (entry) =>
                                entry.undo?.id
                                !== completed.undo?.id,
                        );

                    window.clearTimeout(
                        undoExpiryTimer,
                    );

                    const nextUndo =
                        payload.next_undo || null;

                    if (nextUndo) {
                        const localNext =
                            [...completionStack]
                                .reverse()
                                .find(
                                    (entry) =>
                                        entry.undo?.id
                                        === nextUndo.id,
                                );

                        lastCompletion = localNext
                            || {
                                undo: nextUndo,
                                csrf: completed.csrf,
                                card: null,
                                parent: null,
                                placeholder: null,
                                external: true,
                            };

                        toast.classList.remove(
                            'global-undo-bar--restored',
                        );

                        toast.querySelector(
                            '.global-undo-title',
                        ).textContent =
                            nextUndo.label
                            || 'Acción anterior';

                        toast.querySelector(
                            '.global-undo-detail',
                        ).textContent =
                            'Puedes deshacer la acción anterior.';

                        animateUndoProgress(
                            toast,
                            nextUndo.expires_at,
                        );

                        scheduleUndoExpiry(
                            toast,
                            nextUndo,
                        );
                    } else {
                        toast.classList.add(
                            'global-undo-bar--restored',
                        );

                        toast.querySelector(
                            '.global-undo-title',
                        ).textContent =
                            payload.message
                            || 'Acción deshecha.';

                        toast.querySelector(
                            '.global-undo-detail',
                        ).textContent = '';

                        lastCompletion = null;

                        window.setTimeout(() => {
                            toast.hidden = true;
                            toast.classList.remove(
                                'global-undo-bar--restored',
                            );
                        }, 1800);
                    }
                } catch (error) {
                    toast.querySelector(
                        '.global-undo-title',
                    ).textContent = error.message
                        || 'No se pudo deshacer.';
                    toast.querySelector(
                        '.global-undo-detail',
                    ).textContent = '';
                } finally {
                    button.disabled = false;
                    button.classList.remove('is-busy');
                }
            });

            return toast;
        };

        const animateUndoProgress = (
            toast,
            expiresAt,
        ) => {
            const fill = toast.querySelector(
                '.global-undo-progress-fill',
            );

            if (! fill) {
                return;
            }

            const expiry = expiresAt
                ? Date.parse(expiresAt)
                : Number.NaN;

            if (! Number.isFinite(expiry)) {
                fill.style.transition = 'none';
                fill.style.transform = 'scaleX(1)';
                return;
            }

            const fullWindow = 10 * 60 * 1000;
            const remaining = Math.max(
                0,
                expiry - Date.now(),
            );
            const ratio = Math.min(
                1,
                remaining / fullWindow,
            );

            fill.style.transition = 'none';
            fill.style.transform =
                `scaleX(${ratio})`;

            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    fill.style.transition =
                        `transform ${remaining}ms linear`;
                    fill.style.transform = 'scaleX(0)';
                });
            });
        };

        const scheduleUndoExpiry = (
            toast,
            undo,
        ) => {
            window.clearTimeout(
                undoExpiryTimer,
            );

            const expiresAt = undo?.expires_at
                ? Date.parse(undo.expires_at)
                : Number.NaN;

            if (! Number.isFinite(expiresAt)) {
                return;
            }

            const delay = Math.max(
                0,
                expiresAt - Date.now(),
            );

            undoExpiryTimer = window.setTimeout(
                () => {
                    if (
                        lastCompletion?.undo?.id
                        !== undo.id
                    ) {
                        return;
                    }

                    toast.hidden = true;

                    completionStack =
                        completionStack.filter(
                            (entry) =>
                                entry.undo?.id
                                !== undo.id,
                        );

                    lastCompletion?.placeholder?.remove();
                    lastCompletion =
                        completionStack.at(-1)
                        || null;
                },
                delay,
            );
        };

        const showUndoToast = (
            undo,
            label,
            csrf,
            state,
        ) => {
            const toast = ensureToast();

            if (
                existingUndoToast
                && existingUndoToast !== toast
            ) {
                existingUndoToast.hidden = true;
            }

            toast.hidden = false;
            toast.classList.remove(
                'global-undo-bar--restored',
            );
            const title = toast.querySelector(
                '.global-undo-title',
            );
            const detail = toast.querySelector(
                '.global-undo-detail',
            );

            title.textContent = 'Tarea completada';
            detail.textContent = 'Puedes deshacer la acción.';
            animateUndoProgress(
                toast,
                undo?.expires_at,
            );

            lastCompletion = {
                ...state,
                undo,
                csrf,
            };

            completionStack = completionStack
                .filter(
                    (entry) =>
                        entry.undo?.id !== undo?.id,
                );

            completionStack.push(
                lastCompletion,
            );

            if (completionStack.length > 10) {
                const removed =
                    completionStack.splice(
                        0,
                        completionStack.length - 10,
                    );

                removed.forEach((entry) => {
                    entry.placeholder?.remove();
                });
            }

            scheduleUndoExpiry(
                toast,
                undo,
            );
        };

        const addConfetti = (button) => {
            const rect = button.getBoundingClientRect();
            const stage = document.createElement('span');

            stage.className =
                'daily-complete-confetti daily-complete-confetti-portal';
            stage.setAttribute('aria-hidden', 'true');
            stage.style.left =
                `${rect.left + rect.width / 2}px`;
            stage.style.top =
                `${rect.top + rect.height / 2}px`;

            for (let index = 0; index < 12; index += 1) {
                const particle = document.createElement('i');
                stage.appendChild(particle);
            }

            document.body.appendChild(stage);

            window.setTimeout(() => {
                stage.remove();
            }, 1100);
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

                    card.style.setProperty(
                        '--daily-task-exit-height',
                        `${card.getBoundingClientRect().height}px`,
                    );
                    card.classList.add(
                        'daily-task-completing',
                    );

                    if (! reducedMotion()) {
                        addConfetti(button);
                    }

                    if (reducedMotion()) {
                        card.classList.add(
                            'daily-task-leaving',
                        );
                    } else {
                        window.setTimeout(
                            () => {
                                card.classList.add(
                                    'daily-task-leaving',
                                );
                            },
                            280,
                        );
                    }

                    const removeDelay = reducedMotion()
                        ? 0
                        : 720;

                    const removeTimer = window.setTimeout(
                        () => {
                            if (! card.isConnected) {
                                return;
                            }

                            const before =
                                captureTaskPositions(parent);

                            card.remove();

                            animateTaskReflow(
                                parent,
                                before,
                            );
                        },
                        removeDelay,
                    );

                    try {
                        const formData = new FormData(form);
                        formData.set('_live', '1');

                        const actionUrl = form.getAttribute(
                            'action',
                        );

                        if (! actionUrl) {
                            throw new Error(
                                'No se encontró la URL de la acción.',
                            );
                        }

                        const response = await fetch(
                            actionUrl,
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
                                    removeTimer,
                                },
                            );
                        } else {
                            placeholder.remove();
                        }
                    } catch (error) {
                        window.clearTimeout(removeTimer);

                        const before = (
                            ! card.isConnected
                            && parent.isConnected
                            && placeholder.isConnected
                        )
                            ? captureTaskPositions(parent)
                            : null;

                        resetCompletedCard(card);

                        if (
                            ! card.isConnected
                            && parent.isConnected
                            && placeholder.isConnected
                        ) {
                            parent.insertBefore(
                                card,
                                placeholder,
                            );

                            animateTaskReflow(
                                parent,
                                before,
                            );
                            pulseRestoredCard(card);
                        }

                        placeholder.remove();

                        const toast = ensureToast();
                        toast.hidden = false;
                        toast.querySelector(
                            '.global-undo-title',
                        ).textContent = error.message
                            || 'No se pudo completar la tarea.';
                        toast.querySelector(
                            '.global-undo-detail',
                        ).textContent = '';

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
