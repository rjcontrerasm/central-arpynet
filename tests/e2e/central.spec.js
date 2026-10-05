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

test('Mi Día completa tarea en vivo con mini confetti y undo inferior', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La escritura y geometría del toast se validan una sola vez.',
    );

    await page.addInitScript(() => {
        window.__centralAudioFrequencies = [];

        class FakeAudioParam {
            constructor(kind) {
                this.kind = kind;
            }

            setValueAtTime(value) {
                if (this.kind === 'frequency') {
                    window.__centralAudioFrequencies.push(
                        value,
                    );
                }
            }

            exponentialRampToValueAtTime() {}
        }

        class FakeOscillator {
            constructor() {
                this.frequency =
                    new FakeAudioParam('frequency');
                this.type = 'sine';
            }

            connect() {}
            start() {}
            stop() {}
        }

        class FakeGain {
            constructor() {
                this.gain =
                    new FakeAudioParam('gain');
            }

            connect() {}
        }

        class FakeAudioContext {
            constructor() {
                this.state = 'running';
                this.currentTime = 0;
                this.destination = {};
            }

            createGain() {
                return new FakeGain();
            }

            createOscillator() {
                return new FakeOscillator();
            }

            resume() {
                return Promise.resolve();
            }
        }

        window.AudioContext = FakeAudioContext;
        window.webkitAudioContext = FakeAudioContext;
    });

    await page.goto('/mi-dia');

    const taskTitle = page.getByText(
        'E2E tarea crítica',
        { exact: true },
    ).first();

    await expect(taskTitle).toBeVisible();

    const card = taskTitle.locator('xpath=ancestor::*[contains(@class,"item")][1]');
    const done = card.getByRole('button', {
        name: '✓ Hecho',
        exact: true,
    });

    await card.scrollIntoViewIfNeeded();

    const beforeScroll = await page.evaluate(
        () => window.scrollY,
    );

    const completionResponse = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && response.url().includes('/mi-dia/tareas/')
            && response.url().includes('/accion')
        ),
    );

    await done.click();

    await expect.poll(
        () => page.evaluate(
            () => window.__centralAudioFrequencies,
        ),
    ).toEqual([740, 990, 1320]);

    const confetti = page.locator(
        '.daily-complete-confetti-portal',
    );

    await expect(confetti).toBeVisible();

    const confettiParent = await confetti.evaluate(
        (element) => element.parentElement?.tagName,
    );

    expect(confettiParent).toBe('BODY');

    const response = await completionResponse;

    expect(response.status()).toBe(200);
    expect(
        response.headers()['content-type'] || '',
    ).toContain('application/json');

    const payload = await response.json();

    expect(payload.ok).toBe(true);
    expect(payload.action).toBe('complete');
    expect(payload.undo?.id).toBeTruthy();

    await expect(card).toHaveClass(
        /daily-task-leaving/,
    );

    const toast = page.locator(
        '#daily-live-undo-toast',
    );

    await expect(toast).toBeVisible();
    await expect(
        toast.getByText('Deshacer', {
            exact: true,
        }),
    ).toBeVisible();

    await expect(
        toast.locator('.global-undo-title'),
    ).toHaveText('Tarea completada');

    await expect(
        toast.locator('.global-undo-detail'),
    ).toHaveText('Puedes deshacer la acción.');

    await expect(
        toast.locator('.global-undo-progress-fill'),
    ).toBeVisible();

    await expect(
        toast.locator('.global-undo-message'),
    ).not.toContainText('Error');

    const toastBox = await toast.boundingBox();
    const viewport = page.viewportSize();

    expect(toastBox).not.toBeNull();
    expect(viewport).not.toBeNull();
    expect(
        Math.abs(
            viewport.height
            - (toastBox.y + toastBox.height)
            - 20,
        ),
    ).toBeLessThanOrEqual(4);
    expect(
        Math.abs(
            viewport.width
            - (toastBox.x + toastBox.width)
            - 18,
        ),
    ).toBeLessThanOrEqual(4);

    const afterScroll = await page.evaluate(
        () => window.scrollY,
    );

    expect(
        Math.abs(afterScroll - beforeScroll),
    ).toBeLessThanOrEqual(4);

    const undoResponse = page.waitForResponse(
        (responseCandidate) => (
            responseCandidate.request().method() === 'POST'
            && new URL(
                responseCandidate.url(),
            ).pathname === '/deshacer'
        ),
    );

    await toast.getByRole('button', {
        name: /Deshacer/,
    }).click();

    expect((await undoResponse).status()).toBe(200);

    await expect.poll(
        () => page.evaluate(
            () => window.__centralAudioFrequencies,
        ),
    ).toEqual([740, 990, 1320, 560, 420]);

    await expect(
        toast.locator('.global-undo-title'),
    ).toHaveText('Acción deshecha.');

    const undoButton = toast.locator(
        '.global-undo-button',
    );

    await expect(undoButton).toBeHidden();

    const restoredToastBox =
        await toast.boundingBox();

    expect(restoredToastBox).not.toBeNull();
    expect(restoredToastBox.height)
        .toBeLessThan(toastBox.height);

    await expect(taskTitle).toBeVisible();

    await expect(toast).toBeHidden({
        timeout: 1800,
    });

    await expectNoHorizontalOverflow(page);
});

