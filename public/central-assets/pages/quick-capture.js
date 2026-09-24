/* Central ARPYNET 2.39.1 — quick-capture */

const radios = document.querySelectorAll(
        'input[name="due_mode"]'
    );

    const customDate = document.getElementById(
        'custom-date-wrapper'
    );

    function refreshCustomDate() {
        const selected = document.querySelector(
            'input[name="due_mode"]:checked'
        );

        customDate.classList.toggle(
            'visible',
            selected && selected.value === 'custom'
        );
    }

    radios.forEach((radio) => {
        radio.addEventListener(
            'change',
            refreshCustomDate
        );
    });

    refreshCustomDate();
