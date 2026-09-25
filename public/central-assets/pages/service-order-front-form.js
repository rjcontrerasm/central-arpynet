/* Central ARPYNET 2.39.2 — service-order-front-form */

(() => {
    const organization = document.getElementById('organization_id');
    const client = document.getElementById('client_id');
    const assignee = document.getElementById('assigned_to');
    if (!organization || organization.disabled) return;
    const filter = () => {
        const id = organization.value;
        client?.querySelectorAll('option[data-organizations]').forEach((option) => {
            option.disabled = !option.dataset.organizations.split(',').includes(id);
        });
        assignee?.querySelectorAll('option[data-organizations]').forEach((option) => {
            option.disabled = !option.dataset.organizations.split(',').includes(id);
        });
    };
    organization.addEventListener('change', filter);
    filter();
})();
