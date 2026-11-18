import { defineStore } from 'pinia'
import { useStorage } from '@vueuse/core'
import { api } from '@/lib/api'

const POOL_SIZE = 1000

/** The most codes the backend reserves in one request. */
const RESERVE_BATCH = 100

/**
 * The soft copy URL behind a printed QR code. It is uppercase so the whole URL fits QR
 * alphanumeric mode, which keeps the code small; the backend routes either case.
 */
export function shareUrl(code: string): string {
  return `${window.location.origin}/P/${code}`.toUpperCase()
}

/**
 * Short codes reserved by the backend ahead of time, so the kiosk can print QR codes while
 * offline. Each upload that uses one returns a replacement.
 */
export const useShortCodesStore = defineStore('shortCodes', () => {
  const pool = useStorage<string[]>('pictory.shortCodes', [])

  let topUpInFlight: Promise<void> | null = null

  /**
   * Reserve enough codes to fill the pool back up, a batch at a time. Each batch is kept as
   * it arrives, so a top-up cut off midway keeps the codes it already reserved.
   */
  function topUp(): Promise<void> {
    topUpInFlight ??= (async () => {
      let missing = POOL_SIZE - pool.value.length

      while (missing > 0) {
        const codes = await api.reserveShortCodes(Math.min(missing, RESERVE_BATCH))
        if (codes.length === 0) {
          return
        }

        pool.value = [...pool.value, ...codes]
        missing = POOL_SIZE - pool.value.length
      }
    })().finally(() => (topUpInFlight = null))

    return topUpInFlight
  }

  /** Take a code for a paper about to be printed, or null when the pool has run dry. */
  function take(): string | null {
    const [code, ...rest] = pool.value
    pool.value = rest

    return code ?? null
  }

  /** The code `take` would return next, left in the pool. */
  function peek(): string | null {
    return pool.value[0] ?? null
  }

  function add(code: string) {
    pool.value = [...pool.value, code]
  }

  return { pool, topUp, take, peek, add }
})
