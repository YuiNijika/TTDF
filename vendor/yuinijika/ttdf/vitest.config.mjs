import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        environmentOptions: {
            jsdom: {
                url: 'https://example.test/admin/themes.php?tab=general'
            }
        },
        include: ['tests/options/**/*.test.mjs'],
        setupFiles: ['./tests/options/setup.mjs'],
        clearMocks: true,
        restoreMocks: true
    }
});
