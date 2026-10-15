import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    root: fileURLToPath(new URL('./tests/e2e', import.meta.url)),
    server: {
        port: 4173,
        strictPort: true,
        fs: {
            allow: [fileURLToPath(new URL('.', import.meta.url))],
        },
    },
});
