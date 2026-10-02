/* Central ARPYNET 2.42.5 — service-orders-ops */

(() => {
    const groups = document.querySelectorAll('[data-service-panels]');

    groups.forEach((group) => {
        const panels = Array.from(
            group.querySelectorAll('[data-service-panel]'),
        );

        panels.forEach((panel) => {
            panel.addEventListener('toggle', () => {
                if (!panel.open) {
                    return;
                }

                panels.forEach((other) => {
                    if (other !== panel) {
                        other.removeAttribute('open');
                    }
                });
            });
        });
    });

    document
        .querySelectorAll('[data-open-service-panel]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.dataset.openServicePanel;
                const panel = id
                    ? document.getElementById(id)
                    : null;

                if (!panel) {
                    return;
                }

                const group = panel.closest('[data-service-panels]');

                group
                    ?.querySelectorAll('[data-service-panel]')
                    .forEach((other) => {
                        if (other !== panel) {
                            other.removeAttribute('open');
                        }
                    });

                panel.setAttribute('open', '');
                panel.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });

                window.setTimeout(() => {
                    panel.querySelector(
                        'input[name="next_action"]',
                    )?.focus();
                }, 180);
            });
        });
})();
