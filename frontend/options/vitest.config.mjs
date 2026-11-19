import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        environmentOptions: {
            jsdom: {
                url: 'https://example.test/admin/options-theme.php?panel=ttdf-custom-theme',
            },
        },
        include: ['tests/**/*.test.mjs'],
        setupFiles: ['./tests/setup.mjs'],
        clearMocks: true,
        restoreMocks: true,
    },
});
