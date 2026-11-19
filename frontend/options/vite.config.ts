import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: '../../vendor/yuinijika/ttdf/static',
    // `static/` is generated output; wiping it prevents stale hashed chunks.
    emptyOutDir: true,
    manifest: false,
    // Both admin panels share components; a single stylesheet avoids emitting
    // orphaned per-chunk CSS that the PHP shell would have to know about.
    cssCodeSplit: false,
    rollupOptions: {
      input: {
        options: fileURLToPath(new URL('./src/options/index.ts', import.meta.url)),
        'custom-theme': fileURLToPath(new URL('./src/customizer/index.ts', import.meta.url)),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (asset) => (
          asset.names?.some((name) => name.endsWith('.css'))
            ? 'app.css'
            : '[name][extname]'
        ),
        // Vue and Arco are shared by both entries; pinning the chunk name keeps
        // the emitted stylesheet at `app.css` instead of a module-derived name.
        manualChunks: (id: string) => (
          id.includes('node_modules/vue')
          || id.includes('node_modules/@vue')
          || id.includes('node_modules/@arco-design')
          || id.includes('/src/lib/')
            ? 'app'
            : undefined
        ),
      },
    },
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
})
