/* Central ARPYNET 2.40.5 — quick-capture */

(() => {
    const radios = document.querySelectorAll(
        'input[name="due_mode"]',
    );

    const customDate = document.getElementById(
        'custom-date-wrapper',
    );

    const refreshCustomDate = () => {
        const selected = document.querySelector(
            'input[name="due_mode"]:checked',
        );

        if (! customDate) {
            return;
        }

        customDate.classList.toggle(
            'visible',
            selected?.value === 'custom',
        );
    };

    radios.forEach((radio) => {
        radio.addEventListener(
            'change',
            refreshCustomDate,
        );
    });

    refreshCustomDate();
})();
