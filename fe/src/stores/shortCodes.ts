import { defineStore } from 'pinia'
import { useStorage } from '@vueuse/core'
import { api } from '@/lib/api'

const POOL_SIZE = 100

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

  /** Reserve enough codes to fill the pool back up. */
  function topUp(): Promise<void> {
    topUpInFlight ??= (async () => {
      const missing = POOL_SIZE - pool.value.length

      if (missing > 0) {
        pool.value = [...pool.value, ...(await api.reserveShortCodes(missing))]
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

  function add(code: string) {
    pool.value = [...pool.value, code]
  }

  return { pool, topUp, take, add }
})
