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

test('Mi Día mueve Hoy, Mañana y +1 semana en vivo con Deshacer', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'Los movimientos de fecha se validan una sola vez.',
    );

    await page.goto(
        '/mi-dia?q=E2E%20reflow%20dos',
    );

    const title = page.getByText(
        'E2E reflow dos',
        { exact: true },
    ).first();

    await expect(title).toBeVisible();

    const card = title.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );
    const originalDue = (
        await card.locator(
            '[data-daily-due-date]',
        ).textContent()
    ).trim();

    await expect(
        card.locator('[data-daily-overdue-pill]'),
    ).toBeVisible();

    const runMove = async (
        action,
        buttonName,
        expectedSection,
        expectedToast,
    ) => {
        const actionForm = card.locator(
            `form[data-daily-action="${action}"]`,
        );

        await expect(actionForm).toBeVisible();

        const responsePromise = page.waitForResponse(
            (response) => (
                response.request().method() === 'POST'
                && response.url().includes('/mi-dia/tareas/')
                && response.url().includes('/accion')
            ),
        );

        await actionForm.getByRole('button', {
            name: buttonName,
            exact: true,
        }).click();

        const response = await responsePromise;
        expect(response.status()).toBe(200);

        const payload = await response.json();

        expect(
            payload.presentation.destination,
        ).toBe(expectedSection);

        await expect(
            page.locator(
                `#${expectedSection} > .list`,
            ).locator('.item', {
                hasText: 'E2E reflow dos',
            }),
        ).toBeVisible();

        await expect(
            card.locator('[data-daily-overdue-pill]'),
        ).toHaveCount(0);

        const toast = page.locator(
            '#daily-live-undo-toast',
        );

        await expect(toast).toBeVisible();
        await expect(
            toast.locator('.global-undo-title'),
        ).toHaveText(expectedToast);

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

        await expect(
            page.locator('#vencidas > .list')
                .locator('.item', {
                    hasText: 'E2E reflow dos',
                }),
        ).toBeVisible();

        await expect(
            card.locator('[data-daily-overdue-pill]'),
        ).toBeVisible();

        await expect(
            card.locator('[data-daily-due-date]'),
        ).toHaveText(originalDue);
    };

    await runMove(
        'today',
        'Hoy',
        'hoy',
        'Movida a hoy',
    );

    await runMove(
        'tomorrow',
        'Mañana',
        'esta-semana',
        'Movida a mañana',
    );

    await runMove(
        'next_week',
        '+1 semana',
        'esta-semana',
        'Movida una semana',
    );

    await expectNoHorizontalOverflow(page);
});

test('Mi Día mantiene acciones de fecha en vivo dentro del filtro Vencidas', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'El comportamiento filtrado se valida una sola vez.',
    );

    await page.goto(
        '/mi-dia?priority=overdue&view=mine&q=E2E%20fecha%20filtro',
    );

    const originalUrl = page.url();
    const title = page.getByText(
        'E2E fecha filtro',
        { exact: true },
    ).first();

    await expect(title).toBeVisible();

    const card = title.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );
    const overdueStat = page.locator(
        '[data-daily-stat="overdue"]',
    );
    const overdueBefore = Number.parseInt(
        await overdueStat.textContent(),
        10,
    );

    const todayForm = card.locator(
        'form[data-daily-action="today"]',
    );

    await expect(todayForm).toBeVisible();

    const responsePromise = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && response.url().includes('/mi-dia/tareas/')
            && response.url().includes('/accion')
        ),
    );

    await todayForm.getByRole('button', {
        name: 'Hoy',
        exact: true,
    }).click();

    const response = await responsePromise;
    expect(response.status()).toBe(200);

    const payload = await response.json();

    expect(payload.presentation.overdue).toBe(false);

    const toast = page.locator(
        '#daily-live-undo-toast',
    );

    try {
        await expect(title).toBeHidden();
        await expect(overdueStat).toHaveText(
            String(overdueBefore - 1),
        );
        expect(page.url()).toBe(originalUrl);
        await expect(toast).toBeVisible();
    } finally {
        if (await toast.isVisible().catch(() => false)) {
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
        }
    }

    await expect(title).toBeVisible();
    await expect(overdueStat).toHaveText(
        String(overdueBefore),
    );
    expect(page.url()).toBe(originalUrl);

    await expectNoHorizontalOverflow(page);
});

