<style>
    :where(
        button,
        input,
        select,
        summary,
        a
    ):focus-visible {
        outline: 3px solid rgba(96, 165, 250, .45);
        outline-offset: 2px;
    }

    button,
    summary,
    a {
        -webkit-tap-highlight-color: transparent;
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
</style>

<script>
    (() => {
        const forms = document.querySelectorAll(
            'form[method="POST"], form[method="post"]'
        );

        forms.forEach((form) => {
            form.addEventListener('submit', () => {
                if (form.dataset.submitting === 'yes') {
                    return;
                }

                form.dataset.submitting = 'yes';
                form.setAttribute('aria-busy', 'true');

                const buttons = form.querySelectorAll(
                    'button[type="submit"]'
                );

                buttons.forEach((button) => {
                    button.dataset.originalLabel =
                        button.textContent.trim();

                    button.disabled = true;
                    button.classList.add('is-busy');

                    const label =
                        button.dataset.busyLabel
                        || 'Guardando…';

                    button.textContent = label;
                });
            });
        });

        const menus = document.querySelectorAll(
            '[data-operational-nav] details'
        );

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
                menu.removeAttribute('open');
            });
        });
    })();
</script>
