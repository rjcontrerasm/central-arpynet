import { test, expect } from '@playwright/test';
import { expectNoHorizontalOverflow } from './helpers.js';

test('Mi Día muestra foco operativo sin overflow', async ({ page }) => {
    await page.goto('/mi-dia');

    await expect(
        page.getByRole('heading', {
            name: 'Mi día',
        }),
    ).toBeVisible();

    await expect(
        page.getByText('Foco del día'),
    ).toBeVisible();

    await expect(
        page.getByText('E2E tarea crítica'),
    ).toBeVisible();

    await expectNoHorizontalOverflow(page);
});

test('búsqueda global encuentra módulos operativos', async ({ page }) => {
    await page.goto('/buscar?q=E2E');

    for (const label of [
        'E2E tarea crítica',
        'E2E proyecto',
        'E2E cliente',
        'E2E servicio',
        'E2E incidente',
    ]) {
        await expect(
            page.getByText(label, {
                exact: true,
            }).first(),
        ).toBeVisible();
    }

    await expectNoHorizontalOverflow(page);
});

test('captura rápida crea una tarea desde FRONT', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'Una escritura funcional basta; la geometría se cubre en los tres viewports.',
    );

    await page.goto('/captura');

    await page
        .locator('input[name="title"]')
        .fill('E2E captura Playwright');

    await page
        .getByRole('button', {
            name: 'Guardar tarea',
        })
        .click();

    await expect(page).toHaveURL(
        /\/captura/,
    );

    await expect(
        page.getByText(
            'Tarea registrada correctamente.',
            { exact: true },
        ),
    ).toBeVisible();

    await expect(
        page.getByText(
            'E2E captura Playwright',
            { exact: true },
        ).first(),
    ).toBeVisible();

    await expectNoHorizontalOverflow(page);
});

test('cliente compartido puede asociarse a dos empresas desde FRONT', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La escritura multiempresa se ejecuta una sola vez.',
    );

    await page.goto('/clientes');

    await page
        .getByRole('button', {
            name: 'Seleccionar todas',
        })
        .click();

    await page
        .locator('#name')
        .fill('E2E cliente compartido Playwright');

    await page
        .getByRole('button', {
            name: 'Crear cliente',
        })
        .click();

    await expect(
        page.getByText(
            'Cliente compartido creado.',
            { exact: true },
        ),
    ).toBeVisible();

    const selectedClient = page.locator('.client.selected');

    await expect(selectedClient).toContainText(
        'E2E cliente compartido Playwright',
    );
    await expect(selectedClient).toContainText(
        'ARPYNET E2E',
    );
    await expect(selectedClient).toContainText(
        'ARPYNET E2E Secundaria',
    );

    await expectNoHorizontalOverflow(page);
});

test('tarea recurrente diaria se crea desde FRONT', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La escritura de recurrencia se ejecuta una sola vez.',
    );

    await page.goto('/tareas-recurrentes/nueva');

    await page
        .locator('#title')
        .fill('E2E recurrencia diaria Playwright');

    await page
        .locator('#frequency')
        .selectOption('daily');

    await page
        .locator('#anchor_date')
        .fill('2026-09-25');

    await page
        .locator('#due_time')
        .fill('18:00');

    await page
        .getByRole('button', {
            name: 'Crear recurrencia',
        })
        .click();

    await expect(page).toHaveURL(
        /\/tareas-recurrentes\/\d+\/editar/,
    );

    await expect(
        page.getByText(
            'Tarea recurrente creada.',
            { exact: true },
        ),
    ).toBeVisible();

    await expect(
        page.locator('#title'),
    ).toHaveValue(
        'E2E recurrencia diaria Playwright',
    );

    await expect(
        page.locator('#frequency'),
    ).toHaveValue('daily');

    await expectNoHorizontalOverflow(page);
});

