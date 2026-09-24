/* Central ARPYNET 2.39.1 — incident-360 */

document.querySelectorAll('[data-incident-create]').forEach((form) => {
    const organization = form.querySelector('[data-incident-organization]');
    if (!organization) return;

    const sync = () => {
        const organizationId = organization.value;
        form.querySelectorAll('[data-incident-scoped]').forEach((select) => {
            Array.from(select.options).forEach((option) => {
                const optionOrganization = option.dataset.org;
                const visible = !optionOrganization || optionOrganization === organizationId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible && option.selected) select.value = '';
            });
        });
    };

    organization.addEventListener('change', sync);
    sync();
});
