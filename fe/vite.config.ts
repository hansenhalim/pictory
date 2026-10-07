import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

/**
 * In dev the kiosk is served from this Vite server, and everything Laravel owns is proxied
 * to it, so the panel's session cookie and the kiosk share one origin as in production.
 * The Host header is kept so Laravel builds URLs for this origin. Set BACKEND_URL when
 * Laravel listens elsewhere.
 */
const backendUrl = process.env.BACKEND_URL ?? 'http://localhost:8000'
const backendPaths = [
  '/api',
  '/admin',
  '/livewire',
  '/storage',
  '/css',
  '/js',
  '/fonts',
  '/p/',
  '/P/',
]

// https://vite.dev/config/
export default defineConfig({
  base: '/kiosk/',
  plugins: [
    vue(),
    vueDevTools(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      manifest: false,
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
        navigateFallback: '/kiosk/index.html',
        navigateFallbackAllowlist: [/^\/kiosk(\/|$)/],
        cleanupOutdatedCaches: true,
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    outDir: '../be/public/kiosk',
    emptyOutDir: true,
  },
  server: {
    port: 5174,
    strictPort: true,
    proxy: Object.fromEntries(
      backendPaths.map((path) => [path, { target: backendUrl, changeOrigin: false }]),
    ),
  },
})
