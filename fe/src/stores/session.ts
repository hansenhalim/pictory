import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { renderPaper } from '@/lib/render'
import { useAssignmentsStore } from '@/stores/assignments'
import { useFramesStore } from '@/stores/frames'
import { usePrintsStore } from '@/stores/prints'
import { shareUrl, useShortCodesStore } from '@/stores/shortCodes'
import { useUploadsStore } from '@/stores/uploads'
import type { Frame } from '@/types'

/** The customer currently using the kiosk, from picking a frame to printing. */
export const useSessionStore = defineStore('session', () => {
  const assignmentsStore = useAssignmentsStore()
  const framesStore = useFramesStore()
  const printsStore = usePrintsStore()
  const shortCodesStore = useShortCodesStore()
  const uploadsStore = useUploadsStore()

  /** Links the paper to its photos on the backend. */
  const sessionRef = ref<string | null>(null)
  const frame = ref<Frame | null>(null)
  const photos = ref<Blob[]>([])
  /** Each photo's behind-the-scenes clip, or null where none was made. */
  const clips = ref<(Blob | null)[]>([])
  /** Uploaded for the soft copy page; has no QR codes. */
  const softCopy = ref<Blob | null>(null)
  /** Sent to the printer; carries the QR codes. */
  const printCopy = ref<Blob | null>(null)
  const code = ref<string | null>(null)
  /** The QR card the operator assigned this session to. */
  const card = ref<string | null>(null)

  const isComplete = computed(
    () => frame.value !== null && photos.value.length >= frame.value.slots.length,
  )

  function begin(selectedFrame: Frame) {
    sessionRef.value = crypto.randomUUID()
    frame.value = selectedFrame
    photos.value = []
    clips.value = []
    softCopy.value = null
    printCopy.value = null
    code.value = null
    card.value = null
  }

  function addPhoto(photo: Blob, clip: Blob | null) {
    photos.value = [...photos.value, photo]
    clips.value = [...clips.value, clip]
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
      clips: [...clips.value],
      createdAt: Date.now(),
    })

    await printsStore.save({
      ref: sessionRef.value,
      createdAt: Date.now(),
      printCopy: paper.printCopy,
      cut: currentFrame.cut,
    })
  }

  /** Assign the finished session to a QR card, and offer that card again for the next session. */
  async function assignCard(label: string | null) {
    if (!sessionRef.value) {
      throw new Error('There is no session to assign.')
    }

    card.value = label

    if (label) {
      assignmentsStore.lastCard = label
    }

    await assignmentsStore.assign(sessionRef.value, label)
  }

  function reset() {
    sessionRef.value = null
    frame.value = null
    photos.value = []
    clips.value = []
    softCopy.value = null
    printCopy.value = null
    code.value = null
    card.value = null
  }

  return {
    frame,
    photos,
    softCopy,
    printCopy,
    code,
    card,
    isComplete,
    begin,
    addPhoto,
    finish,
    assignCard,
    reset,
  }
})