test('Mi Día pone una tarea En espera en vivo y permite deshacer', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La transición En espera se valida una sola vez.',
    );

    await page.goto(
        '/mi-dia?q=E2E%20espera%20live',
    );

    const sourceList = page.locator(
        '#vencidas > .list',
    );
    const sourceCard = sourceList.locator(
        '.item',
        { hasText: 'E2E espera live' },
    ).first();

    await expect(sourceCard).toBeVisible();

    const card = sourceCard;
    const waitingStat = page.locator(
        '[data-daily-stat="waiting"]',
    );
    const waitingBefore = Number.parseInt(
        await waitingStat.textContent(),
        10,
    );
    const details = card.locator(
        'details.task-edit',
        { hasText: 'En espera' },
    );

    await details.locator('summary').click();

    const form = details.locator(
        'form.waiting-form',
    );

    await form.locator(
        'input[name="waiting_reason"]',
    ).fill('Esperando validación E2E');

    const responsePromise = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && new URL(
                response.url(),
            ).pathname.includes('/esperar')
        ),
    );

    await form.getByRole('button', {
        name: 'Poner en espera',
        exact: true,
    }).click();

    const response = await responsePromise;
    expect(response.status()).toBe(200);

    const payload = await response.json();
    expect(payload.ok).toBe(true);

    const waitingList = page.locator(
        '#en-espera > .list',
    );
    const toast = page.locator(
        '#daily-live-undo-toast',
    );

    try {
        await expect(sourceCard).toBeHidden();
        await expect(waitingStat).toHaveText(
            String(waitingBefore + 1),
        );

        await expect(
            waitingList.locator('.daily-waiting-live', {
                hasText: 'E2E espera live',
            }),
        ).toBeVisible();

        await expect(
            waitingList.locator('.daily-waiting-live', {
                hasText: 'Esperando validación E2E',
            }),
        ).toBeVisible();

        await expect(
            waitingList
                .locator('.daily-waiting-live', {
                    hasText: 'E2E espera live',
                })
                .getByRole('button', {
                    name: 'Reactivar',
                    exact: true,
                }),
        ).toBeVisible();

        await expect(toast).toBeVisible();
        await expect(
            toast.locator('.global-undo-title'),
        ).toHaveText('Tarea en espera');
    } finally {
        if (await toast.isVisible().catch(() => false)) {
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
        }
    }

    await expect(sourceCard).toBeVisible();
    await expect(waitingStat).toHaveText(
        String(waitingBefore),
    );
    await expect(
        waitingList.locator('.daily-waiting-live', {
            hasText: 'E2E espera live',
        }),
    ).toHaveCount(0);

    await expectNoHorizontalOverflow(page);
});

test('Mi Día reactiva una tarea En espera en vivo y permite deshacer', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La reactivación live se valida una sola vez.',
    );

    await page.goto(
        '/mi-dia?q=E2E%20reactivar%20espera',
    );

    const originalUrl = page.url();
    const waitingList = page.locator(
        '#en-espera > .list',
    );
    const waitingCard = waitingList.locator(
        '.daily-waiting-card',
        { hasText: 'E2E reactivar espera' },
    ).first();
    const waitingStat = page.locator(
        '[data-daily-stat="waiting"]',
    );
    const waitingHeader = page.locator(
        '[data-daily-waiting-count]',
    );
    const overdueStat = page.locator(
        '[data-daily-stat="overdue"]',
    );

    await expect(waitingCard).toBeVisible();
    await expect(
        waitingCard.getByText(
            'Esperando aprobación E2E',
            { exact: false },
        ),
    ).toBeVisible();

    const waitingBefore = Number.parseInt(
        await waitingStat.textContent(),
        10,
    );
    const overdueBefore = Number.parseInt(
        await overdueStat.textContent(),
        10,
    );

    const resumeResponse = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && new URL(
                response.url(),
            ).pathname.includes('/reactivar')
        ),
    );

    await waitingCard.getByRole('button', {
        name: 'Reactivar',
        exact: true,
    }).click();

    const response = await resumeResponse;
    expect(response.status()).toBe(200);

    const payload = await response.json();
    expect(payload.ok).toBe(true);
    expect(payload.presentation.overdue).toBe(true);
    expect(payload.presentation.destination).toBe(
        'vencidas',
    );

    await expect(waitingCard).toBeHidden();
    await expect(waitingStat).toHaveText(
        String(waitingBefore - 1),
    );
    await expect(waitingHeader).toHaveText(
        String(waitingBefore - 1),
    );
    await expect(overdueStat).toHaveText(
        String(overdueBefore + 1),
    );
    expect(page.url()).toBe(originalUrl);

    const activeCard = page.locator(
        '#vencidas > .list .item',
        { hasText: 'E2E reactivar espera' },
    ).first();

    await expect(activeCard).toBeVisible();
    await expect(
        activeCard.locator(
            'form[data-daily-action="complete"]',
        ),
    ).toHaveAttribute(
        'data-live-complete',
        'true',
    );
    await expect(
        activeCard.locator(
            'form.waiting-form',
        ),
    ).toHaveAttribute(
        'data-daily-live-waiting-bound',
        '1',
    );

    const toast = page.locator(
        '#daily-live-undo-toast',
    );

    await expect(toast).toBeVisible();
    await expect(
        toast.locator('.global-undo-title'),
    ).toHaveText('Tarea reactivada');

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

    await expect(waitingCard).toBeVisible();
    await expect(waitingStat).toHaveText(
        String(waitingBefore),
    );
    await expect(waitingHeader).toHaveText(
        String(waitingBefore),
    );
    await expect(overdueStat).toHaveText(
        String(overdueBefore),
    );
    await expect(activeCard).toHaveCount(0);
    expect(page.url()).toBe(originalUrl);

    await expectNoHorizontalOverflow(page);
});

