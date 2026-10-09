import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end suite against a dedicated database (catalogo_app_e2e, see .env.e2e.example).
 * Runs serially: every spec shares the seeded state and reseeds in global setup.
 */
export default defineConfig({
    testDir: 'tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    workers: 1,
    fullyParallel: false,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: 'http://127.0.0.1:8001',
        viewport: { width: 1400, height: 900 },
        locale: 'es-CO',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8001',
        url: 'http://127.0.0.1:8001/login',
        reuseExistingServer: false,
        timeout: 60_000,
        env: { APP_ENV: 'e2e' },
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