test('Mi Día cambia En curso en vivo y permite deshacer', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La acción inline se valida una sola vez.',
    );

    await page.goto(
        '/mi-dia?q=E2E%20reflow%20uno',
    );

    const title = page.getByText(
        'E2E reflow uno',
        { exact: true },
    ).first();

    await expect(title).toBeVisible();

    const card = title.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );
    const startForm = card.locator(
        'form[data-daily-action="start"]',
    );

    await expect(startForm).toBeVisible();

    const startResponse = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && response.url().includes('/mi-dia/tareas/')
            && response.url().includes('/accion')
        ),
    );

    await startForm.getByRole('button', {
        name: 'En curso',
        exact: true,
    }).click();

    expect((await startResponse).status()).toBe(200);

    await expect(
        card.locator('[data-daily-in-progress]'),
    ).toHaveText('En curso');
    await expect(startForm).toBeHidden();

    const toast = page.locator(
        '#daily-live-undo-toast',
    );

    await expect(toast).toBeVisible();
    await expect(
        toast.locator('.global-undo-title'),
    ).toHaveText('Tarea en curso');

    const undoResponse = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/deshacer'
        ),
    );

    await toast.getByRole('button', {
        name: /Deshacer/,
    }).click();

    expect((await undoResponse).status()).toBe(200);

    await expect(
        card.locator('[data-daily-in-progress]'),
    ).toHaveCount(0);
    await expect(startForm).toBeVisible();
    await expect(title).toBeVisible();

    await expectNoHorizontalOverflow(page);
});

test('Mi Día muestra prioridad crítica primero y con tratamiento distintivo', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La jerarquía visual crítica se valida una sola vez.',
    );

    await page.goto(
        '/mi-dia?q=E2E%20foco%20cr%C3%ADtico%20%C3%BAnico',
    );

    const criticalSection = page.locator(
        '#prioridad-critica',
    );
    const overdueSection = page.locator(
        '#vencidas',
    );
    const criticalCard = criticalSection.locator(
        '.daily-task-critical',
    ).first();

    await expect(criticalSection).toBeVisible();
    await expect(criticalCard).toBeVisible();
    await expect(
        criticalCard.locator('.pill.critical').first(),
    ).toBeVisible();

    const criticalBox = await criticalSection.boundingBox();
    const overdueBox = await overdueSection.boundingBox();

    expect(criticalBox).not.toBeNull();
    expect(overdueBox).not.toBeNull();
    expect(criticalBox.y).toBeLessThan(overdueBox.y);

    const leftBorder = await criticalCard.evaluate(
        (element) => (
            window.getComputedStyle(
                element,
                '::before',
            ).width
        ),
    );

    expect(leftBorder).toBe('4px');

    await expectNoHorizontalOverflow(page);
});

test('Mi Día oculta y restaura foco crítico cuando cambia en vivo', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La transición de foco se valida una sola vez.',
    );

    await page.goto(
        '/mi-dia?q=E2E%20foco%20cr%C3%ADtico%20%C3%BAnico',
    );

    const title = page.getByText(
        'E2E foco crítico único',
        { exact: true },
    ).first();

    await expect(title).toBeVisible();

    const card = title.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );
    const done = card.getByRole('button', {
        name: '✓ Hecho',
        exact: true,
    });
    const primary = page.locator(
        '[data-daily-focus-primary]',
    );
    const criticalSection = page.locator(
        '#prioridad-critica',
    );

    await expect(primary).toBeVisible();
    await expect(primary).toContainText('Ver críticas');
    await expect(criticalSection).toBeVisible();

    const completionResponse = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && response.url().includes('/mi-dia/tareas/')
            && response.url().includes('/accion')
        ),
    );

    await done.click();

    expect((await completionResponse).status()).toBe(200);

    const toast = page.locator(
        '#daily-live-undo-toast',
    );

    try {
        await expect(
            page.locator('[data-daily-stat="critical"]'),
        ).toHaveText('0');
        await expect(primary).toBeHidden();
        await expect(criticalSection).toBeHidden();
    } finally {
        if (await toast.isVisible().catch(() => false)) {
            const undoResponse = page.waitForResponse(
                (response) => (
                    response.request().method() === 'POST'
                    && new URL(response.url()).pathname === '/deshacer'
                ),
            );

            await toast.getByRole('button', {
                name: /Deshacer/,
            }).click();

            expect((await undoResponse).status()).toBe(200);
        }
    }

    await expect(
        page.locator('[data-daily-stat="critical"]'),
    ).toHaveText('1');
    await expect(primary).toBeVisible();
    await expect(primary).toContainText('Ver críticas');
    await expect(criticalSection).toBeVisible();
    await expect(title).toBeVisible();

    await expectNoHorizontalOverflow(page);
});

