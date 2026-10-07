import './style.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { useFramesStore } from './stores/frames'
import { useShortCodesStore } from './stores/shortCodes'
import { useUploadsStore } from './stores/uploads'

const app = createApp(App)

app.use(createPinia())

const framesStore = useFramesStore()
const shortCodesStore = useShortCodesStore()

/** Refresh what the kiosk caches for offline use. Failures are fine: the cache keeps working. */
function refreshCache() {
  void framesStore.sync().catch(() => {})
  void shortCodesStore.topUp().catch(() => {})
}

await framesStore.load()

useUploadsStore().start()
window.addEventListener('online', refreshCache)

if (navigator.onLine) {
  refreshCache()
}

app.use(router)

app.mount('#app')
