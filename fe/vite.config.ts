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
  '/c/',
  '/C/',
]

/**
 * web-gphoto2, which drives a DSLR, shares memory with its worker threads. Chrome allows that
 * only on a cross-origin isolated page, so whatever serves /kiosk/ sends these headers.
 * Credentialless still loads cross-origin resources that lack a CORP header, minus cookies.
 */
const crossOriginIsolation = {
  'Cross-Origin-Opener-Policy': 'same-origin',
  'Cross-Origin-Embedder-Policy': 'credentialless',
}

// https://vite.dev/config/
export default defineConfig({
  base: '/kiosk/',
  plugins: [
    vue(),
    vueDevTools(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      // Installing the kiosk as an app is what makes Chrome grant it persistent storage.
      manifest: {
        id: '/kiosk/',
        name: 'pictory.photo kiosk',
        short_name: 'pictory',
        start_url: '/kiosk/',
        scope: '/kiosk/',
        display: 'fullscreen',
        background_color: '#0a0a0a',
        theme_color: '#0a0a0a',
        icons: [
          { src: 'pwa-192.png', sizes: '192x192', type: 'image/png' },
          { src: 'pwa-512.png', sizes: '512x512', type: 'image/png' },
          { src: 'pwa-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2,wasm}'],
        navigateFallback: '/kiosk/index.html',
        navigateFallbackAllowlist: [/^\/kiosk(\/|$)/],
        cleanupOutdatedCaches: true,
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      // The WebAssembly module behind web-gphoto2's wrapper, which its package does not export.
      'web-gphoto2/libapi': fileURLToPath(
        new URL('./node_modules/web-gphoto2/build/libapi.mjs', import.meta.url),
      ),
    },
  },
  // Pre-bundling would move web-gphoto2 away from the wasm and worker files it loads beside it.
  optimizeDeps: {
    exclude: ['web-gphoto2'],
  },
  build: {
    outDir: '../be/public/kiosk',
    emptyOutDir: true,
  },
  server: {
    port: 5174,
    strictPort: true,
    // Vite sends these only with what it serves itself, not with Laravel's proxied pages.
    headers: crossOriginIsolation,
    proxy: Object.fromEntries(
      backendPaths.map((path) => [path, { target: backendUrl, changeOrigin: false }]),
    ),
  },
})
