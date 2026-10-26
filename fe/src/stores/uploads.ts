import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, ApiError, isShortCodeRejected } from '@/lib/api'
import { db } from '@/lib/db'
import { useAssignmentsStore } from '@/stores/assignments'
import { useShortCodesStore } from '@/stores/shortCodes'
import type { PendingUpload } from '@/types'

const FIRST_RETRY_MS = 5_000
const MAX_RETRY_MS = 5 * 60_000

/** Explain a failed upload to the operator. */
function describeFailure(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return 'Could not reach the server.'
  }

  if (error.status === 413) {
    return 'The server rejected the upload as too large. Raise PHP’s upload_max_filesize and post_max_size.'
  }

  if (error.status === 401) {
    return 'Signed out of the admin panel. Sign in again to resume uploads.'
  }

  const message = (error.body as { message?: string } | null)?.message

  return `The server responded with ${error.status}${message ? `: ${message}` : '.'}`
}

/**
 * Finished sessions waiting to reach the backend. Each one is saved to IndexedDB before it
 * is printed, then uploaded in the background and retried with backoff until it succeeds,
 * so photos survive the kiosk going offline, reloading or crashing.
 */
export const useUploadsStore = defineStore('uploads', () => {
  const shortCodesStore = useShortCodesStore()
  const assignmentsStore = useAssignmentsStore()

  const pendingCount = ref(0)
  /** Why the last attempt failed, or null while uploads are going through. */
  const lastError = ref<string | null>(null)
  const nextRetryAt = ref<number | null>(null)

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
    pending.clips?.forEach((clip, index) => {
      if (clip) {
        form.append(`clips[${index}]`, clip, `clip-${index + 1}.mp4`)
      }
    })

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

  /**
   * Upload every pending session, oldest first. One failing session does not hold up the rest.
   * Cards assigned to sessions wait for their upload, so they are sent once it lands.
   */
  async function run(): Promise<void> {
    if (isRunning) {
      isRerunRequested = true
      return
    }

    isRunning = true
    clearTimeout(retryTimer)

    try {
      const database = await db
      let failure: string | null = null
      let hasUploaded = false

      for (const pending of await database.getAllFromIndex('uploads', 'createdAt')) {
        try {
          await upload(pending)
          await database.delete('uploads', pending.ref)
          hasUploaded = true
        } catch (error) {
          failure ??= describeFailure(error)
          console.warn(`Upload of session ${pending.ref} failed; will retry.`, error)
        }
      }

      if (hasUploaded) {
        void assignmentsStore.run()
      }

      failedRuns = failure ? failedRuns + 1 : 0
      lastError.value = failure
      nextRetryAt.value = null

      if (failure) {
        const delay = Math.min(FIRST_RETRY_MS * 2 ** (failedRuns - 1), MAX_RETRY_MS)
        nextRetryAt.value = Date.now() + delay
        retryTimer = setTimeout(run, delay)
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

  return { pendingCount, lastError, nextRetryAt, enqueue, retryNow: run, start }
})
