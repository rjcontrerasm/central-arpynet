/* Central ARPYNET 2.40.5 — operational shared interactions */

(() => {
        const install = () => {
            if (window.__centralOperationalInteractionsInstalled) {
                return;
            }

            window.__centralOperationalInteractionsInstalled = true;

            const forms = document.querySelectorAll(
                'form[method="POST"], form[method="post"]'
            );

            const restoreSubmittingForms = () => {
                forms.forEach((form) => {
                    if (form.dataset.submitting !== 'yes') {
                        return;
                    }

                    delete form.dataset.submitting;
                    form.removeAttribute('aria-busy');

                    form.querySelectorAll(
                        'button[data-original-html]'
                    ).forEach((button) => {
                        if (button.dataset.wasDisabled !== 'yes') {
                            button.disabled = false;
                        }

                        button.classList.remove('is-busy');
                        button.innerHTML = button.dataset.originalHtml;

                        delete button.dataset.originalHtml;
                        delete button.dataset.wasDisabled;
                    });
                });
            };

            forms.forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (form.dataset.submitting === 'yes') {
                        event.preventDefault();
                        return;
                    }

                    const confirmation = form.dataset.confirm;

                    if (
                        confirmation
                        && ! window.confirm(confirmation)
                    ) {
                        event.preventDefault();
                        return;
                    }

                    form.dataset.submitting = 'yes';
                    form.setAttribute('aria-busy', 'true');

                    const buttons = form.querySelectorAll(
                        'button[type="submit"]'
                    );

                    buttons.forEach((button) => {
                        button.dataset.originalHtml = button.innerHTML;
                        button.dataset.wasDisabled =
                            button.disabled ? 'yes' : 'no';

                        button.disabled = true;
                        button.classList.add('is-busy');

                        const label =
                            button.dataset.busyLabel
                            || 'Guardando…';

                        button.textContent = label;
                    });
                });
            });

            window.addEventListener(
                'pageshow',
                restoreSubmittingForms,
            );

            const menus = document.querySelectorAll(
                '[data-operational-nav] details'
            );

            menus.forEach((menu) => {
                menu.addEventListener('toggle', () => {
                    if (!menu.open) {
                        return;
                    }

                    menus.forEach((otherMenu) => {
                        if (
                            otherMenu !== menu
                            && otherMenu.open
                        ) {
                            otherMenu.removeAttribute('open');
                        }
                    });
                });
            });

            document.addEventListener('click', (event) => {
                menus.forEach((menu) => {
                    if (
                        menu.open
                        && ! menu.contains(event.target)
                    ) {
                        menu.removeAttribute('open');
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                menus.forEach((menu) => {
                    if (!menu.open) {
                        return;
                    }

                    menu.removeAttribute('open');
                    menu.querySelector('summary')?.focus();
                });
            });

            const undoToast = document.querySelector(
                '.global-undo-bar'
            );

            if (
                undoToast
                && undoToast.parentElement !== document.body
            ) {
                document.body.appendChild(undoToast);
            }

            if (undoToast) {
                document.body.classList.add(
                    'has-global-undo-toast',
                );

                const progressFill = undoToast.querySelector(
                    '.global-undo-progress-fill',
                );
                const expiresAt = undoToast.dataset.undoExpiresAt
                    ? Date.parse(undoToast.dataset.undoExpiresAt)
                    : Number.NaN;

                if (
                    progressFill
                    && Number.isFinite(expiresAt)
                ) {
                    const fullWindow = 10 * 60 * 1000;
                    const remaining = Math.max(
                        0,
                        expiresAt - Date.now(),
                    );
                    const ratio = Math.min(
                        1,
                        remaining / fullWindow,
                    );

                    progressFill.style.transition = 'none';
                    progressFill.style.transform =
                        `scaleX(${ratio})`;

                    window.requestAnimationFrame(() => {
                        window.requestAnimationFrame(() => {
                            progressFill.style.transition =
                                `transform ${remaining}ms linear`;
                            progressFill.style.transform =
                                'scaleX(0)';
                        });
                    });
                }
            }

            const homeUrl = '/mi-dia';

            document.querySelectorAll('.brand').forEach((brand) => {
                if (brand.tagName === 'A') {
                    return;
                }

                brand.setAttribute('role', 'link');
                brand.setAttribute('tabindex', '0');
                brand.setAttribute('aria-label', 'Ir a Mi día');
                brand.dataset.homeLink = 'true';

                const navigateHome = () => {
                    window.location.href = homeUrl;
                };

                brand.addEventListener('click', navigateHome);
                brand.addEventListener('keydown', (event) => {
                    if (
                        event.key === 'Enter'
                        || event.key === ' '
                    ) {
                        event.preventDefault();
                        navigateHome();
                    }
                });
            });

            const scrollTopButton = document.createElement('button');

            scrollTopButton.type = 'button';
            scrollTopButton.className = 'operational-scroll-top';
            scrollTopButton.setAttribute('aria-label', 'Volver arriba');
            scrollTopButton.setAttribute('title', 'Volver arriba');
            scrollTopButton.hidden = true;
            scrollTopButton.innerHTML = \`
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="m6 10 6-6 6 6"/>
                    <path d="M12 4v16"/>
                </svg>
            \`;

            document.body.appendChild(scrollTopButton);

            const refreshScrollTopButton = () => {
                const shouldShow =
                    window.scrollY > 520
                    && document.documentElement.scrollHeight
                        > window.innerHeight + 700;

                scrollTopButton.hidden = ! shouldShow;
                scrollTopButton.classList.toggle(
                    'is-visible',
                    shouldShow,
                );
            };

            scrollTopButton.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: window.matchMedia(
                        '(prefers-reduced-motion: reduce)'
                    ).matches
                        ? 'auto'
                        : 'smooth',
                });
            });

            window.addEventListener(
                'scroll',
                refreshScrollTopButton,
                { passive: true },
            );
            window.addEventListener(
                'resize',
                refreshScrollTopButton,
            );
            refreshScrollTopButton();

            const teamAccessSelects = document.querySelectorAll(
                'select[name="work_team_ids[]"][multiple]',
            );

            teamAccessSelects.forEach((teamSelect) => {
                const form = teamSelect.closest('form');
                const visibilitySelect = form?.querySelector(
                    'select[name="visibility_scope"]',
                );

                if (! visibilitySelect) {
                    return;
                }

                const hasSelectedTeam = () =>
                    Array.from(teamSelect.options)
                        .some((option) => option.selected);

                const refreshRequiredState = () => {
                    teamSelect.required =
                        visibilitySelect.value === 'teams';
                };

                const syncVisibilityFromTeams = () => {
                    visibilitySelect.value =
                        hasSelectedTeam()
                            ? 'teams'
                            : 'organization';

                    refreshRequiredState();
                };

                teamSelect.addEventListener(
                    'mousedown',
                    (event) => {
                        if (
                            event.button !== 0
                            || event.target.tagName !== 'OPTION'
                        ) {
                            return;
                        }

                        event.preventDefault();

                        event.target.selected =
                            ! event.target.selected;

                        teamSelect.focus();

                        teamSelect.dispatchEvent(
                            new Event(
                                'change',
                                { bubbles: true },
                            ),
                        );
                    },
                );

                teamSelect.addEventListener(
                    'change',
                    syncVisibilityFromTeams,
                );

                visibilitySelect.addEventListener(
                    'change',
                    () => {
                        if (
                            visibilitySelect.value
                            === 'organization'
                        ) {
                            Array.from(
                                teamSelect.options,
                            ).forEach((option) => {
                                option.selected = false;
                            });
                        }

                        refreshRequiredState();
                    },
                );

                if (hasSelectedTeam()) {
                    visibilitySelect.value = 'teams';
                }

                refreshRequiredState();
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