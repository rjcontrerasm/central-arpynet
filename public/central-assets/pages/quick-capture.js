/* Central ARPYNET 2.40.7 — quick-capture */

(() => {
    const radios = document.querySelectorAll(
        'input[name="due_mode"]',
    );

    const customDate = document.getElementById(
        'custom-date-wrapper',
    );

    const form = document.querySelector(
        'form[data-context-work-team-id]',
    );

    const assigneeSelect = form?.querySelector(
        'select[name="assigned_to"]',
    );

    const teamSelect = form?.querySelector(
        'select[name="work_team_ids[]"]',
    );

    const visibilitySelect = form?.querySelector(
        'select[name="visibility_scope"]',
    );

    const contextWorkTeamId = Number(
        form?.dataset.contextWorkTeamId || 0,
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

    const applyAssigneeDefaultTeam = () => {
        if (
            contextWorkTeamId > 0
            || ! assigneeSelect
            || ! teamSelect
            || ! visibilitySelect
        ) {
            return;
        }

        const selectedAssignee =
            assigneeSelect.selectedOptions[0];

        const defaultTeamId = Number(
            selectedAssignee
                ?.dataset.defaultWorkTeamId
                || 0,
        );

        Array.from(teamSelect.options)
            .forEach((option) => {
                option.selected =
                    defaultTeamId > 0
                    && Number(option.value)
                        === defaultTeamId;
            });

        visibilitySelect.value =
            defaultTeamId > 0
                ? 'teams'
                : 'organization';

        teamSelect.required =
            defaultTeamId > 0;
    };

    radios.forEach((radio) => {
        radio.addEventListener(
            'change',
            refreshCustomDate,
        );
    });

    assigneeSelect?.addEventListener(
        'change',
        applyAssigneeDefaultTeam,
    );

    refreshCustomDate();
})();
