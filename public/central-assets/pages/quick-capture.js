/* Central ARPYNET 2.40.1 — quick-capture */

(() => {
    const radios = document.querySelectorAll(
        'input[name="due_mode"]',
    );

    const customDate = document.getElementById(
        'custom-date-wrapper',
    );

    const visibility = document.getElementById(
        'visibility-scope',
    );

    const teamsWrapper = document.getElementById(
        'work-teams-wrapper',
    );

    const teamsSelect = teamsWrapper
        ?.querySelector('select[name="work_team_ids[]"]');

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

    const refreshTeamVisibility = () => {
        if (! visibility || ! teamsWrapper) {
            return;
        }

        const restricted =
            visibility.value === 'teams';

        teamsWrapper.hidden = ! restricted;

        if (teamsSelect) {
            teamsSelect.required = restricted;

            if (! restricted) {
                Array.from(
                    teamsSelect.options,
                ).forEach((option) => {
                    option.selected = false;
                });
            }
        }
    };

    radios.forEach((radio) => {
        radio.addEventListener(
            'change',
            refreshCustomDate,
        );
    });

    visibility?.addEventListener(
        'change',
        refreshTeamVisibility,
    );

    refreshCustomDate();
    refreshTeamVisibility();
})();
