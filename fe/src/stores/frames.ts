import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/lib/api'
import { db } from '@/lib/db'
import { useSettingsStore } from '@/stores/settings'
import type { Frame } from '@/types'

export interface FrameImage {
  blob: Blob
  url: string
  width: number
  height: number
}

/**
 * Frames and their images, cached in IndexedDB so the kiosk keeps working offline and
 * never depends on an image URL that may expire.
 */
export const useFramesStore = defineStore('frames', () => {
  const settingsStore = useSettingsStore()

  const frames = ref<Frame[]>([])
  const images = ref(new Map<number, FrameImage>())
  const isSyncing = ref(false)
  const syncError = ref<string | null>(null)

  const enabledFrames = computed(() =>
    settingsStore.settings.enabledFrameIds
      .map((id) => frames.value.find((frame) => frame.id === id))
      .filter((frame): frame is Frame => frame !== undefined && images.value.has(frame.id)),
  )

  /** Load the cached frames. */
  async function load() {
    const database = await db
    const [storedFrames, storedImages] = await Promise.all([
      database.getAll('frames'),
      database.getAll('frameImages'),
    ])

    const loadedImages = new Map<number, FrameImage>()

    for (const { frameId, blob } of storedImages) {
      const bitmap = await createImageBitmap(blob)
      loadedImages.set(frameId, {
        blob,
        url: URL.createObjectURL(blob),
        width: bitmap.width,
        height: bitmap.height,
      })
      bitmap.close()
    }

    images.value.forEach((image) => URL.revokeObjectURL(image.url))
    images.value = loadedImages
    frames.value = storedFrames.sort((a, b) => a.id - b.id)
  }

  /** Refresh the cache from the backend, downloading only the images that changed. */
  async function sync() {
    if (isSyncing.value) {
      return
    }

    isSyncing.value = true
    syncError.value = null

    try {
      const latestFrames = await api.frames()
      const database = await db
      const storedImages = new Map(
        (await database.getAll('frameImages')).map((image) => [image.frameId, image]),
      )

      const downloads = await Promise.all(
        latestFrames
          .filter((frame) => storedImages.get(frame.id)?.updatedAt !== frame.updated_at)
          .map(async (frame) => ({
            frameId: frame.id,
            updatedAt: frame.updated_at,
            blob: await api.frameImage(frame),
          })),
      )

      const transaction = database.transaction(['frames', 'frameImages'], 'readwrite')
      const frameStore = transaction.objectStore('frames')
      const imageStore = transaction.objectStore('frameImages')
      const latestIds = latestFrames.map((frame) => frame.id)

      await frameStore.clear()
      await Promise.all(latestFrames.map((frame) => frameStore.put(frame)))
      await Promise.all(downloads.map((image) => imageStore.put(image)))
      await Promise.all(
        [...storedImages.keys()]
          .filter((id) => !latestIds.includes(id))
          .map((id) => imageStore.delete(id)),
      )
      await transaction.done

      settingsStore.keepFrames(latestIds)
      await load()
    } catch (error) {
      syncError.value = error instanceof Error ? error.message : String(error)
      throw error
    } finally {
      isSyncing.value = false
    }
  }

  function image(frameId: number): FrameImage | undefined {
    return images.value.get(frameId)
  }

  return { frames, enabledFrames, isSyncing, syncError, load, sync, image }
})
