import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: {
        timeout: 7_000,
    },
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI
        ? [['list'], ['html', { open: 'never' }]]
        : 'list',
    use: {
        baseURL:
            process.env.E2E_BASE_URL
            || 'http://127.0.0.1:8000',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command:
            'php artisan serve --host=127.0.0.1 --port=8000',
        url: 'http://127.0.0.1:8000/admin/login',
        reuseExistingServer: ! process.env.CI,
        timeout: 120_000,
    },
    projects: [
        {
            name: 'desktop-1920',
            use: {
                viewport: {
                    width: 1920,
                    height: 1080,
                },
            },
        },
        {
            name: 'desktop-1366',
            use: {
                viewport: {
                    width: 1366,
                    height: 768,
                },
            },
        },
        {
            name: 'mobile-390',
            use: {
                viewport: {
                    width: 390,
                    height: 844,
                },
            },
        },
    ],
});
