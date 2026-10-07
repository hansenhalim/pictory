import { createRouter, createWebHistory } from 'vue-router'
import { whenUnauthorized } from '@/lib/api'
import { useFramesStore } from '@/stores/frames'
import { useSessionStore } from '@/stores/session'
import FrameSelectionView from '@/views/FrameSelectionView.vue'
import PhotoShootView from '@/views/PhotoShootView.vue'
import ResultView from '@/views/ResultView.vue'
import SettingsView from '@/views/SettingsView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'frames', component: FrameSelectionView },
    { path: '/settings', name: 'settings', component: SettingsView },
    { path: '/shoot', name: 'shoot', component: PhotoShootView },
    { path: '/result', name: 'result', component: ResultView },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

/** Screens between customers, where sending the operator to sign in interrupts nobody. */
const idleRouteNames = ['frames', 'settings']

let isSignedOut = false

function signIn() {
  window.location.href = '/admin/login'
}

whenUnauthorized(() => {
  isSignedOut = true

  if (idleRouteNames.includes(String(router.currentRoute.value.name))) {
    signIn()
  }
})

router.beforeEach((to) => {
  if (isSignedOut && idleRouteNames.includes(String(to.name))) {
    signIn()
    return false
  }

  if (to.name !== 'settings' && useFramesStore().enabledFrames.length === 0) {
    return { name: 'settings' }
  }

  const sessionStore = useSessionStore()

  if (to.name === 'shoot' && !sessionStore.frame) {
    return { name: 'frames' }
  }

  if (to.name === 'result' && !sessionStore.paper) {
    return { name: 'frames' }
  }
})

export default router
