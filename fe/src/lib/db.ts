import { openDB, type DBSchema } from 'idb'
import type { Frame, PendingUpload } from '@/types'

export interface StoredFrameImage {
  frameId: number
  /** The frame's `updated_at` when the image was downloaded, to know when to fetch it again. */
  updatedAt: string
  blob: Blob
}

interface KioskDatabase extends DBSchema {
  frames: { key: number; value: Frame }
  frameImages: { key: number; value: StoredFrameImage }
  uploads: { key: string; value: PendingUpload; indexes: { createdAt: number } }
}

/**
 * Everything the kiosk needs to keep running offline: the frames and their images, and the
 * sessions still waiting to be uploaded. Blobs are too large for localStorage.
 */
export const db = openDB<KioskDatabase>('pictory-kiosk', 1, {
  upgrade(database) {
    database.createObjectStore('frames', { keyPath: 'id' })
    database.createObjectStore('frameImages', { keyPath: 'frameId' })
    database.createObjectStore('uploads', { keyPath: 'ref' }).createIndex('createdAt', 'createdAt')
  },
})
