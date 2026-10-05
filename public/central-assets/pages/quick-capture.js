/* Central ARPYNET 2.48.0 — contextual quick capture */

(() => {
    const form = document.querySelector(
        'form[data-context-work-team-id]',
    );

    if (! form) {
        return;
    }

    const radios = form.querySelectorAll(
        'input[name="due_mode"]',
    );
    const customDate = document.getElementById(
        'custom-date-wrapper',
    );
    const organizationSelect = form.querySelector(
        'select[name="organization_id"]',
    );
    const projectSelect = form.querySelector(
        'select[name="project_id"]',
    );
    const assigneeSelect = form.querySelector(
        'select[name="assigned_to"]',
    );
    const teamSelect = form.querySelector(
        'select[name="work_team_ids[]"]',
    );
    const visibilitySelect = form.querySelector(
        'select[name="visibility_scope"]',
    );
    const teamWrapper = document.getElementById(
        'work-teams-wrapper',
    );

    const contextWorkTeamId = Number(
        form.dataset.contextWorkTeamId || 0,
    );
    const currentUserId = Number(
        form.dataset.currentUserId || 0,
    );

    const idsFrom = (value) => (
        String(value || '')
            .split(',')
            .map((id) => Number(id))
            .filter((id) => id > 0)
    );

    const selectedTeamIds = () => (
        teamSelect
            ? Array.from(
                teamSelect.selectedOptions,
            ).map(
                (option) => Number(option.value),
            )
            : []
    );

    const refreshCustomDate = () => {
        const selected = form.querySelector(
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

    const refreshProjects = () => {
        if (! organizationSelect || ! projectSelect) {
            return;
        }

        const organizationId = Number(
            organizationSelect.value,
        );
        let selectedIsValid = false;

        Array.from(projectSelect.options)
            .forEach((option) => {
                if (! option.value) {
                    option.hidden = false;
                    option.disabled = false;

                    if (option.selected) {
                        selectedIsValid = true;
                    }

                    return;
                }

                const matches =
                    Number(
                        option.dataset.organizationId || 0,
                    ) === organizationId;

                option.hidden = ! matches;
                option.disabled = ! matches;

                if (option.selected && matches) {
                    selectedIsValid = true;
                }
            });

        if (! selectedIsValid) {
            projectSelect.value = '';
        }
    };

    const assigneeMatchesContext = (option) => {
        if (! option?.value) {
            return false;
        }

        if (
            visibilitySelect?.value === 'teams'
        ) {
            const teams = selectedTeamIds();
            const optionTeams = idsFrom(
                option.dataset.workTeamIds,
            );

            return teams.length > 0
                && teams.some(
                    (teamId) =>
                        optionTeams.includes(teamId),
                );
        }

        const organizationId = Number(
            organizationSelect?.value || 0,
        );
        const organizations = idsFrom(
            option.dataset.organizationIds,
        );

        return organizationId > 0
            && organizations.includes(
                organizationId,
            );
    };

    const refreshAssignees = () => {
        if (! assigneeSelect) {
            return;
        }

        const options = Array.from(
            assigneeSelect.options,
        );

        options.forEach((option) => {
            const allowed =
                assigneeMatchesContext(option);

            option.hidden = ! allowed;
            option.disabled = ! allowed;
        });

        if (
            assigneeMatchesContext(
                assigneeSelect.selectedOptions[0],
            )
        ) {
            return;
        }

        const preferred = options.find(
            (option) => (
                Number(option.value)
                    === currentUserId
                && ! option.disabled
            ),
        );
        const fallback = preferred
            || options.find(
                (option) => ! option.disabled,
            );

        if (fallback) {
            assigneeSelect.value =
                fallback.value;
        }
    };

    const selectOnlyTeam = (teamId) => {
        if (! teamSelect) {
            return;
        }

        Array.from(teamSelect.options)
            .forEach((option) => {
                option.selected =
                    teamId > 0
                    && Number(option.value)
                        === teamId;
            });
    };

    const ensureTeamSelection = () => {
        if (
            ! teamSelect
            || visibilitySelect?.value !== 'teams'
            || selectedTeamIds().length > 0
        ) {
            return;
        }

        const assigneeDefaultTeamId = Number(
            assigneeSelect
                ?.selectedOptions[0]
                ?.dataset.defaultWorkTeamId
                || 0,
        );
        const preferredTeamId =
            contextWorkTeamId
            || assigneeDefaultTeamId;

        const preferredExists =
            preferredTeamId > 0
            && Array.from(teamSelect.options)
                .some(
                    (option) => (
                        Number(option.value)
                        === preferredTeamId
                    ),
                );

        if (preferredExists) {
            selectOnlyTeam(preferredTeamId);
            return;
        }

        const first = teamSelect.options[0];

        if (first) {
            first.selected = true;
        }
    };

    const refreshTeamVisibility = () => {
        if (! visibilitySelect || ! teamSelect) {
            return;
        }

        const isTeamVisibility =
            visibilitySelect.value === 'teams';

        if (teamWrapper) {
            teamWrapper.hidden =
                ! isTeamVisibility;
        }

        teamSelect.disabled =
            ! isTeamVisibility;
        teamSelect.required =
            isTeamVisibility;

        if (isTeamVisibility) {
            ensureTeamSelection();
        } else {
            selectOnlyTeam(0);
        }
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

        if (defaultTeamId > 0) {
            visibilitySelect.value = 'teams';
            selectOnlyTeam(defaultTeamId);
        } else {
            visibilitySelect.value =
                'organization';
            selectOnlyTeam(0);
        }

        refreshTeamVisibility();
    };

    radios.forEach((radio) => {
        radio.addEventListener(
            'change',
            refreshCustomDate,
        );
    });

    organizationSelect?.addEventListener(
        'change',
        () => {
            refreshProjects();
            refreshAssignees();
        },
    );

    visibilitySelect?.addEventListener(
        'change',
        () => {
            refreshTeamVisibility();
            refreshAssignees();
        },
    );

    teamSelect?.addEventListener(
        'change',
        () => {
            if (
                selectedTeamIds().length > 0
                && visibilitySelect
            ) {
                visibilitySelect.value = 'teams';
            }

            refreshTeamVisibility();
            refreshAssignees();
        },
    );

    assigneeSelect?.addEventListener(
        'change',
        () => {
            applyAssigneeDefaultTeam();
            refreshAssignees();
        },
    );

    refreshCustomDate();
    refreshTeamVisibility();
    refreshProjects();
    refreshAssignees();
})();
