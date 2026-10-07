import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { renderPaper } from '@/lib/render'
import { useFramesStore } from '@/stores/frames'
import { shareUrl, useShortCodesStore } from '@/stores/shortCodes'
import { useUploadsStore } from '@/stores/uploads'
import type { Frame } from '@/types'

/** The customer currently using the kiosk, from picking a frame to printing. */
export const useSessionStore = defineStore('session', () => {
  const framesStore = useFramesStore()
  const shortCodesStore = useShortCodesStore()
  const uploadsStore = useUploadsStore()

  /** Links the paper to its photos on the backend. */
  const sessionRef = ref<string | null>(null)
  const frame = ref<Frame | null>(null)
  const photos = ref<Blob[]>([])
  const paper = ref<Blob | null>(null)
  const code = ref<string | null>(null)

  const isComplete = computed(
    () => frame.value !== null && photos.value.length >= frame.value.slots.length,
  )

  function begin(selectedFrame: Frame) {
    sessionRef.value = crypto.randomUUID()
    frame.value = selectedFrame
    photos.value = []
    paper.value = null
    code.value = null
  }

  function addPhoto(photo: Blob) {
    photos.value = [...photos.value, photo]
  }

  /**
   * Render the paper and queue the session for upload. The short code is taken only now,
   * so sessions abandoned before this point never use one up.
   */
  async function finish() {
    const currentFrame = frame.value
    const frameImage = currentFrame ? framesStore.image(currentFrame.id) : undefined

    if (!sessionRef.value || !currentFrame || !frameImage) {
      throw new Error('There is no session to finish.')
    }

    code.value ??= shortCodesStore.take()

    paper.value = await renderPaper({
      frame: currentFrame,
      frameImage: frameImage.blob,
      photos: photos.value,
      shareUrl: code.value ? shareUrl(code.value) : null,
    })

    await uploadsStore.enqueue({
      ref: sessionRef.value,
      code: code.value,
      paper: paper.value,
      photos: [...photos.value],
      createdAt: Date.now(),
    })
  }

  function reset() {
    sessionRef.value = null
    frame.value = null
    photos.value = []
    paper.value = null
    code.value = null
  }

  return { frame, photos, paper, code, isComplete, begin, addPhoto, finish, reset }
})