test('Mi Día pone En espera sin recargar dentro del filtro Vencidas', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'El caso filtrado En espera se valida una sola vez.',
    );

    await page.goto(
        '/mi-dia?priority=overdue&view=mine&q=E2E%20espera%20filtro',
    );

    const originalUrl = page.url();
    const title = page.getByText(
        'E2E espera filtro',
        { exact: true },
    ).first();

    await expect(title).toBeVisible();

    const card = title.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );
    const overdueStat = page.locator(
        '[data-daily-stat="overdue"]',
    );
    const overdueBefore = Number.parseInt(
        await overdueStat.textContent(),
        10,
    );
    const waitingBefore = Number.parseInt(
        await page.locator(
            '[data-daily-stat="waiting"]',
        ).textContent(),
        10,
    );
    const details = card.locator(
        'details.task-edit',
        { hasText: 'En espera' },
    );

    await details.locator('summary').click();

    const form = details.locator(
        'form.waiting-form',
    );

    await form.locator(
        'input[name="waiting_reason"]',
    ).fill('Esperando filtro E2E');

    const responsePromise = page.waitForResponse(
        (response) => (
            response.request().method() === 'POST'
            && new URL(
                response.url(),
            ).pathname.includes('/esperar')
        ),
    );

    await form.getByRole('button', {
        name: 'Poner en espera',
        exact: true,
    }).click();

    expect((await responsePromise).status()).toBe(200);

    const toast = page.locator(
        '#daily-live-undo-toast',
    );

    try {
        await expect(title).toBeHidden();
        await expect(overdueStat).toHaveText(
            String(overdueBefore - 1),
        );
        await expect(
            page.locator('[data-daily-stat="waiting"]'),
        ).toHaveText(String(waitingBefore));
        expect(page.url()).toBe(originalUrl);
    } finally {
        if (await toast.isVisible().catch(() => false)) {
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
        }
    }

    await expect(title).toBeVisible();
    await expect(overdueStat).toHaveText(
        String(overdueBefore),
    );
    expect(page.url()).toBe(originalUrl);

    await expectNoHorizontalOverflow(page);
});

