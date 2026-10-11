import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useStorage } from '@vueuse/core'
import { api, ApiError } from '@/lib/api'
import { db } from '@/lib/db'
import { usePrintsStore } from '@/stores/prints'
import type { PendingAssignment } from '@/types'

const FIRST_RETRY_MS = 5_000
const MAX_RETRY_MS = 5 * 60_000

/**
 * Why the backend refused an assignment for good, or null when it is worth retrying. Sessions
 * are uploaded before their card is sent, so a missing session was deleted by an admin.
 */
function describeRejection(error: unknown, card: string | null): string | null {
  if (!(error instanceof ApiError)) {
    return null
  }

  if (error.status === 404) {
    return 'This session is no longer on the server.'
  }

  if (error.status === 422) {
    const errors = (error.body as { errors?: { card?: string[] } } | null)?.errors

    return errors?.card?.[0] ?? `Card ${card} was refused.`
  }

  return null
}

/**
 * Sessions the operator assigned to a QR card, waiting to reach the backend. Each assignment is
 * sent once its session has been uploaded and retried with backoff until the backend accepts or
 * refuses it. Assigning a session again replaces its waiting assignment.
 */
export const useAssignmentsStore = defineStore('assignments', () => {
  const printsStore = usePrintsStore()

  /** The card last given to a finished session, offered again along with the card after it. */
  const lastCard = useStorage<string | null>('pictory.lastCard', null)
  /** Sessions whose card has not reached the backend yet. */
  const pendingRefs = ref(new Set<string>())

  let isRunning = false
  let isRerunRequested = false
  let failedRuns = 0
  let retryTimer: ReturnType<typeof setTimeout> | undefined

  async function refreshPending() {
    pendingRefs.value = new Set(await (await db).getAllKeys('assignments'))
  }

  /** Put a session on a card, or take it off its card when the card is null. */
  async function assign(ref: string, card: string | null) {
    await (await db).put('assignments', { ref, card, createdAt: Date.now() })
    await printsStore.update(ref, { card, cardError: null })
    await refreshPending()
    void run()
  }

  /** Drop a sent assignment, unless the operator replaced it while it was being sent. */
  async function settle(pending: PendingAssignment, rejection: string | null) {
    const database = await db
    const current = await database.get('assignments', pending.ref)

    if (current?.createdAt !== pending.createdAt) {
      return
    }

    await database.delete('assignments', pending.ref)

    if (rejection) {
      await printsStore.update(pending.ref, { cardError: rejection })
    }
  }

  /** Send every assignment whose session has been uploaded, oldest first. */
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

      for (const pending of await database.getAllFromIndex('assignments', 'createdAt')) {
        if (await database.getKey('uploads', pending.ref)) {
          continue
        }

        try {
          await api.assignCard(pending.ref, pending.card)
          await settle(pending, null)
        } catch (error) {
          const rejection = describeRejection(error, pending.card)

          if (rejection) {
            await settle(pending, rejection)
          } else {
            hasFailed = true
            console.warn(`Assigning session ${pending.ref} to its card failed; will retry.`, error)
          }
        }
      }

      failedRuns = hasFailed ? failedRuns + 1 : 0

      if (hasFailed) {
        retryTimer = setTimeout(run, Math.min(FIRST_RETRY_MS * 2 ** (failedRuns - 1), MAX_RETRY_MS))
      }
    } finally {
      isRunning = false
      await refreshPending()
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

  return { lastCard, pendingRefs, assign, run, start }
})
