import { fileURLToPath, URL } from 'node:url'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'
import vuetify from 'vite-plugin-vuetify'

export default defineConfig({
  plugins: [vue(), vuetify({ autoImport: true, styles: { configFile: 'src/styles/settings.scss' } })],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': { target: process.env.API_PROXY_TARGET ?? 'http://localhost:8000', changeOrigin: true },
    },
  },
  test: {
    environment: 'jsdom',
    globals: false,
    server: { deps: { inline: ['vuetify'] } },
    setupFiles: ['src/test/setup.ts'],
  },
})