test('Mi Día jerarquiza empresa en cabecera según viewport', async (
    { page },
    testInfo,
) => {
    test.skip(
        ! ['desktop-1366', 'mobile-390'].includes(
            testInfo.project.name,
        ),
        'La cabecera se valida en desktop y móvil.',
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
    const heading = card.locator(
        '.task-card-heading',
    );
    const company = card.locator(
        '.task-organization-badge',
    );

    await expect(heading).toBeVisible();
    await expect(company).toBeVisible();

    if (testInfo.project.name === 'desktop-1366') {
        await expect(company).toHaveText(
            'ARPYNET E2E',
        );

        const badgeFits = await company.evaluate(
            (element) => (
                element.scrollWidth
                <= element.clientWidth + 1
            ),
        );

        expect(badgeFits).toBe(true);
    }

    const titleBox = await title.boundingBox();
    const companyBox = await company.boundingBox();

    expect(titleBox).not.toBeNull();
    expect(companyBox).not.toBeNull();

    if (testInfo.project.name === 'desktop-1366') {
        expect(companyBox.x).toBeGreaterThan(
            titleBox.x + 40,
        );
        expect(
            Math.abs(companyBox.y - titleBox.y),
        ).toBeLessThan(18);
    } else {
        expect(companyBox.y).toBeGreaterThan(
            titleBox.y + 10,
        );
        expect(
            Math.abs(companyBox.x - titleBox.x),
        ).toBeLessThan(12);
    }

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

    await followingCard.evaluate((element) => {
        window.__centralE2EReflowObserved = false;

        const observer = new MutationObserver(() => {
            if (
                element.classList.contains(
                    'daily-task-reflowing',
                )
            ) {
                window.__centralE2EReflowObserved = true;
            }
        });

        observer.observe(element, {
            attributes: true,
            attributeFilter: ['class'],
        });

        window.__centralE2EReflowObserver = observer;
    });

    await card.evaluate((element) => {
        window.__centralE2ERestoreObserved = false;

        const observer = new MutationObserver(() => {
            if (
                element.classList.contains(
                    'daily-task-restoring',
                )
            ) {
                window.__centralE2ERestoreObserved = true;
            }
        });

        observer.observe(element, {
            attributes: true,
            attributeFilter: ['class'],
        });

        window.__centralE2ERestoreObserver = observer;
    });

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

    const toast = page.locator(
        '#daily-live-undo-toast',
    );

    try {
        await expect(overdueStat).toHaveText(
            String(overdueBefore - 1),
        );

        await expect(
            page.locator('[data-daily-focus-title]'),
        ).toContainText(
            String(overdueBefore - 1),
        );

        await expect(card).toBeHidden();

        await page.waitForFunction(
            () => (
                window.__centralE2EReflowObserved
                === true
            ),
            null,
            { timeout: 2000 },
        );

        await expect(followingCard).not.toHaveClass(
            /daily-task-reflowing/,
            { timeout: 2000 },
        );

        const after = await followingCard.boundingBox();
        expect(after).not.toBeNull();

        const moved = (
            Math.abs(after.x - before.x) > 4
            || Math.abs(after.y - before.y) > 4
        );

        expect(moved).toBe(true);
        await expect(toast).toBeVisible();
    } finally {
        if (await toast.isVisible().catch(() => false)) {
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
        }
    }

    await expect(overdueStat).toHaveText(
        String(overdueBefore),
    );

    await expect(
        page.locator('[data-daily-focus-title]'),
    ).toContainText(
        String(overdueBefore),
    );

    await expect(title).toBeVisible();

    await page.waitForFunction(
        () => (
            window.__centralE2ERestoreObserved
            === true
        ),
        null,
        { timeout: 1500 },
    );

    const restoredCard = title.locator(
        'xpath=ancestor::*[contains(@class,"item")][1]',
    );

    await expect(restoredCard).not.toHaveClass(
        /daily-task-restoring/,
        { timeout: 1500 },
    );
    await expect(title).toBeVisible();

    await page.evaluate(() => {
        window.__centralE2EReflowObserver?.disconnect();
        window.__centralE2ERestoreObserver?.disconnect();
    });

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
    await page.goto(
        '/buscar?q=E2E%20b%C3%BAsqueda%20tarea',
    );

    await expect(
        page.getByText(
            'E2E búsqueda tarea',
            { exact: true },
        ).first(),
    ).toBeVisible();

    await page.goto('/buscar?q=E2E');

    for (const label of [
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

test('captura rápida filtra responsables y proyectos por empresa', async (
    { page },
    testInfo,
) => {
    test.skip(
        testInfo.project.name !== 'desktop-1366',
        'La lógica dinámica de captura se valida una sola vez.',
    );

    await page.goto('/captura');

    const organization = page.locator(
        '#organization-select',
    );
    const assignee = page.locator(
        'select[name="assigned_to"]',
    );
    const project = page.locator(
        '#project-select',
    );
    const primaryOnly = assignee.locator(
        'option',
        { hasText: 'E2E ARPYNET solo' },
    );
    const primaryProject = project.locator(
        'option',
        { hasText: 'E2E proyecto' },
    );
    const secondaryProject = project.locator(
        'option',
        {
            hasText:
                'Proyecto secundario captura',
        },
    );

    await expect(primaryOnly).not.toHaveAttribute(
        'disabled',
        '',
    );
    await expect(primaryProject).not.toHaveAttribute(
        'disabled',
        '',
    );
    await expect(secondaryProject).toHaveAttribute(
        'disabled',
        '',
    );
    await expect(secondaryProject).toHaveAttribute(
        'hidden',
        '',
    );

    const secondaryValue = await organization
        .locator(
            'option',
            {
                hasText:
                    'ARPYNET E2E Secundaria',
            },
        )
        .getAttribute('value');

    expect(secondaryValue).toBeTruthy();

    await organization.selectOption(
        secondaryValue,
    );

    await expect(primaryOnly).toHaveAttribute(
        'disabled',
        '',
    );
    await expect(primaryOnly).toHaveAttribute(
        'hidden',
        '',
    );
    await expect(primaryProject).toHaveAttribute(
        'disabled',
        '',
    );
    await expect(primaryProject).toHaveAttribute(
        'hidden',
        '',
    );
    await expect(secondaryProject).not.toHaveAttribute(
        'disabled',
        '',
    );
    await expect(
        assignee.locator('option:checked'),
    ).toHaveText('Central E2E');

    await page.goto(
        `/captura?organization_id=${secondaryValue}`,
    );

    await expect(organization).toHaveValue(
        secondaryValue,
    );
    await expect(
        page.getByText(
            'Empresa: ARPYNET E2E Secundaria',
            { exact: true },
        ),
    ).toBeVisible();

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

