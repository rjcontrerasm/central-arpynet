(() => {
    const form = document.querySelector('[data-task-convert]');
    const target = document.getElementById('conversion-target');
    const frequency = document.getElementById('conversion-frequency');
    const anchor = document.getElementById('conversion-anchor');

    if (! form || ! target || ! frequency || ! anchor) {
        return;
    }

    let suggestedAnchors = {};

    try {
        suggestedAnchors = JSON.parse(
            form.dataset.suggestedAnchors || '{}',
        );
    } catch {
        suggestedAnchors = {};
    }

    const groups = {
        service: document.getElementById('service-fields'),
        recurring: document.getElementById('recurring-fields'),
        waiting: document.getElementById('waiting-fields'),
    };

    const refresh = () => {
        Object.entries(groups).forEach(([key, element]) => {
            if (! element) {
                return;
            }

            element.hidden = target.value !== key;
        });
    };

    const refreshAnchor = () => {
        const suggested = suggestedAnchors[frequency.value];

        if (suggested) {
            anchor.value = suggested;
        }
    };

    target.addEventListener('change', refresh);
    frequency.addEventListener('change', refreshAnchor);
    refresh();
})();
