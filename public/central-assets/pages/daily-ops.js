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
        let undoRestoreHideTimer = null;
        let audioContext = null;

        const primeFeedbackAudio = () => {
            try {
                const AudioContextClass =
                    window.AudioContext
                    || window.webkitAudioContext;

                if (! AudioContextClass) {
                    return null;
                }

                if (! audioContext) {
                    audioContext =
                        new AudioContextClass();
                }

                if (audioContext.state === 'suspended') {
                    audioContext.resume()
                        .catch(() => {});
                }

                return audioContext;
            } catch (error) {
                return null;
            }
        };

        const playFeedbackSound = (type) => {
            try {
                const context =
                    primeFeedbackAudio();

                if (! context) {
                    return;
                }

                const now = context.currentTime;
                const gain = context.createGain();

                gain.gain.setValueAtTime(
                    0.0001,
                    now,
                );
                gain.gain.exponentialRampToValueAtTime(
                    type === 'complete'
                        ? 0.028
                        : 0.025,
                    now + 0.008,
                );
                gain.gain.exponentialRampToValueAtTime(
                    0.0001,
                    now + (
                        type === 'complete'
                            ? 0.145
                            : 0.13
                    ),
                );
                gain.connect(context.destination);

                const notes = type === 'complete'
                    ? [
                        [740, 0, 0.055],
                        [990, 0.035, 0.095],
                        [1320, 0.075, 0.145],
                    ]
                    : [
                        [560, 0, 0.065],
                        [420, 0.045, 0.13],
                    ];

                notes.forEach(
                    ([frequency, offset, end]) => {
                        const oscillator =
                            context.createOscillator();

                        oscillator.type = 'sine';
                        oscillator.frequency.setValueAtTime(
                            frequency,
                            now + offset,
                        );
                        oscillator.connect(gain);
                        oscillator.start(
                            now + offset,
                        );
                        oscillator.stop(
                            now + end,
                        );
                    },
                );
            } catch (error) {
                // El feedback sonoro nunca debe afectar la acción principal.
            }
        };

        const liveHttpError = (
            response,
        ) => {
            const messages = {
                401: 'Tu sesión venció. Central recargará la página para volver a autenticarte.',
                419: 'La sesión de seguridad venció. Central recargará la página.',
            };

            if (! messages[response.status]) {
                return null;
            }

            const error = new Error(
                messages[response.status],
            );

            error.centralStatus = response.status;
            error.centralReload = true;

            return error;
        };

        const parseJsonResponse = async (response) => {
            const sessionError =
                liveHttpError(response);

            if (sessionError) {
                throw sessionError;
            }

            const contentType = response.headers.get(
                'content-type',
            ) || '';

            if (! contentType.includes('application/json')) {
                await response.text();

                throw new Error(
                    response.ok
                        ? 'El servidor devolvió una respuesta inesperada.'
                        : `Error ${response.status}: respuesta inesperada del servidor.`,
                );
            }

            return response.json();
        };

        const htmlCard = (html) => {
            const template = document.createElement(
                'template',
            );

            template.innerHTML = String(html || '').trim();

            return template.content.firstElementChild;
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

        const dailyStat = (key) => document.querySelector(
            `[data-daily-stat="${key}"]`,
        );

        const dailyStatValue = (key) => {
            const value = Number.parseInt(
                dailyStat(key)?.textContent?.trim() || '0',
                10,
            );

            return Number.isFinite(value)
                ? value
                : 0;
        };

        const setDailyStatValue = (key, value) => {
            const target = dailyStat(key);

            if (! target) {
                return;
            }

            target.textContent = String(
                Math.max(0, value),
            );
        };

        const setWaitingCount = (value) => {
            const normalized = Math.max(
                0,
                Number.parseInt(value || '0', 10) || 0,
            );

            setDailyStatValue(
                'waiting',
                normalized,
            );

            document.querySelectorAll(
                '[data-daily-waiting-count]',
            ).forEach((target) => {
                target.textContent = String(normalized);
            });
        };

        const renderDailyFocus = () => {
            const overdue = dailyStatValue('overdue');
            const critical = dailyStatValue('critical');
            const today = dailyStatValue('today');
            const waiting = dailyStatValue('waiting');
            const title = document.querySelector(
                '[data-daily-focus-title]',
            );
            const criticalMeta = document.querySelector(
                '[data-daily-focus-critical]',
            );
            const waitingMeta = document.querySelector(
                '[data-daily-focus-waiting]',
            );

            if (title) {
                if (overdue > 0) {
                    title.textContent =
                        `${overdue} ${overdue === 1
                            ? 'tarea vencida requiere'
                            : 'tareas vencidas requieren'} revisión`;
                } else if (critical > 0) {
                    title.textContent =
                        `${critical} ${critical === 1
                            ? 'tarea crítica requiere'
                            : 'tareas críticas requieren'} atención inmediata`;
                } else if (today > 0) {
                    title.textContent =
                        `${today} ${today === 1
                            ? 'tarea para resolver'
                            : 'tareas para resolver'} hoy`;
                } else {
                    title.textContent =
                        'Sin urgencias en tu bandeja';
                }
            }

            if (criticalMeta) {
                criticalMeta.textContent = String(critical);
            }

            if (waitingMeta) {
                waitingMeta.textContent = String(waiting);
            }

            const primaryAction = document.querySelector(
                '[data-daily-focus-primary]',
            );
            const primaryLabel = document.querySelector(
                '[data-daily-focus-primary-label]',
            );

            if (primaryAction && primaryLabel) {
                let key = null;
                let label = '';

                if (overdue > 0) {
                    key = 'overdue';
                    label = 'Ver vencidas';
                } else if (critical > 0) {
                    key = 'critical';
                    label = 'Ver críticas';
                } else if (today > 0) {
                    key = 'today';
                    label = 'Ver tareas de hoy';
                }

                if (key) {
                    const href = primaryAction.dataset[
                        `href${key.charAt(0).toUpperCase()}${key.slice(1)}`
                    ];

                    if (href) {
                        primaryAction.setAttribute(
                            'href',
                            href,
                        );
                    }

                    primaryLabel.textContent = label;
                    primaryAction.hidden = false;
                } else {
                    primaryAction.hidden = true;
                    primaryAction.removeAttribute('href');
                }
            }

            const overdueLink = document.querySelector(
                '[data-daily-overdue-link]',
            );
            if (overdueLink) {
                overdueLink.hidden = overdue <= 0;
                overdueLink.textContent = `Ver las ${overdue}`;
            }
        };

        const adjustDailySummary = (card, delta) => {
            if (! card) {
                return;
            }

            if (card.dataset.dailyOverdue === '1') {
                setDailyStatValue(
                    'overdue',
                    dailyStatValue('overdue') + delta,
                );
            }

            const band = card.dataset.dailyPriorityBand;

            if (
                band
                && ['critical', 'today', 'week', 'planned']
                    .includes(band)
            ) {
                setDailyStatValue(
                    band,
                    dailyStatValue(band) + delta,
                );
            }

            renderDailyFocus();
        };

        const transitionDailySummary = (
            card,
            presentation,
        ) => {
            adjustDailySummary(card, -1);

            card.dataset.dailyOverdue =
                presentation.overdue ? '1' : '0';
            card.dataset.dailyPriorityBand =
                presentation.priority_band || 'planned';
            card.dataset.dailyPriorityScore =
                String(presentation.priority_score ?? 0);

            adjustDailySummary(card, 1);
        };

        const updateDatePresentation = (
            card,
            presentation,
        ) => {
            const dueDate = card.querySelector(
                '[data-daily-due-date]',
            );
            const pills = card.querySelector(
                '[data-daily-pills]',
            );
            const priorityPill = card.querySelector(
                '[data-daily-priority-pill]',
            );
            let overduePill = card.querySelector(
                '[data-daily-overdue-pill]',
            );

            if (dueDate) {
                dueDate.textContent =
                    presentation.due_date || 'sin fecha';
            }

            if (presentation.overdue) {
                if (! overduePill && pills) {
                    overduePill =
                        document.createElement('span');
                    overduePill.className = 'pill overdue';
                    overduePill.dataset.dailyOverduePill = '1';
                    overduePill.textContent = 'Vencida';
                    pills.prepend(overduePill);
                }
            } else {
                overduePill?.remove();
            }

            if (priorityPill) {
                priorityPill.className =
                    `pill ${presentation.priority_band}`;
                priorityPill.textContent =
                    `${presentation.priority_label} · ${presentation.priority_score}`;
            }

            card.classList.toggle(
                'daily-task-critical',
                presentation.priority_band === 'critical',
            );

            const todayForm = card.querySelector(
                'form[data-daily-action="today"]',
            );

            if (todayForm) {
                todayForm.hidden =
                    Boolean(presentation.due_today);
            }
        };

        const matchesPriorityFilter = (
            filter,
            presentation,
        ) => {
            if (! filter) {
                return true;
            }

            if (filter === 'overdue') {
                return Boolean(presentation.overdue);
            }

            return presentation.priority_band === filter;
        };

        const destinationList = (destination) => (
            destination
                ? document.querySelector(
                    `#${destination} > .list`,
                )
                : null
        );

        const insertCardByPriority = (
            parent,
            card,
        ) => {
            if (! parent) {
                return;
            }

            const score = Number.parseInt(
                card.dataset.dailyPriorityScore || '0',
                10,
            );
            const before = taskCards(parent).find(
                (candidate) => (
                    candidate !== card
                    && Number.parseInt(
                        candidate.dataset.dailyPriorityScore || '0',
                        10,
                    ) < score
                ),
            );

            if (before) {
                parent.insertBefore(card, before);
            } else {
                parent.appendChild(card);
            }
        };

        const syncDailyEmptyState = (parent) => {
            if (! parent?.isConnected) {
                return;
            }

            const message =
                parent.dataset?.dailyEmptyMessage;

            if (! message) {
                return;
            }

            const hasCards = taskCards(parent).length > 0;
            const emptyStates = [
                ...parent.querySelectorAll('.empty'),
            ];
            const section = parent.closest('.section');

            if (section?.dataset.dailyHideWhenEmpty === '1') {
                section.hidden = ! hasCards;
            }

            if (hasCards) {
                emptyStates.forEach(
                    (empty) => empty.remove(),
                );
                return;
            }

            if (emptyStates.length > 0) {
                return;
            }

            const empty = document.createElement('div');
            empty.className = 'empty daily-live-empty';
            empty.textContent = message;
            parent.appendChild(empty);
        };

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
                element.classList.remove(
                    'daily-task-reflowing',
                );
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

                element.classList.add(
                    'daily-task-reflowing',
                );

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
                            element.classList.remove(
                                'daily-task-reflowing',
                            );
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

                primeFeedbackAudio();

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

                    playFeedbackSound('undo');

                    const completed =
                        lastCompletion;

                    if (completed.restoreAction) {
                        completed.restoreAction();
                    } else {
                        adjustDailySummary(
                            completed.card,
                            1,
                        );
                    }

                    const {
                        card,
                        parent,
                        placeholder,
                        leaveTimer,
                        removeTimer,
                    } = completed;

                    if (leaveTimer) {
                        window.clearTimeout(leaveTimer);
                    }

                    if (removeTimer) {
                        window.clearTimeout(removeTimer);
                    }

                    if (
                        ! completed.restoreAction
                        && card
                        && parent?.isConnected
                        && placeholder?.isConnected
                    ) {
                        if (card.isConnected) {
                            card.classList.remove(
                                'daily-task-leaving',
                            );
                            placeholder.remove();
                            syncDailyEmptyState(parent);

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
                            syncDailyEmptyState(parent);

                            animateTaskReflow(
                                parent,
                                before,
                            );
                            pulseRestoredCard(card);
                        }
                    } else if (! completed.restoreAction) {
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
                        button.hidden = false;

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
                        button.hidden = true;

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

                        window.clearTimeout(
                            undoRestoreHideTimer,
                        );

                        undoRestoreHideTimer =
                            window.setTimeout(() => {
                                toast.hidden = true;
                                toast.classList.remove(
                                    'global-undo-bar--restored',
                                );
                                undoRestoreHideTimer = null;
                            }, 1200);
                    }
                } catch (error) {
                    showLiveErrorToast(
                        error,
                        'No se pudo deshacer.',
                    );
                } finally {
                    button.disabled = false;
                    button.classList.remove('is-busy');
                }
            });

            return toast;
        };

        const showLiveErrorToast = (
            error,
            fallback,
        ) => {
            const toast = ensureToast();
            const previous = lastCompletion;
            const button = toast.querySelector(
                '.global-undo-button',
            );
            const progress = toast.querySelector(
                '.global-undo-progress-fill',
            );

            window.clearTimeout(
                undoRestoreHideTimer,
            );

            toast.hidden = false;
            toast.classList.remove(
                'global-undo-bar--restored',
                'daily-live-error-toast',
            );
            toast.classList.add(
                'daily-live-error-toast',
            );

            if (button) {
                button.hidden = true;
                button.disabled = false;
                button.classList.remove(
                    'is-busy',
                );
            }

            if (progress) {
                progress.style.transition = 'none';
                progress.style.transform = 'scaleX(0)';
            }

            toast.querySelector(
                '.global-undo-title',
            ).textContent =
                error?.message || fallback;

            toast.querySelector(
                '.global-undo-detail',
            ).textContent =
                error?.centralReload
                    ? 'Recargando…'
                    : '';

            undoRestoreHideTimer =
                window.setTimeout(() => {
                    toast.classList.remove(
                        'daily-live-error-toast',
                    );

                    if (error?.centralReload) {
                        window.location.reload();
                        return;
                    }

                    if (
                        previous?.undo
                        && lastCompletion?.undo?.id
                            === previous.undo.id
                    ) {
                        showUndoToast(
                            previous.undo,
                            previous.label
                                || previous.undo.label
                                || 'Acción anterior',
                            previous.csrf,
                            previous,
                        );
                        return;
                    }

                    toast.hidden = true;
                }, error?.centralReload ? 1300 : 2600);
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

            window.clearTimeout(
                undoRestoreHideTimer,
            );
            undoRestoreHideTimer = null;

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

            const undoButton = toast.querySelector(
                '.global-undo-button',
            );

            if (undoButton) {
                undoButton.hidden = false;
            }

            const title = toast.querySelector(
                '.global-undo-title',
            );
            const detail = toast.querySelector(
                '.global-undo-detail',
            );

            title.textContent =
                state.toastTitle || 'Tarea completada';
            detail.textContent =
                state.toastDetail
                || 'Puedes deshacer la acción.';
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

        const bindResumeForms = (root = document) => {
            root.querySelectorAll(
                '.resume-form',
            ).forEach((form) => {
                const waitingCard = form.closest('.item');

                if (! waitingCard) {
                    return;
                }

                if (form.dataset.dailyLiveResumeBound === '1') {
                    return;
                }

                form.dataset.dailyLiveResumeBound = '1';

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
                        const csrf = csrfToken(form);
                        const selectedPriority =
                            form.querySelector(
                                'input[name="priority"]',
                            )?.value || '';
                        const waitingParent =
                            waitingCard.parentNode;
                        const sourceMarker =
                            document.createComment(
                                'central-resume-origin',
                            );

                        waitingParent.insertBefore(
                            sourceMarker,
                            waitingCard,
                        );

                        form.dataset.submitting = 'yes';
                        button.disabled = true;

                        try {
                            const formData =
                                new FormData(form);
                            formData.set('_live', '1');

                            const response = await fetch(
                                form.getAttribute('action'),
                                {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        Accept: 'application/json',
                                        'X-Requested-With':
                                            'XMLHttpRequest',
                                        'X-Central-Live-Action':
                                            '1',
                                    },
                                    body: formData,
                                },
                            );

                            const payload =
                                await parseJsonResponse(
                                    response,
                                );

                            if (
                                ! response.ok
                                || ! payload.ok
                                || ! payload.presentation
                            ) {
                                throw new Error(
                                    payload.message
                                    || 'No se pudo reactivar la tarea.',
                                );
                            }

                            const presentation =
                                payload.presentation;
                            const activeCard =
                                htmlCard(
                                    payload.card_html,
                                );

                            if (! activeCard) {
                                throw new Error(
                                    'No se pudo construir la ficha reactivada.',
                                );
                            }

                            const waitingBefore =
                                captureTaskPositions(
                                    waitingParent,
                                );

                            waitingCard.remove();

                            animateTaskReflow(
                                waitingParent,
                                waitingBefore,
                            );
                            syncDailyEmptyState(
                                waitingParent,
                            );

                            if (! selectedPriority) {
                                setWaitingCount(
                                    dailyStatValue(
                                        'waiting',
                                    ) - 1,
                                );
                            }

                            const remainsInFilter =
                                matchesPriorityFilter(
                                    selectedPriority,
                                    presentation,
                                );
                            const countedInSummary =
                                ! selectedPriority
                                || remainsInFilter;

                            if (countedInSummary) {
                                adjustDailySummary(
                                    activeCard,
                                    1,
                                );
                            }
                            const destination =
                                remainsInFilter
                                    ? destinationList(
                                        presentation.destination,
                                    )
                                    : null;

                            if (destination) {
                                const destinationBefore =
                                    captureTaskPositions(
                                        destination,
                                    );

                                insertCardByPriority(
                                    destination,
                                    activeCard,
                                );
                                syncDailyEmptyState(
                                    destination,
                                );
                                animateTaskReflow(
                                    destination,
                                    destinationBefore,
                                );
                                pulseRestoredCard(
                                    activeCard,
                                );
                                bindLiveTaskForms(
                                    activeCard,
                                );
                            }

                            renderDailyFocus();

                            if (payload.undo) {
                                showUndoToast(
                                    payload.undo,
                                    payload.label,
                                    csrf,
                                    {
                                        mode: 'resume-waiting',
                                        toastTitle:
                                            'Tarea reactivada',
                                        card: activeCard,
                                        placeholder:
                                            sourceMarker,
                                        restoreAction: () => {
                                            const activeParent =
                                                activeCard
                                                    .parentNode;

                                            if (activeParent) {
                                                const activeBefore =
                                                    captureTaskPositions(
                                                        activeParent,
                                                    );

                                                activeCard.remove();

                                                animateTaskReflow(
                                                    activeParent,
                                                    activeBefore,
                                                );
                                                syncDailyEmptyState(
                                                    activeParent,
                                                );
                                            }

                                            if (countedInSummary) {
                                                adjustDailySummary(
                                                    activeCard,
                                                    -1,
                                                );
                                            }

                                            if (! selectedPriority) {
                                                setWaitingCount(
                                                    dailyStatValue(
                                                        'waiting',
                                                    ) + 1,
                                                );
                                            }

                                            const waitingBeforeUndo =
                                                captureTaskPositions(
                                                    waitingParent,
                                                );

                                            waitingParent.insertBefore(
                                                waitingCard,
                                                sourceMarker,
                                            );
                                            sourceMarker.remove();

                                            animateTaskReflow(
                                                waitingParent,
                                                waitingBeforeUndo,
                                            );
                                            syncDailyEmptyState(
                                                waitingParent,
                                            );
                                            renderDailyFocus();
                                            pulseRestoredCard(
                                                waitingCard,
                                            );
                                        },
                                    },
                                );
                            } else {
                                sourceMarker.remove();
                            }
                        } catch (error) {
                            sourceMarker.remove();

                            showLiveErrorToast(
                                error,
                                'No se pudo reactivar la tarea.',
                            );
                        } finally {
                            delete form.dataset.submitting;
                            button.disabled = false;
                        }
                    },
                    { capture: true },
                );
            });
        };

        const bindWaitingForms = (root = document) => {
        root.querySelectorAll(
            '.waiting-form',
        ).forEach((form) => {
            const card = form.closest('.item');

            if (! card) {
                return;
            }

            if (form.dataset.dailyLiveWaitingBound === '1') {
                return;
            }

            form.dataset.dailyLiveWaitingBound = '1';

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
                    const csrf = csrfToken(form);
                    const selectedPriority =
                        form.querySelector(
                            'input[name="priority"]',
                        )?.value || '';
                    const sourceParent = card.parentNode;
                    const sourceMarker =
                        document.createComment(
                            'central-waiting-origin',
                        );

                    sourceParent.insertBefore(
                        sourceMarker,
                        card,
                    );

                    form.dataset.submitting = 'yes';
                    button.disabled = true;

                    try {
                        const formData = new FormData(form);
                        formData.set('_live', '1');

                        const response = await fetch(
                            form.getAttribute('action'),
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
                                || 'No se pudo poner la tarea en espera.',
                            );
                        }

                        const sourceBefore =
                            captureTaskPositions(
                                sourceParent,
                            );

                        adjustDailySummary(
                            card,
                            -1,
                        );
                        if (! selectedPriority) {
                            setWaitingCount(
                                dailyStatValue('waiting') + 1,
                            );
                        }
                        renderDailyFocus();

                        card.remove();

                        animateTaskReflow(
                            sourceParent,
                            sourceBefore,
                        );
                        syncDailyEmptyState(sourceParent);

                        let waitingCard = null;
                        const waitingParent =
                            selectedPriority
                                ? null
                                : destinationList(
                                    'en-espera',
                                );

                        if (waitingParent) {
                            waitingCard = htmlCard(
                                payload.waiting?.card_html,
                            );

                            if (! waitingCard) {
                                throw new Error(
                                    'No se pudo construir la ficha En espera.',
                                );
                            }

                            const waitingBefore =
                                captureTaskPositions(
                                    waitingParent,
                                );

                            waitingParent.appendChild(
                                waitingCard,
                            );
                            syncDailyEmptyState(
                                waitingParent,
                            );
                            animateTaskReflow(
                                waitingParent,
                                waitingBefore,
                            );
                            pulseRestoredCard(
                                waitingCard,
                            );
                            bindResumeForms(
                                waitingCard,
                            );
                        }

                        if (payload.undo) {
                            showUndoToast(
                                payload.undo,
                                payload.label,
                                csrf,
                                {
                                    mode: 'waiting',
                                    toastTitle:
                                        'Tarea en espera',
                                    card,
                                    placeholder:
                                        sourceMarker,
                                    restoreAction: () => {
                                        const waitingParentNow =
                                            waitingCard
                                                ?.parentNode;

                                        if (waitingCard) {
                                            const waitingBefore =
                                                waitingParentNow
                                                    ? captureTaskPositions(
                                                        waitingParentNow,
                                                    )
                                                    : null;

                                            waitingCard.remove();

                                            if (
                                                waitingParentNow
                                                && waitingBefore
                                            ) {
                                                animateTaskReflow(
                                                    waitingParentNow,
                                                    waitingBefore,
                                                );
                                                syncDailyEmptyState(
                                                    waitingParentNow,
                                                );
                                            }
                                        }

                                        if (! selectedPriority) {
                                            setWaitingCount(
                                                dailyStatValue(
                                                    'waiting',
                                                ) - 1,
                                            );
                                        }
                                        adjustDailySummary(
                                            card,
                                            1,
                                        );

                                        const originBefore =
                                            captureTaskPositions(
                                                sourceParent,
                                            );

                                        sourceParent.insertBefore(
                                            card,
                                            sourceMarker,
                                        );
                                        sourceMarker.remove();

                                        animateTaskReflow(
                                            sourceParent,
                                            originBefore,
                                        );
                                        syncDailyEmptyState(
                                            sourceParent,
                                        );
                                        renderDailyFocus();
                                        pulseRestoredCard(
                                            card,
                                        );
                                    },
                                },
                            );
                        } else {
                            sourceMarker.remove();
                        }
                    } catch (error) {
                        sourceMarker.remove();

                        showLiveErrorToast(
                            error,
                            'No se pudo poner la tarea en espera.',
                        );
                    } finally {
                        delete form.dataset.submitting;
                        button.disabled = false;
                    }
                },
                { capture: true },
            );
        });

        };

        const bindDateActionForms = (root = document) => {
        root.querySelectorAll(
            '.action-form input[name="action"][value="today"],'
            + ' .action-form input[name="action"][value="tomorrow"],'
            + ' .action-form input[name="action"][value="next_week"]',
        ).forEach((input) => {
            const form = input.closest('form');
            const card = form?.closest('.item');

            if (! form || ! card) {
                return;
            }

            if (form.dataset.dailyLiveDateBound === '1') {
                return;
            }

            form.dataset.dailyLiveDateBound = '1';

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
                    const csrf = csrfToken(form);
                    const selectedPriority =
                        form.querySelector(
                            'input[name="priority"]',
                        )?.value || '';
                    const sourceParent = card.parentNode;
                    const sourceMarker =
                        document.createComment(
                            'central-date-action-origin',
                        );
                    const snapshot = {
                        overdue:
                            card.dataset.dailyOverdue,
                        band:
                            card.dataset.dailyPriorityBand,
                        score:
                            card.dataset.dailyPriorityScore,
                        dueDate:
                            card.querySelector(
                                '[data-daily-due-date]',
                            )?.textContent?.trim() || '',
                        priorityClass:
                            card.querySelector(
                                '[data-daily-priority-pill]',
                            )?.className || '',
                        priorityText:
                            card.querySelector(
                                '[data-daily-priority-pill]',
                            )?.textContent?.trim() || '',
                        critical:
                            card.classList.contains(
                                'daily-task-critical',
                            ),
                        todayHidden:
                            card.querySelector(
                                'form[data-daily-action="today"]',
                            )?.hidden || false,
                        hadOverduePill:
                            Boolean(
                                card.querySelector(
                                    '[data-daily-overdue-pill]',
                                ),
                            ),
                    };

                    sourceParent.insertBefore(
                        sourceMarker,
                        card,
                    );

                    form.dataset.submitting = 'yes';
                    button.disabled = true;

                    try {
                        const formData = new FormData(form);
                        formData.set('_live', '1');

                        const response = await fetch(
                            form.getAttribute('action'),
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

                        if (
                            ! response.ok
                            || ! payload.ok
                            || ! payload.presentation
                        ) {
                            throw new Error(
                                payload.message
                                || 'No se pudo mover la tarea.',
                            );
                        }

                        const presentation =
                            payload.presentation;
                        const remainsInFilter =
                            matchesPriorityFilter(
                                selectedPriority,
                                presentation,
                            );
                        const targetParent =
                            remainsInFilter
                                ? destinationList(
                                    presentation.destination,
                                )
                                : null;

                        if (
                            remainsInFilter
                            && ! targetParent
                        ) {
                            window.location.reload();
                            return;
                        }

                        const sourceBefore =
                            captureTaskPositions(
                                sourceParent,
                            );
                        const targetBefore = (
                            targetParent
                            && targetParent !== sourceParent
                        )
                            ? captureTaskPositions(
                                targetParent,
                            )
                            : sourceBefore;

                        if (selectedPriority) {
                            adjustDailySummary(
                                card,
                                -1,
                            );

                            card.dataset.dailyOverdue =
                                presentation.overdue
                                    ? '1'
                                    : '0';
                            card.dataset.dailyPriorityBand =
                                presentation.priority_band
                                || 'planned';
                            card.dataset.dailyPriorityScore =
                                String(
                                    presentation.priority_score
                                    ?? 0,
                                );

                            if (remainsInFilter) {
                                adjustDailySummary(
                                    card,
                                    1,
                                );
                            } else {
                                renderDailyFocus();
                            }
                        } else {
                            transitionDailySummary(
                                card,
                                presentation,
                            );
                        }

                        updateDatePresentation(
                            card,
                            presentation,
                        );

                        if (remainsInFilter) {
                            insertCardByPriority(
                                targetParent,
                                card,
                            );
                        } else {
                            card.remove();
                        }

                        animateTaskReflow(
                            sourceParent,
                            sourceBefore,
                        );

                        if (
                            targetParent
                            && targetParent !== sourceParent
                        ) {
                            animateTaskReflow(
                                targetParent,
                                targetBefore,
                            );
                        }

                        syncDailyEmptyState(sourceParent);

                        if (targetParent) {
                            syncDailyEmptyState(
                                targetParent,
                            );
                        }

                        if (card.isConnected) {
                            pulseRestoredCard(card);
                        }

                        if (payload.undo) {
                            const titleByAction = {
                                today: 'Movida a hoy',
                                tomorrow: 'Movida a mañana',
                                next_week:
                                    'Movida una semana',
                            };

                            showUndoToast(
                                payload.undo,
                                payload.label,
                                csrf,
                                {
                                    mode: 'move',
                                    toastTitle:
                                        titleByAction[
                                            payload.action
                                        ]
                                        || 'Fecha actualizada',
                                    card,
                                    placeholder:
                                        sourceMarker,
                                    restoreAction: () => {
                                        const currentParent =
                                            card.parentNode;
                                        const currentBefore =
                                            currentParent
                                                ? captureTaskPositions(
                                                    currentParent,
                                                )
                                                : null;
                                        const originBefore =
                                            sourceParent
                                                === currentParent
                                                ? currentBefore
                                                : captureTaskPositions(
                                                    sourceParent,
                                                );

                                        if (card.isConnected) {
                                            adjustDailySummary(
                                                card,
                                                -1,
                                            );
                                        }

                                        card.dataset.dailyOverdue =
                                            snapshot.overdue;
                                        card.dataset.dailyPriorityBand =
                                            snapshot.band;
                                        card.dataset.dailyPriorityScore =
                                            snapshot.score;

                                        const dueDate =
                                            card.querySelector(
                                                '[data-daily-due-date]',
                                            );
                                        const priorityPill =
                                            card.querySelector(
                                                '[data-daily-priority-pill]',
                                            );
                                        const pills =
                                            card.querySelector(
                                                '[data-daily-pills]',
                                            );
                                        let overduePill =
                                            card.querySelector(
                                                '[data-daily-overdue-pill]',
                                            );

                                        if (dueDate) {
                                            dueDate.textContent =
                                                snapshot.dueDate;
                                        }

                                        if (priorityPill) {
                                            priorityPill.className =
                                                snapshot.priorityClass;
                                            priorityPill.textContent =
                                                snapshot.priorityText;
                                        }

                                        card.classList.toggle(
                                            'daily-task-critical',
                                            snapshot.critical,
                                        );

                                        if (
                                            snapshot.hadOverduePill
                                            && ! overduePill
                                            && pills
                                        ) {
                                            overduePill =
                                                document.createElement(
                                                    'span',
                                                );
                                            overduePill.className =
                                                'pill overdue';
                                            overduePill.dataset
                                                .dailyOverduePill =
                                                '1';
                                            overduePill.textContent =
                                                'Vencida';
                                            pills.prepend(
                                                overduePill,
                                            );
                                        } else if (
                                            ! snapshot.hadOverduePill
                                        ) {
                                            overduePill?.remove();
                                        }

                                        const todayForm =
                                            card.querySelector(
                                                'form[data-daily-action="today"]',
                                            );

                                        if (todayForm) {
                                            todayForm.hidden =
                                                snapshot.todayHidden;
                                        }

                                        adjustDailySummary(
                                            card,
                                            1,
                                        );

                                        sourceParent.insertBefore(
                                            card,
                                            sourceMarker,
                                        );
                                        sourceMarker.remove();

                                        if (currentParent) {
                                            animateTaskReflow(
                                                currentParent,
                                                currentBefore,
                                            );
                                        }

                                        if (
                                            sourceParent
                                            !== currentParent
                                        ) {
                                            animateTaskReflow(
                                                sourceParent,
                                                originBefore,
                                            );
                                        }

                                        if (currentParent) {
                                            syncDailyEmptyState(
                                                currentParent,
                                            );
                                        }
                                        syncDailyEmptyState(
                                            sourceParent,
                                        );
                                        pulseRestoredCard(card);
                                    },
                                },
                            );
                        } else {
                            sourceMarker.remove();
                        }
                    } catch (error) {
                        sourceMarker.remove();

                        showLiveErrorToast(
                            error,
                            'No se pudo mover la tarea.',
                        );
                    } finally {
                        delete form.dataset.submitting;
                        button.disabled = false;
                    }
                },
                { capture: true },
            );
        });

        };

        const bindStartActionForms = (root = document) => {
        root.querySelectorAll(
            '.action-form input[name="action"][value="start"]',
        ).forEach((input) => {
            const form = input.closest('form');
            const card = form?.closest('.item');

            if (! form || ! card) {
                return;
            }

            if (form.dataset.dailyLiveStartBound === '1') {
                return;
            }

            form.dataset.dailyLiveStartBound = '1';

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
                    const pills = card.querySelector(
                        '[data-daily-pills]',
                    );
                    const csrf = csrfToken(form);

                    form.dataset.submitting = 'yes';
                    button.disabled = true;

                    try {
                        const formData = new FormData(form);
                        formData.set('_live', '1');

                        const response = await fetch(
                            form.getAttribute('action'),
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
                                || 'No se pudo iniciar la tarea.',
                            );
                        }

                        let progressPill = card.querySelector(
                            '[data-daily-in-progress]',
                        );

                        if (! progressPill && pills) {
                            progressPill =
                                document.createElement('span');
                            progressPill.className =
                                'pill today';
                            progressPill.dataset.dailyInProgress =
                                '1';
                            progressPill.textContent = 'En curso';
                            pills.appendChild(progressPill);
                        }

                        form.hidden = true;

                        if (payload.undo) {
                            showUndoToast(
                                payload.undo,
                                payload.label,
                                csrf,
                                {
                                    mode: 'inline',
                                    toastTitle: 'Tarea en curso',
                                    card,
                                    restoreAction: () => {
                                        card.querySelector(
                                            '[data-daily-in-progress]',
                                        )?.remove();
                                        form.hidden = false;
                                        pulseRestoredCard(card);
                                    },
                                },
                            );
                        }
                    } catch (error) {
                        showLiveErrorToast(
                            error,
                            'No se pudo iniciar la tarea.',
                        );
                    } finally {
                        delete form.dataset.submitting;
                        button.disabled = false;
                    }
                },
                { capture: true },
            );
        });

        };

        const bindCompleteActionForms = (root = document) => {
        root.querySelectorAll(
            '.action-form input[name="action"][value="complete"]',
        ).forEach((input) => {
            const form = input.closest('form');
            const card = form?.closest('.item');

            if (! form || ! card) {
                return;
            }

            if (form.dataset.dailyLiveCompleteBound === '1') {
                return;
            }

            form.dataset.dailyLiveCompleteBound = '1';

            if (card.dataset.dailyLiveComplete === 'reload') {
                return;
            }

            form.dataset.liveComplete = 'true';

            const followUpDialog = card.querySelector(
                '[data-follow-up-dialog]',
            );
            const followUpMode = form.querySelector(
                '[data-follow-up-mode]',
            );
            const followUpDueDate = form.querySelector(
                '[data-follow-up-due-date]',
            );

            if (
                followUpDialog
                && followUpDialog.dataset.bound !== '1'
            ) {
                followUpDialog.dataset.bound = '1';

                const datePicker =
                    followUpDialog.querySelector(
                        '[data-follow-up-date-picker]',
                    );

                const closeDialog = () => {
                    if (followUpDialog.open) {
                        followUpDialog.close();
                    }
                };

                followUpDialog.querySelectorAll(
                    '[data-follow-up-cancel]',
                ).forEach((button) => {
                    button.addEventListener(
                        'click',
                        closeDialog,
                    );
                });

                followUpDialog.querySelectorAll(
                    '[data-follow-up-choice]',
                ).forEach((button) => {
                    button.addEventListener(
                        'click',
                        () => {
                            const choice =
                                button.dataset
                                    .followUpChoice
                                || 'none';

                            if (
                                choice === 'custom'
                                && (
                                    ! datePicker
                                    || ! datePicker.value
                                )
                            ) {
                                datePicker?.reportValidity();
                                return;
                            }

                            if (followUpMode) {
                                followUpMode.value =
                                    choice;
                            }

                            if (followUpDueDate) {
                                followUpDueDate.value =
                                    choice === 'custom'
                                        ? (
                                            datePicker?.value
                                            || ''
                                        )
                                        : '';
                            }

                            form.dataset
                                .followUpConfirmed =
                                'yes';

                            closeDialog();
                            form.requestSubmit();
                        },
                    );
                });
            }

            form.addEventListener(
                'submit',
                async (event) => {
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    if (form.dataset.submitting === 'yes') {
                        return;
                    }

                    if (
                        form.dataset.hasNextAction === '1'
                        && form.dataset.followUpConfirmed
                            !== 'yes'
                        && followUpDialog
                    ) {
                        if (
                            typeof followUpDialog.showModal
                            === 'function'
                        ) {
                            followUpDialog.showModal();
                        } else {
                            followUpDialog.setAttribute(
                                'open',
                                '',
                            );
                        }

                        return;
                    }

                    delete form.dataset.followUpConfirmed;

                    primeFeedbackAudio();
                    playFeedbackSound('complete');

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

                    const leaveTimer = reducedMotion()
                        ? null
                        : window.setTimeout(
                            () => {
                                card.classList.add(
                                    'daily-task-leaving',
                                );
                            },
                            280,
                        );

                    if (reducedMotion()) {
                        card.classList.add(
                            'daily-task-leaving',
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
                            syncDailyEmptyState(parent);
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

                        adjustDailySummary(card, -1);

                        if (payload.undo) {
                            showUndoToast(
                                payload.undo,
                                payload.label,
                                csrf,
                                {
                                    card,
                                    parent,
                                    placeholder,
                                    leaveTimer,
                                    removeTimer,
                                    toastTitle:
                                        payload.follow_up
                                        && payload.follow_up.created
                                            ? 'Tarea completada · continuación creada'
                                            : 'Tarea completada',
                                    toastDetail:
                                        payload.follow_up
                                        && payload.follow_up.created
                                            ? (
                                                'Nueva tarea: '
                                                + payload.follow_up.title
                                                + (
                                                    payload.follow_up.due_date
                                                        ? ' · '
                                                            + payload.follow_up.due_date
                                                        : ''
                                                )
                                                + '. Puedes deshacer ambas.'
                                            )
                                            : 'Puedes deshacer la acción.',
                                },
                            );
                        } else {
                            placeholder.remove();
                        }
                    } catch (error) {
                        if (leaveTimer) {
                            window.clearTimeout(leaveTimer);
                        }
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

                        showLiveErrorToast(
                            error,
                            'No se pudo completar la tarea.',
                        );
                    } finally {
                        delete form.dataset.submitting;
                        button.disabled = false;
                    }
                },
                { capture: true },
            );
        });
        };

        const bindEditForms = (root = document) => {
            root.querySelectorAll(
                'form[data-daily-edit-form]',
            ).forEach((form) => {
                if (
                    form.dataset.dailyEditBound
                    === '1'
                ) {
                    return;
                }

                form.dataset.dailyEditBound = '1';

                const organization =
                    form.querySelector(
                        '[data-edit-organization]',
                    );
                const project =
                    form.querySelector(
                        '[data-edit-project]',
                    );
                const assignee =
                    form.querySelector(
                        '[data-edit-assignee]',
                    );
                const visibility =
                    form.querySelector(
                        '[data-edit-visibility]',
                    );
                const teams =
                    form.querySelector(
                        '[data-edit-teams]',
                    );
                const teamsWrapper =
                    form.querySelector(
                        '[data-edit-teams-wrapper]',
                    );
                const currentUserId = Number(
                    form.dataset.currentUserId || 0,
                );
                const contextWorkTeamId = Number(
                    form.querySelector(
                        'input[name="work_team"]',
                    )?.value || 0,
                );

                const idsFrom = (value) => (
                    String(value || '')
                        .split(',')
                        .map((id) => Number(id))
                        .filter((id) => id > 0)
                );

                const selectedTeamIds = () => (
                    teams
                        ? Array.from(
                            teams.selectedOptions,
                        ).map(
                            (option) =>
                                Number(option.value),
                        )
                        : []
                );

                const selectOnlyTeam = (teamId) => {
                    if (! teams) {
                        return;
                    }

                    Array.from(teams.options)
                        .forEach((option) => {
                            option.selected =
                                teamId > 0
                                && Number(option.value)
                                    === teamId;
                        });
                };

                const refreshProjects = () => {
                    if (! organization || ! project) {
                        return;
                    }

                    const organizationId = Number(
                        organization.value,
                    );
                    let selectedIsValid = false;

                    Array.from(project.options)
                        .forEach((option) => {
                            if (! option.value) {
                                option.hidden = false;
                                option.disabled = false;

                                if (option.selected) {
                                    selectedIsValid = true;
                                }

                                return;
                            }

                            const matches =
                                Number(
                                    option.dataset
                                        .organizationId
                                    || 0,
                                ) === organizationId;

                            option.hidden = ! matches;
                            option.disabled = ! matches;

                            if (
                                option.selected
                                && matches
                            ) {
                                selectedIsValid = true;
                            }
                        });

                    if (! selectedIsValid) {
                        project.value = '';
                    }
                };

                const assigneeAllowed = (option) => {
                    if (! option?.value) {
                        return false;
                    }

                    if (
                        visibility?.value === 'teams'
                    ) {
                        const selectedTeams =
                            selectedTeamIds();
                        const memberTeams = idsFrom(
                            option.dataset
                                .workTeamIds,
                        );

                        return selectedTeams.length > 0
                            && selectedTeams.some(
                                (teamId) =>
                                    memberTeams.includes(
                                        teamId,
                                    ),
                            );
                    }

                    const organizationId = Number(
                        organization?.value || 0,
                    );
                    const memberships = idsFrom(
                        option.dataset
                            .organizationIds,
                    );

                    return organizationId > 0
                        && memberships.includes(
                            organizationId,
                        );
                };

                const refreshAssignees = () => {
                    if (! assignee) {
                        return;
                    }

                    const options = Array.from(
                        assignee.options,
                    );

                    options.forEach((option) => {
                        const allowed =
                            assigneeAllowed(option);

                        option.hidden = ! allowed;
                        option.disabled = ! allowed;
                    });

                    if (
                        assigneeAllowed(
                            assignee
                                .selectedOptions[0],
                        )
                    ) {
                        return;
                    }

                    const preferred = options.find(
                        (option) => (
                            Number(option.value)
                                === currentUserId
                            && ! option.disabled
                        ),
                    );

                    const fallback =
                        preferred
                        || options.find(
                            (option) =>
                                ! option.disabled,
                        );

                    if (fallback) {
                        assignee.value =
                            fallback.value;
                    }
                };

                const ensureTeamSelection = () => {
                    if (
                        ! teams
                        || selectedTeamIds().length > 0
                    ) {
                        return;
                    }

                    const contextTeamExists =
                        contextWorkTeamId > 0
                        && Array.from(
                            teams.options,
                        ).some(
                            (option) => (
                                Number(option.value)
                                === contextWorkTeamId
                            ),
                        );

                    if (contextTeamExists) {
                        selectOnlyTeam(
                            contextWorkTeamId,
                        );
                        return;
                    }

                    const first = teams.options[0];

                    if (first) {
                        first.selected = true;
                    }
                };

                const refreshVisibility = () => {
                    if (! visibility || ! teams) {
                        return;
                    }

                    const usesTeams =
                        visibility.value === 'teams';

                    if (teamsWrapper) {
                        teamsWrapper.hidden =
                            ! usesTeams;
                    }

                    teams.disabled = ! usesTeams;
                    teams.required = usesTeams;

                    if (usesTeams) {
                        ensureTeamSelection();
                    } else {
                        selectOnlyTeam(0);
                    }

                    refreshAssignees();
                };

                organization?.addEventListener(
                    'change',
                    () => {
                        refreshProjects();

                        if (
                            visibility?.value
                            !== 'teams'
                        ) {
                            refreshAssignees();
                        }
                    },
                );

                visibility?.addEventListener(
                    'change',
                    refreshVisibility,
                );

                teams?.addEventListener(
                    'change',
                    () => {
                        if (
                            selectedTeamIds()
                                .length === 0
                        ) {
                            ensureTeamSelection();
                        }

                        refreshAssignees();
                    },
                );

                refreshProjects();
                refreshVisibility();
                refreshAssignees();
            });
        };

        const bindTaskEditDialogs = (root = document) => {
            root.querySelectorAll(
                '[data-task-edit-dialog]',
            ).forEach((dialog) => {
                if (
                    dialog.dataset.taskEditDialogBound
                    === '1'
                ) {
                    return;
                }

                dialog.dataset.taskEditDialogBound = '1';

                const card = dialog.closest(
                    '[data-operational-card]',
                );
                const trigger = card?.querySelector(
                    '[data-task-edit-open="'
                    + dialog.id
                    + '"]',
                );
                const form = dialog.querySelector(
                    'form[data-daily-edit-form]',
                );
                const closeButtons =
                    dialog.querySelectorAll(
                        '[data-task-edit-close]',
                    );
                const modalTaskName =
                    dialog.querySelector(
                        '[data-task-edit-task-name]',
                    );
                const titleInput =
                    form?.querySelector(
                        '[data-edit-title]',
                    );
                const cardTaskName =
                    card?.querySelector(
                        '.item-title',
                    )?.textContent?.trim() || '';

                let dirty = false;

                const syncDynamicFields = () => {
                    form?.querySelector(
                        '[data-edit-organization]',
                    )?.dispatchEvent(
                        new Event(
                            'change',
                            { bubbles: true },
                        ),
                    );

                    form?.querySelector(
                        '[data-edit-visibility]',
                    )?.dispatchEvent(
                        new Event(
                            'change',
                            { bubbles: true },
                        ),
                    );
                };

                const restoreForm = () => {
                    if (! form) {
                        return;
                    }

                    form.reset();

                    if (titleInput) {
                        titleInput.value =
                            cardTaskName;
                    }

                    syncDynamicFields();

                    if (modalTaskName) {
                        modalTaskName.textContent =
                            titleInput?.value?.trim()
                            || cardTaskName;
                    }

                    dirty = false;
                };

                const closeDialog = (
                    requireConfirmation = true,
                ) => {
                    if (
                        requireConfirmation
                        && dirty
                        && ! window.confirm(
                            '¿Descartar los cambios no guardados?',
                        )
                    ) {
                        return;
                    }

                    restoreForm();

                    if (dialog.open) {
                        dialog.close();
                    }

                    document.documentElement
                        .classList.remove(
                            'task-edit-modal-open',
                        );

                    trigger?.focus();
                };

                trigger?.addEventListener(
                    'click',
                    () => {
                        restoreForm();

                        if (modalTaskName) {
                            modalTaskName.textContent =
                                cardTaskName;
                        }

                        if (
                            typeof dialog.showModal
                            === 'function'
                        ) {
                            dialog.showModal();
                        } else {
                            dialog.setAttribute(
                                'open',
                                '',
                            );
                        }

                        document.documentElement
                            .classList.add(
                                'task-edit-modal-open',
                            );

                        window.requestAnimationFrame(
                            () => {
                                form?.querySelector(
                                    'textarea[name="description"]',
                                )?.focus();
                            },
                        );
                    },
                );

                closeButtons.forEach((button) => {
                    button.addEventListener(
                        'click',
                        () => closeDialog(true),
                    );
                });

                form?.addEventListener(
                    'input',
                    (event) => {
                        dirty = true;

                        if (
                            event.target === titleInput
                            && modalTaskName
                        ) {
                            modalTaskName.textContent =
                                titleInput.value.trim()
                                || 'Sin título';
                        }
                    },
                );

                form?.addEventListener(
                    'change',
                    () => {
                        dirty = true;
                    },
                );

                form?.addEventListener(
                    'submit',
                    () => {
                        dirty = false;
                    },
                );

                dialog.addEventListener(
                    'cancel',
                    (event) => {
                        event.preventDefault();
                        closeDialog(true);
                    },
                );

                dialog.addEventListener(
                    'click',
                    (event) => {
                        if (
                            event.target === dialog
                            && ! dirty
                        ) {
                            closeDialog(false);
                        }
                    },
                );

                dialog.addEventListener(
                    'close',
                    () => {
                        document.documentElement
                            .classList.remove(
                                'task-edit-modal-open',
                            );
                    },
                );
            });
        };

        const displayModeStorageKey =
            'central.daily.displayMode';

        const setStoredDisplayMode = (mode) => {
            try {
                window.localStorage.setItem(
                    displayModeStorageKey,
                    mode,
                );
            } catch (error) {
                // El modo sigue funcionando aunque el navegador
                // bloquee almacenamiento local.
            }
        };

        const storedDisplayMode = () => {
            try {
                return window.localStorage.getItem(
                    displayModeStorageKey,
                );
            } catch (error) {
                return null;
            }
        };

        const updateDisplayButtons = (mode) => {
            document.querySelectorAll(
                '[data-daily-display-mode]',
            ).forEach((button) => {
                const active =
                    button.dataset.dailyDisplayMode
                    === mode;

                button.classList.toggle(
                    'active',
                    active,
                );

                button.setAttribute(
                    'aria-pressed',
                    active ? 'true' : 'false',
                );
            });
        };

        const ensureDisplayUrl = (mode) => {
            const url = new URL(
                window.location.href,
            );

            if (mode === 'quick') {
                if (
                    url.searchParams.get('display')
                    === 'quick'
                ) {
                    return false;
                }

                url.searchParams.set(
                    'display',
                    'quick',
                );
            } else {
                if (
                    ! url.searchParams.has('display')
                ) {
                    return false;
                }

                url.searchParams.delete('display');
            }

            window.location.replace(
                url.toString(),
            );

            return true;
        };

        const applyDisplayMode = (
            mode,
            persist = true,
        ) => {
            const normalized =
                mode === 'quick'
                    ? 'quick'
                    : 'detailed';

            document.body.classList.toggle(
                'daily-mode-quick',
                normalized === 'quick',
            );

            updateDisplayButtons(normalized);

            if (persist) {
                setStoredDisplayMode(normalized);
            }

            return normalized;
        };

        const bindDisplayMode = () => {
            const url = new URL(
                window.location.href,
            );

            const urlMode =
                url.searchParams.get('display')
                    === 'quick'
                    ? 'quick'
                    : 'detailed';

            const remembered =
                storedDisplayMode();

            const initial =
                remembered === 'quick'
                || remembered === 'detailed'
                    ? remembered
                    : urlMode;

            if (
                initial !== urlMode
                && ensureDisplayUrl(initial)
            ) {
                return;
            }

            applyDisplayMode(
                urlMode,
                remembered == null,
            );

            document.querySelectorAll(
                '[data-daily-display-mode]',
            ).forEach((button) => {
                if (
                    button.dataset.dailyDisplayModeBound
                    === '1'
                ) {
                    return;
                }

                button.dataset.dailyDisplayModeBound =
                    '1';

                button.addEventListener(
                    'click',
                    () => {
                        const mode =
                            button.dataset
                                .dailyDisplayMode
                            === 'quick'
                                ? 'quick'
                                : 'detailed';

                        setStoredDisplayMode(mode);

                        if (! ensureDisplayUrl(mode)) {
                            applyDisplayMode(mode);
                        }
                    },
                );
            });
        };

        const bindQuickEditTriggers = (
            root = document,
        ) => {
            root.querySelectorAll(
                '[data-quick-open-edit]',
            ).forEach((button) => {
                if (
                    button.dataset.quickOpenEditBound
                    === '1'
                ) {
                    return;
                }

                button.dataset.quickOpenEditBound =
                    '1';

                button.addEventListener(
                    'click',
                    () => {
                        const card = button.closest(
                            '[data-operational-card]',
                        );

                        const dialogId =
                            button.dataset
                                .quickOpenEdit;

                        const original =
                            card?.querySelector(
                                '[data-task-edit-open="'
                                + dialogId
                                + '"]',
                            );

                        original?.click();
                    },
                );
            });
        };

        const closeQuickPriorityMenus = (
            except = null,
        ) => {
            document.querySelectorAll(
                '.task-quick-priority[open]',
            ).forEach((details) => {
                if (details === except) {
                    return;
                }

                details.open = false;

                details.closest(
                    '[data-daily-quick-container]',
                )?.classList.remove(
                    'quick-priority-active',
                );
            });
        };

        const bindQuickPriorityMenus = (
            root = document,
        ) => {
            root.querySelectorAll(
                '.task-quick-priority',
            ).forEach((details) => {
                if (
                    details.dataset
                        .quickPriorityBound
                    === '1'
                ) {
                    return;
                }

                details.dataset.quickPriorityBound =
                    '1';

                details.addEventListener(
                    'toggle',
                    () => {
                        const card = details.closest(
                            '[data-daily-quick-container]',
                        );

                        if (details.open) {
                            closeQuickPriorityMenus(
                                details,
                            );

                            card?.classList.add(
                                'quick-priority-active',
                            );
                        } else {
                            card?.classList.remove(
                                'quick-priority-active',
                            );
                        }
                    },
                );
            });

            if (
                document.documentElement.dataset
                    .quickPriorityDismissBound
                === '1'
            ) {
                return;
            }

            document.documentElement.dataset
                .quickPriorityDismissBound = '1';

            document.addEventListener(
                'click',
                (event) => {
                    if (
                        event.target.closest(
                            '.task-quick-priority',
                        )
                    ) {
                        return;
                    }

                    closeQuickPriorityMenus();
                },
            );

            document.addEventListener(
                'keydown',
                (event) => {
                    if (event.key !== 'Escape') {
                        return;
                    }

                    closeQuickPriorityMenus();
                },
            );
        };

        const bindQuickSectionToggles = () => {
            document.querySelectorAll(
                '.two-column > main > .section',
            ).forEach((section) => {
                const head =
                    section.querySelector(
                        ':scope > .section-head',
                    );

                if (
                    ! head
                    || head.querySelector(
                        '[data-daily-section-toggle]',
                    )
                ) {
                    return;
                }

                const button =
                    document.createElement(
                        'button',
                    );

                button.type = 'button';
                button.className =
                    'daily-section-toggle';
                button.dataset.dailySectionToggle =
                    '1';
                button.setAttribute(
                    'aria-expanded',
                    'true',
                );
                button.textContent = 'Ocultar';

                button.addEventListener(
                    'click',
                    () => {
                        const collapsed =
                            section.classList.toggle(
                                'daily-section-collapsed',
                            );

                        button.setAttribute(
                            'aria-expanded',
                            collapsed
                                ? 'false'
                                : 'true',
                        );

                        button.textContent =
                            collapsed
                                ? 'Mostrar'
                                : 'Ocultar';
                    },
                );

                head.appendChild(button);
            });
        };

        const bindLiveTaskForms = (root = document) => {
            bindResumeForms(root);
            bindWaitingForms(root);
            bindDateActionForms(root);
            bindStartActionForms(root);
            bindCompleteActionForms(root);
            bindEditForms(root);
            bindTaskEditDialogs(root);
            bindQuickEditTriggers(root);
            bindQuickPriorityMenus(root);
        };

        bindDisplayMode();
        bindQuickSectionToggles();
        bindLiveTaskForms();
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
