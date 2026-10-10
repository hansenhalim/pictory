import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { renderPaper } from '@/lib/render'
import { useFramesStore } from '@/stores/frames'
import { usePrintsStore } from '@/stores/prints'
import { shareUrl, useShortCodesStore } from '@/stores/shortCodes'
import { useUploadsStore } from '@/stores/uploads'
import type { Frame } from '@/types'

/** The customer currently using the kiosk, from picking a frame to printing. */
export const useSessionStore = defineStore('session', () => {
  const framesStore = useFramesStore()
  const printsStore = usePrintsStore()
  const shortCodesStore = useShortCodesStore()
  const uploadsStore = useUploadsStore()

  /** Links the paper to its photos on the backend. */
  const sessionRef = ref<string | null>(null)
  const frame = ref<Frame | null>(null)
  const photos = ref<Blob[]>([])
  /** Uploaded for the soft copy page; has no QR codes. */
  const softCopy = ref<Blob | null>(null)
  /** Sent to the printer; carries the QR codes. */
  const printCopy = ref<Blob | null>(null)
  const code = ref<string | null>(null)

  const isComplete = computed(
    () => frame.value !== null && photos.value.length >= frame.value.slots.length,
  )

  function begin(selectedFrame: Frame) {
    sessionRef.value = crypto.randomUUID()
    frame.value = selectedFrame
    photos.value = []
    softCopy.value = null
    printCopy.value = null
    code.value = null
  }

  function addPhoto(photo: Blob) {
    photos.value = [...photos.value, photo]
  }

  /**
   * Render the paper, queue the session for upload and keep the print copy for reprints. The
   * short code is taken only now, so sessions abandoned before this point never use one up.
   */
  async function finish() {
    const currentFrame = frame.value
    const frameImage = currentFrame ? framesStore.image(currentFrame.id) : undefined

    if (!sessionRef.value || !currentFrame || !frameImage) {
      throw new Error('There is no session to finish.')
    }

    code.value ??= shortCodesStore.take()

    const paper = await renderPaper({
      frame: currentFrame,
      frameImage: frameImage.blob,
      photos: photos.value,
      shareUrl: code.value ? shareUrl(code.value) : null,
    })

    softCopy.value = paper.softCopy
    printCopy.value = paper.printCopy

    await uploadsStore.enqueue({
      ref: sessionRef.value,
      code: code.value,
      paper: paper.softCopy,
      photos: [...photos.value],
      createdAt: Date.now(),
    })

    await printsStore.save({
      ref: sessionRef.value,
      createdAt: Date.now(),
      printCopy: paper.printCopy,
      cut: currentFrame.cut,
    })
  }

  function reset() {
    sessionRef.value = null
    frame.value = null
    photos.value = []
    softCopy.value = null
    printCopy.value = null
    code.value = null
  }

  return {
    frame,
    photos,
    softCopy,
    printCopy,
    code,
    isComplete,
    begin,
    addPhoto,
    finish,
    reset,
  }
})
