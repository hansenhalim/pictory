import { defineStore } from 'pinia'
import { useStorage } from '@vueuse/core'

/** Per-kiosk settings, kept on the device. Frames are global; each kiosk picks its own. */
export interface KioskSettings {
  /** Frames offered to customers, in the order they are shown. */
  enabledFrameIds: number[]
  cameraDeviceId: string
  mirror: boolean
  countdownSeconds: number
  agentUrl: string
}

export const useSettingsStore = defineStore('settings', () => {
  const settings = useStorage<KioskSettings>(
    'pictory.settings',
    {
      enabledFrameIds: [],
      cameraDeviceId: '',
      mirror: true,
      countdownSeconds: 3,
      agentUrl: 'http://127.0.0.1:8001',
    },
    localStorage,
    { mergeDefaults: true },
  )

  function toggleFrame(frameId: number) {
    const ids = settings.value.enabledFrameIds

    settings.value.enabledFrameIds = ids.includes(frameId)
      ? ids.filter((id) => id !== frameId)
      : [...ids, frameId]
  }

  /** Forget frames that were deleted from the backend. */
  function keepFrames(existingIds: number[]) {
    settings.value.enabledFrameIds = settings.value.enabledFrameIds.filter((id) =>
      existingIds.includes(id),
    )
  }

  return { settings, toggleFrame, keepFrames }
})
