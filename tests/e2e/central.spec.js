import { test, expect } from '@playwright/test';
import { expectNoHorizontalOverflow } from './helpers.js';

test('navegación operativa mantiene geometría de Mi día', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La geometría desktop se compara una sola vez.',
    );

    const paths = [
        '/mi-dia',
        '/proyectos',
        '/360',
        '/jarvis',
        '/clientes',
        '/decisiones',
        '/colaboracion',
    ];

    let referenceHeight = null;

    for (const path of paths) {
        await page.goto(path);

        const topbar = page.locator('.topbar').first();
        await expect(topbar).toBeVisible();

        const box = await topbar.boundingBox();
        expect(box).not.toBeNull();

        if (referenceHeight === null) {
            referenceHeight = box.height;
        } else {
            expect(
                Math.abs(box.height - referenceHeight),
                `Altura distinta en ${path}`,
            ).toBeLessThanOrEqual(1);
        }

        const more = page.locator('.op-nav-more > summary').first();
        await expect(more).toBeVisible();

        const moreBox = await more.boundingBox();
        expect(moreBox).not.toBeNull();
        expect(Math.abs(moreBox.height - 38)).toBeLessThanOrEqual(1);
    }

    await page.goto('/mi-dia');

    const active = page.locator('.op-nav-link.is-active').first();
    await expect(active).toBeVisible();

    const legacyUnderline = await active.evaluate((element) => (
        window.getComputedStyle(element, '::after').display
    ));

    expect(legacyUnderline).toBe('none');

    await expectNoHorizontalOverflow(page);
});

test('proyectos mantiene acciones rápidas con estilo local', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La regresión visual de proyectos se valida en desktop.',
    );

    await page.goto('/proyectos');

    const cssHref = await page
        .locator('link[href*="projects-ops.css"]')
        .getAttribute('href');

    expect(cssHref).toContain('projects-ops.css?v=');

    const css = await page.evaluate(async (href) => {
        const response = await fetch(href);
        return response.text();
    }, cssHref);

    expect(css).toContain('details.project-quick-actions');
    expect(css).toContain('.project-quick-form');
    expect(css).not.toContain('details.quick{');

    await expectNoHorizontalOverflow(page);
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

