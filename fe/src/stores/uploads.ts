import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, isShortCodeRejected } from '@/lib/api'
import { db } from '@/lib/db'
import { useShortCodesStore } from '@/stores/shortCodes'
import type { PendingUpload } from '@/types'

const FIRST_RETRY_MS = 5_000
const MAX_RETRY_MS = 5 * 60_000

/**
 * Finished sessions waiting to reach the backend. Each one is saved to IndexedDB before it
 * is printed, then uploaded in the background and retried with backoff until it succeeds,
 * so photos survive the kiosk going offline, reloading or crashing.
 */
export const useUploadsStore = defineStore('uploads', () => {
  const shortCodesStore = useShortCodesStore()

  const pendingCount = ref(0)

  let isRunning = false
  let isRerunRequested = false
  let failedRuns = 0
  let retryTimer: ReturnType<typeof setTimeout> | undefined

  async function refreshCount() {
    pendingCount.value = await (await db).count('uploads')
  }

  async function enqueue(upload: PendingUpload) {
    await (await db).put('uploads', upload)
    await refreshCount()
    void run()
  }

  async function upload(pending: PendingUpload) {
    const form = new FormData()
    form.append('ref', pending.ref)
    form.append('paper', pending.paper, 'paper.png')
    pending.photos.forEach((photo, index) =>
      form.append('photos[]', photo, `photo-${index + 1}.jpg`),
    )

    if (pending.code) {
      form.append('code', pending.code)
    }

    try {
      const stored = await api.storePaper(form)

      if (stored.replacement_code) {
        shortCodesStore.add(stored.replacement_code)
      }
    } catch (error) {
      if (pending.code && isShortCodeRejected(error)) {
        await (await db).put('uploads', { ...pending, code: null })

        return upload({ ...pending, code: null })
      }

      throw error
    }
  }

  /** Upload every pending session, oldest first. One failing session does not hold up the rest. */
  async function run(): Promise<void> {
    if (isRunning) {
      isRerunRequested = true
      return
    }

    isRunning = true
    clearTimeout(retryTimer)

    try {
      const database = await db
      let hasFailed = false

      for (const pending of await database.getAllFromIndex('uploads', 'createdAt')) {
        try {
          await upload(pending)
          await database.delete('uploads', pending.ref)
        } catch (error) {
          hasFailed = true
          console.warn(`Upload of session ${pending.ref} failed; will retry.`, error)
        }
      }

      failedRuns = hasFailed ? failedRuns + 1 : 0

      if (hasFailed) {
        retryTimer = setTimeout(run, Math.min(FIRST_RETRY_MS * 2 ** (failedRuns - 1), MAX_RETRY_MS))
      }
    } finally {
      isRunning = false
      await refreshCount()
    }

    if (isRerunRequested) {
      isRerunRequested = false
      return run()
    }
  }

  function start() {
    window.addEventListener('online', () => void run())
    void run()
  }

  return { pendingCount, enqueue, start }
})
