import { expect } from '@playwright/test';

export async function login(page) {
    await page.goto('/admin/login');

    const email = page.locator('input[type="email"]').first();
    const password = page.locator('input[type="password"]').first();

    await expect(email).toBeVisible();
    await expect(password).toBeVisible();

    await email.fill(
        process.env.E2E_EMAIL || 'e2e@arpynet.test',
    );
    await password.fill(
        process.env.E2E_PASSWORD
            || 'central-e2e-password',
    );

    await page
        .locator('button[type="submit"]')
        .first()
        .click();

    await expect(page).not.toHaveURL(
        /\/admin\/login/,
        { timeout: 10_000 },
    );
}

export async function expectNoHorizontalOverflow(page) {
    const geometry = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        page: document.documentElement.scrollWidth,
    }));

    expect(
        geometry.page,
        'page should not overflow horizontally',
    ).toBeLessThanOrEqual(
        geometry.viewport + 1,
    );
}
