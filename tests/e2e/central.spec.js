import { test, expect } from '@playwright/test';
import {
    expectNoHorizontalOverflow,
    login,
} from './helpers.js';

test.beforeEach(async ({ page }) => {
    await login(page);
});

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
        /\/mi-dia/,
    );

    await expect(
        page.getByText(
            'E2E captura Playwright',
            { exact: true },
        ).first(),
    ).toBeVisible();

    await expectNoHorizontalOverflow(page);
});
