import { defineStore } from 'pinia'
import { watchEffect } from 'vue'
import { useStorage } from '@vueuse/core'

/** The browser's toolbar color for each theme, matching the page background. */
const THEME_COLORS = { dark: '#0a0a0a', light: '#fafafa' }

/** Per-kiosk settings, kept on the device. Frames are global; each kiosk picks its own. */
export interface KioskSettings {
  /** Frames offered to customers, in the order they are shown. */
  enabledFrameIds: number[]
  /** The webcam picked by `cameraDeviceId`, or the DSLR the operator allowed over USB. */
  cameraSource: 'webcam' | 'dslr'
  cameraDeviceId: string
  mirror: boolean
  countdownSeconds: number
  /** Record the countdown before each photo as a behind-the-scenes clip for the operator. */
  recordClips: boolean
  agentUrl: string
  /** What the result screen offers for the soft copy: the short code's QR, or assigning a QR card. */
  softCopyLink: 'qr' | 'card'
  theme: 'dark' | 'light'
}

export const useSettingsStore = defineStore('settings', () => {
  const settings = useStorage<KioskSettings>(
    'pictory.settings',
    {
      enabledFrameIds: [],
      cameraSource: 'webcam',
      cameraDeviceId: '',
      mirror: true,
      countdownSeconds: 3,
      recordClips: true,
      agentUrl: 'http://127.0.0.1:8001',
      softCopyLink: 'qr',
      theme: 'dark',
    },
    localStorage,
    { mergeDefaults: true },
  )

  watchEffect(() => {
    document.documentElement.dataset.theme = settings.value.theme
    document
      .querySelector('meta[name="theme-color"]')
      ?.setAttribute('content', THEME_COLORS[settings.value.theme])
  })

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
