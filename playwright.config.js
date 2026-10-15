import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    use: {
        baseURL: 'http://127.0.0.1:4173',
    },
    webServer: {
        command: 'npx vite --config vite.e2e.config.js',
        url: 'http://127.0.0.1:4173',
        reuseExistingServer: true,
        timeout: 120000,
    },
});
