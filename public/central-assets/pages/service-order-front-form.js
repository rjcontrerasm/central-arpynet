/* Central ARPYNET 2.41.0 — service-order-front-form */

(() => {
    const organization = document.getElementById('organization_id');
    const client = document.getElementById('client_id');
    const assignee = document.getElementById('assigned_to');
    const workTeam = document.getElementById('work_team_id');

    if (!organization) {
        return;
    }

    const includes = (csv, value) => {
        if (!value) {
            return true;
        }

        return (csv || '')
            .split(',')
            .filter(Boolean)
            .includes(value);
    };

    const filter = () => {
        const organizationId = organization.value;
        const workTeamId = workTeam?.value || '';

        client
            ?.querySelectorAll('option[data-organizations]')
            .forEach((option) => {
                option.disabled = !includes(
                    option.dataset.organizations,
                    organizationId,
                );
            });

        assignee
            ?.querySelectorAll('option[data-organizations]')
            .forEach((option) => {
                const organizationAllowed = includes(
                    option.dataset.organizations,
                    organizationId,
                );

                const teamAllowed = includes(
                    option.dataset.workTeams,
                    workTeamId,
                );

                option.disabled =
                    !organizationAllowed
                    || !teamAllowed;
            });
    };

    if (!organization.disabled) {
        organization.addEventListener('change', filter);
    }

    workTeam?.addEventListener('change', filter);
    filter();
})();
