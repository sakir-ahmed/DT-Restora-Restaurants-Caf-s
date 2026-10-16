import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    timeout: 120000,
    projects: [
        {
            name: 'library',
            testMatch: /offline\.spec\.js/,
            use: { baseURL: 'http://127.0.0.1:4317' },
        },
        {
            name: 'journey',
            testMatch: /pos-offline-journey\.spec\.js/,
            use: {
                baseURL: 'http://127.0.0.1:8099',
                browserName: 'chromium',
            },
        },
    ],
    webServer: [
        {
            command: 'npx vite --config vite.e2e.config.js',
            url: 'http://127.0.0.1:4317',
            reuseExistingServer: true,
            timeout: 120000,
        },
        {
            command: 'bash tests/e2e/serve.sh',
            url: 'http://127.0.0.1:8099/login',
            reuseExistingServer: false,
            timeout: 300000,
        },
    ],
});
