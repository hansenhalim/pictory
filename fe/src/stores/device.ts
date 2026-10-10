import { defineStore } from 'pinia'
import { ref } from 'vue'

/** Chrome's install prompt, which TypeScript's DOM types do not include yet. */
interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

/**
 * The kiosk machine's browser: whether its storage is safe from eviction, and installing the
 * kiosk as an app, which is what makes Chrome grant persistent storage.
 */
export const useDeviceStore = defineStore('device', () => {
  const isStoragePersistent = ref(false)
  const storageUsedBytes = ref<number | null>(null)
  const installPrompt = ref<BeforeInstallPromptEvent | null>(null)

  /** Ask Chrome not to evict the history and pending uploads. It decides silently. */
  async function requestPersistentStorage() {
    if (!navigator.storage?.persist) {
      return
    }

    isStoragePersistent.value =
      (await navigator.storage.persisted()) || (await navigator.storage.persist())
  }

  async function refreshStorageUsage() {
    storageUsedBytes.value = (await navigator.storage?.estimate())?.usage ?? null
  }

  async function install() {
    const prompt = installPrompt.value

    if (!prompt) {
      return
    }

    await prompt.prompt()
    installPrompt.value = null
  }

  /** Must run before the page finishes loading, as Chrome offers the install prompt only once. */
  function start() {
    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault()
      installPrompt.value = event as BeforeInstallPromptEvent
    })

    window.addEventListener('appinstalled', () => {
      installPrompt.value = null
      void requestPersistentStorage()
    })

    void requestPersistentStorage()
  }

  return {
    isStoragePersistent,
    storageUsedBytes,
    installPrompt,
    requestPersistentStorage,
    refreshStorageUsage,
    install,
    start,
  }
})
