/* Central ARPYNET 2.41.1 — service-order-front-form */

(() => {
    const organization = document.getElementById('organization_id');
    const client = document.getElementById('client_id');
    const assignee = document.getElementById('assigned_to');
    const workTeam = document.getElementById('work_team_id');
    const clientHelp = document.querySelector('[data-client-help]');
    const modal = document.querySelector('[data-client-modal]');
    const modalOpen = document.querySelector('[data-client-modal-open]');
    const modalCloses = document.querySelectorAll('[data-client-modal-close]');
    const clientForm = document.querySelector('[data-client-inline-form]');
    const modalOrganization = document.querySelector('[data-client-modal-organization]');
    const clientErrors = document.querySelector('[data-client-inline-errors]');

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

    const enabledClientCount = () => Array.from(
        client?.querySelectorAll('option[data-organizations]') || [],
    ).filter((option) => !option.disabled).length;

    const updateClientHelp = () => {
        if (!clientHelp) {
            return;
        }

        const count = enabledClientCount();

        clientHelp.textContent = count > 0
            ? 'Solo se muestran clientes asociados a la empresa seleccionada.'
            : 'No hay clientes en esta empresa. Puedes crear uno aquí sin salir de la orden.';
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

        if (
            client?.selectedOptions[0]
            && client.selectedOptions[0].disabled
        ) {
            client.value = '';
        }

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

        updateClientHelp();
    };

    const selectedOrganizationName = () => {
        const option = organization.selectedOptions[0];

        return option?.textContent?.trim() || 'empresa seleccionada';
    };

    const resetClientErrors = () => {
        if (!clientErrors) {
            return;
        }

        clientErrors.hidden = true;
        clientErrors.textContent = '';
    };

    const showClientErrors = (messages) => {
        if (!clientErrors) {
            return;
        }

        const values = Object.values(messages || {})
            .flat()
            .filter(Boolean);

        clientErrors.textContent = values.length > 0
            ? values.join(' ')
            : 'No se pudo crear el cliente. Revisa los datos e inténtalo nuevamente.';
        clientErrors.hidden = false;
    };

    const openClientModal = () => {
        if (!modal || !clientForm) {
            return;
        }

        resetClientErrors();

        if (modalOrganization) {
            modalOrganization.textContent = selectedOrganizationName();
        }

        modal.showModal();
        window.setTimeout(() => {
            document.getElementById('quick_client_name')?.focus();
        }, 0);
    };

    const closeClientModal = () => {
        if (!modal?.open) {
            return;
        }

        modal.close();
        resetClientErrors();
    };

    modalOpen?.addEventListener('click', openClientModal);

    modalCloses.forEach((button) => {
        button.addEventListener('click', closeClientModal);
    });

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeClientModal();
        }
    });

    clientForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        resetClientErrors();

        const submitButton = clientForm.querySelector('[type="submit"]');
        const originalLabel = submitButton?.textContent || '';

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = submitButton.dataset.busyLabel || 'Creando…';
        }

        const payload = new FormData(clientForm);
        payload.set('organization_id', organization.value);
        payload.set('is_active', '1');

        try {
            const response = await fetch(clientForm.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
                },
                body: payload,
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                showClientErrors(data.errors);
                return;
            }

            const created = data.client;

            if (!created?.id || !client) {
                showClientErrors();
                return;
            }

            const option = document.createElement('option');
            option.value = String(created.id);
            option.dataset.organizations = (created.organization_ids || [])
                .map(String)
                .join(',');
            option.textContent = created.organization_names?.length
                ? `${created.name} — ${created.organization_names.join(' · ')}`
                : created.name;
            option.selected = true;

            client.append(option);
            filter();
            client.value = String(created.id);
            clientForm.reset();
            closeClientModal();
            client.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (error) {
            showClientErrors();
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalLabel;
            }
        }
    });

    if (!organization.disabled) {
        organization.addEventListener('change', () => {
            filter();

            if (modalOrganization) {
                modalOrganization.textContent = selectedOrganizationName();
            }
        });
    }

    workTeam?.addEventListener('change', filter);
    filter();
})();
