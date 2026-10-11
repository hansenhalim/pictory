import { openDB, type DBSchema } from 'idb'
import type { Frame, PendingAssignment, PendingUpload, PrintRecord } from '@/types'

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
  prints: { key: string; value: PrintRecord; indexes: { createdAt: number } }
  assignments: { key: string; value: PendingAssignment; indexes: { createdAt: number } }
}

/**
 * Everything the kiosk needs to keep running offline: the frames and their images, the
 * sessions still waiting to be uploaded, and the history of prints for reprinting, and the QR cards still waiting to be assigned. Blobs are
 * too large for localStorage.
 */
export const db = openDB<KioskDatabase>('pictory-kiosk', 3, {
  upgrade(database, oldVersion) {
    if (oldVersion < 1) {
      database.createObjectStore('frames', { keyPath: 'id' })
      database.createObjectStore('frameImages', { keyPath: 'frameId' })
      database
        .createObjectStore('uploads', { keyPath: 'ref' })
        .createIndex('createdAt', 'createdAt')
    }

    if (oldVersion < 2) {
      database.createObjectStore('prints', { keyPath: 'ref' }).createIndex('createdAt', 'createdAt')
    }

    if (oldVersion < 3) {
      database
        .createObjectStore('assignments', { keyPath: 'ref' })
        .createIndex('createdAt', 'createdAt')
    }
  },
})