test('Mi Día reacomoda fichas con micro-rebote y deshacer estable', async (
    { page },
    testInfo,
) => {
    test.skip(
        ! ['desktop-1366', 'mobile-390'].includes(
            testInfo.project.name,
        ),
        'El reacomodo se valida en grilla desktop y lista móvil.',
    );

    await page.goto('/mi-dia');

    const title = page.getByText(
        'E2E reflow dos',
        { exact: true },
    ).first();
    const followingTitle = page.getByText(
        'E2E reflow tres',
        { exact: true },
    ).first();

    await expect(title).toBeVisible();
    await expect(followingTitle).toBeVisible();

    const card = title.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );
    const followingCard = followingTitle.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );
    const done = card.getByRole('button', {
        name: '✓ Hecho',
        exact: true,
    });

    const before = await followingCard.boundingBox();
    expect(before).not.toBeNull();

    const overdueStat = page.locator(
        '[data-daily-stat="overdue"]',
    );
    const overdueBefore = Number.parseInt(
        await overdueStat.textContent(),
        10,
    );

    expect(overdueBefore).toBeGreaterThan(0);

    const completionResponse = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && response.url().includes('/mi-dia/tareas/')
            && response.url().includes('/accion')
        ),
    );

    await done.click();

    const response = await completionResponse;
    expect(response.status()).toBe(200);

    await expect(overdueStat).toHaveText(
        String(overdueBefore - 1),
    );

    await expect(
        page.locator('[data-daily-focus-title]'),
    ).toContainText(
        String(overdueBefore - 1),
    );

    await page.waitForTimeout(760);

    await expect(card).toBeHidden();
    await expect(followingCard).toHaveClass(
        /daily-task-reflowing/,
    );

    const during = await followingCard.boundingBox();
    expect(during).not.toBeNull();

    const moved = (
        Math.abs(during.x - before.x) > 4
        || Math.abs(during.y - before.y) > 4
    );

    expect(moved).toBe(true);

    await expect(followingCard).not.toHaveClass(
        /daily-task-reflowing/,
        { timeout: 1500 },
    );

    const toast = page.locator(
        '#daily-live-undo-toast',
    );

    const undoResponse = page.waitForResponse(
        (undoResponseCandidate) => (
            undoResponseCandidate.request().method() === 'POST'
            && new URL(
                undoResponseCandidate.url(),
            ).pathname === '/deshacer'
        ),
    );

    await toast.getByRole('button', {
        name: /Deshacer/,
    }).click();

    const undoResult = await undoResponse;
    expect(undoResult.status()).toBe(200);

    await expect(overdueStat).toHaveText(
        String(overdueBefore),
    );

    await expect(
        page.locator('[data-daily-focus-title]'),
    ).toContainText(
        String(overdueBefore),
    );

    await expect(title).toBeVisible();

    const restoredCard = title.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );

    await expect(restoredCard).toHaveClass(
        /daily-task-restoring/,
    );

    await page.waitForTimeout(650);

    await expect(restoredCard).not.toHaveClass(
        /daily-task-restoring/,
    );
    await expect(title).toBeVisible();

    await expectNoHorizontalOverflow(page);
});

test('Mi Día permite deshacer antes de terminar la salida sin borrar la ficha restaurada', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'El caso de carrera se ejecuta una sola vez.',
    );

    await page.goto('/mi-dia');

    const title = page.getByText(
        'E2E reflow uno',
        { exact: true },
    ).first();

    await expect(title).toBeVisible();

    const card = title.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );

    const completionResponse = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && response.url().includes('/mi-dia/tareas/')
            && response.url().includes('/accion')
        ),
    );

    await card.getByRole('button', {
        name: '✓ Hecho',
        exact: true,
    }).click();

    await completionResponse;

    const toast = page.locator(
        '#daily-live-undo-toast',
    );
    await expect(toast).toBeVisible();

    const undoResponse = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/deshacer'
        ),
    );

    await toast.getByRole('button', {
        name: /Deshacer/,
    }).click();

    const undoResult = await undoResponse;
    expect(undoResult.status()).toBe(200);

    await expect(title).toBeVisible();

    // Espera más que el temporizador original de retiro (720 ms).
    // Si no se canceló correctamente, la ficha desaparecería aquí.
    await page.waitForTimeout(900);

    await expect(title).toBeVisible();
    await expect(card).not.toHaveClass(
        /daily-task-leaving/,
    );

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

