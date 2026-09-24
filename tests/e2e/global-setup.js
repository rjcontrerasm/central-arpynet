import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { login } from './helpers.js';

export default async function globalSetup() {
    const baseURL =
        process.env.E2E_BASE_URL
        || 'http://127.0.0.1:8000';

    const browser = await chromium.launch();
    const context = await browser.newContext({
        baseURL,
    });
    const page = await context.newPage();

    await login(page);

    await mkdir('.playwright/.auth', {
        recursive: true,
    });

    await context.storageState({
        path: '.playwright/.auth/e2e.json',
    });

    await browser.close();
}
