@once
<x-operational-polish />

<style>
    :where(
        button,
        input,
        select,
        textarea,
        summary,
        a,
        [role="button"]
    ):focus-visible {
        outline: 3px solid rgba(96, 165, 250, .48);
        outline-offset: 2px;
    }

    button,
    summary,
    a,
    [role="button"] {
        -webkit-tap-highlight-color: transparent;
        touch-action: manipulation;
    }

    button[disabled],
    .is-busy {
        cursor: wait !important;
        opacity: .72;
    }

    [data-operational-card] {
        transition:
            transform 140ms ease,
            border-color 140ms ease,
            box-shadow 140ms ease;
    }

    @media (hover: hover) and (pointer: fine) {
        [data-operational-card]:hover {
            transform: translateY(-1px);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
        }
    }

    @media (forced-colors: active) {
        :where(
            button,
            input,
            select,
            textarea,
            summary,
            a,
            [role="button"]
        ):focus-visible {
            outline: 2px solid Highlight;
        }
    }
</style>

<script>
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
</script>
@endonce
