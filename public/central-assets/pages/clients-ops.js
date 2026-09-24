/* Central ARPYNET 2.39.1 — clients-ops */

(() => {
        document.querySelectorAll('[data-org-selector]').forEach((selector) => {
            const toggle = selector.querySelector('[data-org-toggle]');
            const group = selector.querySelector('[data-org-checks]');

            if (!toggle || !group) return;

            const boxes = () => Array.from(
                group.querySelectorAll('input[type="checkbox"][name="organization_ids[]"]'),
            );

            const syncToggle = () => {
                const currentBoxes = boxes();
                const allSelected = currentBoxes.length > 0
                    && currentBoxes.every((box) => box.checked);

                toggle.textContent = allSelected ? 'Limpiar' : 'Seleccionar todas';
                toggle.setAttribute('aria-pressed', allSelected ? 'true' : 'false');
            };

            toggle.addEventListener('click', () => {
                const currentBoxes = boxes();
                const shouldSelect = ! currentBoxes.every((box) => box.checked);

                currentBoxes.forEach((box) => {
                    box.checked = shouldSelect;
                });

                syncToggle();
            });

            group.addEventListener('change', syncToggle);
            syncToggle();
        });
    })();
