import { defineStore } from 'pinia'
import { ref } from 'vue'
import { db } from '@/lib/db'
import type { PrintRecord } from '@/types'

const PAGE_SIZE = 24

/**
 * Every session this kiosk has printed, kept until the operator clears it, so a customer
 * can get another print later. Pages are read newest first with a cursor, so a long history
 * is never loaded all at once.
 */
export const usePrintsStore = defineStore('prints', () => {
  const prints = ref<PrintRecord[]>([])
  const total = ref(0)
  /** Why the latest print could not be kept, e.g. because the disk is full. */
  const saveError = ref<string | null>(null)

  async function readPage(offset: number): Promise<PrintRecord[]> {
    const database = await db
    let cursor = await database
      .transaction('prints')
      .store.index('createdAt')
      .openCursor(null, 'prev')

    if (cursor && offset > 0) {
      cursor = await cursor.advance(offset)
    }

    const page: PrintRecord[] = []

    while (cursor && page.length < PAGE_SIZE) {
      page.push(cursor.value)
      cursor = await cursor.continue()
    }

    return page
  }

  async function load() {
    const [firstPage, count] = await Promise.all([readPage(0), (await db).count('prints')])

    prints.value = firstPage
    total.value = count
  }

  async function loadMore() {
    prints.value = [...prints.value, ...(await readPage(prints.value.length))]
  }

  /** Keep a print. Never throws: losing history must not stop a customer's session. */
  async function save(print: PrintRecord) {
    try {
      await (await db).put('prints', print)
      saveError.value = null
    } catch (error) {
      console.error(error)
      saveError.value = error instanceof Error ? error.message : String(error)
    }
  }

  /** Change a kept print, e.g. to record its card. Prints no longer kept are left alone. */
  async function update(ref: string, changes: Partial<PrintRecord>) {
    try {
      const transaction = (await db).transaction('prints', 'readwrite')
      const print = await transaction.store.get(ref)

      if (print) {
        await transaction.store.put({ ...print, ...changes })
      }

      await transaction.done
    } catch (error) {
      console.error(error)
    }

    const loaded = prints.value.find((print) => print.ref === ref)

    if (loaded) {
      Object.assign(loaded, changes)
    }
  }

  async function clear() {
    await (await db).clear('prints')
    prints.value = []
    total.value = 0
  }

  return { prints, total, saveError, load, loadMore, save, update, clear }
})
