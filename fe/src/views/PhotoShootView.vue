<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'
import { useRouter } from 'vue-router'
import { useObjectUrl } from '@vueuse/core'
import KioskButton from '@/components/KioskButton.vue'
import { copy } from '@/copy'
import { type Camera, DslrCamera, WebcamCamera } from '@/lib/camera'
import { ClipRecorder } from '@/lib/clip'
import { useFramesStore } from '@/stores/frames'
import { useSessionStore } from '@/stores/session'
import { useSettingsStore } from '@/stores/settings'

const router = useRouter()
const framesStore = useFramesStore()
const sessionStore = useSessionStore()
const { settings } = useSettingsStore()

const camera: Camera =
  settings.cameraSource === 'dslr'
    ? new DslrCamera({ mirror: settings.mirrorResult })
    : new WebcamCamera({ deviceId: settings.cameraDeviceId, mirror: settings.mirrorResult })
const video = useTemplateRef('video')

/** Shooting waits for the camera, so the first photo and clip are not taken from an empty stream. */
const isCameraReady = ref(false)
const isCameraUnavailable = ref(false)
const countdown = ref(0)
const isCountingDown = ref(false)
const hasCaptureFailed = ref(false)
const captured = ref<Blob | null>(null)
const capturedUrl = useObjectUrl(captured)
/** The behind-the-scenes clip of the countdown before `captured`. */
const capturedClip = ref<Blob | null>(null)
const isRecording = ref(false)
const isFinishing = ref(false)
const hasFinishFailed = ref(false)

let isLeaving = false
let stream: MediaStream | null = null
let recorder: ClipRecorder | null = null

const frame = computed(() => sessionStore.frame!)
const frameImage = computed(() => framesStore.image(frame.value.id))
const slotIndex = computed(() => Math.min(sessionStore.photos.length, frame.value.slots.length - 1))
const isLastSlot = computed(() => sessionStore.photos.length === frame.value.slots.length - 1)

/** Every placement of a photo shares its shape, so the first one stands in for the slot. */
const placement = computed(() => frame.value.slots[slotIndex.value]![0]!)

/** The part of the slot that lies on the paper; the rest of the photo is cut off when printed. */
const visibleArea = computed(() => {
  const { x, y, width, height } = placement.value
  const image = frameImage.value

  if (!image) {
    return placement.value
  }

  const left = Math.max(x, 0)
  const top = Math.max(y, 0)

  return {
    x: left,
    y: top,
    width: Math.min(x + width, image.width) - left,
    height: Math.min(y + height, image.height) - top,
  }
})

/** The preview has the visible area's shape, as large as fits above the buttons. */
const viewportStyle = computed(() => ({
  aspectRatio: `${visibleArea.value.width} / ${visibleArea.value.height}`,
  height: `min(78vh, calc(94vw * ${visibleArea.value.height / visibleArea.value.width}))`,
}))

/** The whole slot, shifted so its visible area lies exactly over the preview. */
const photoStyle = computed(() => {
  const { x, y, width, height } = placement.value
  const area = visibleArea.value

  return {
    left: `${((x - area.x) / area.width) * 100}%`,
    top: `${((y - area.y) / area.height) * 100}%`,
    width: `${(width / area.width) * 100}%`,
    height: `${(height / area.height) * 100}%`,
  }
})

/** The frame image, scaled and shifted so the visible area of it lies exactly over the preview. */
const overlayStyle = computed(() => {
  const { x, y, width, height } = visibleArea.value
  const image = frameImage.value

  return image
    ? {
        left: `${(-x / width) * 100}%`,
        top: `${(-y / height) * 100}%`,
        width: `${(image.width / width) * 100}%`,
        height: `${(image.height / height) * 100}%`,
      }
    : {}
})

/**
 * Each slot counts down this much longer than the one before, so the clips recorded during
 * the countdown differ in length, which post-processing uses for transitions.
 */
const COUNTDOWN_STEP_MS = 100

function wait(milliseconds: number) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds))
}

async function start() {
  isCountingDown.value = true
  hasCaptureFailed.value = false

  recorder =
    settings.recordClips && stream
      ? await ClipRecorder.start(stream, { mirror: settings.mirrorResult })
      : null

  if (recorder) {
    isRecording.value = true
    void recorder.clip.then(() => (isRecording.value = false))
  }

  const deadline =
    performance.now() + settings.countdownSeconds * 1000 + slotIndex.value * COUNTDOWN_STEP_MS

  // Timed against the deadline so the ticks do not drift. The extra time goes on the first
  // digit, keeping the rhythm of the last seconds before the shot the same for every slot.
  let remaining = deadline - performance.now()

  while (remaining > 0) {
    countdown.value = Math.min(settings.countdownSeconds, Math.ceil(remaining / 1000))
    await wait(remaining - (countdown.value - 1) * 1000)

    // The recorder may have started after the view began unmounting, so it is stopped here too.
    if (isLeaving) {
      void recorder?.stop()
      return
    }

    remaining = deadline - performance.now()
  }

  countdown.value = 0

  // Stopped as the shot is taken, so the clip lasts as long as the countdown.
  const clip = recorder?.stop() ?? Promise.resolve(null)
  recorder = null

  // A DSLR can refuse to shoot, e.g. when it cannot focus, so the customer may try again.
  try {
    const photo = await camera.capture()

    // Saving appears with the photo, so the photo waits for the clip saved along with it.
    capturedClip.value = await clip
    captured.value = photo
  } catch (error) {
    console.error(error)
    hasCaptureFailed.value = true
  } finally {
    isCountingDown.value = false
  }
}

function retake() {
  captured.value = null
  capturedClip.value = null
}

function save() {
  sessionStore.addPhoto(captured.value!, capturedClip.value)
  captured.value = null
  capturedClip.value = null
}

async function completeSession() {
  isFinishing.value = true
  hasFinishFailed.value = false

  try {
    await sessionStore.finish()
    await router.replace({ name: 'result' })
  } catch (error) {
    console.error(error)
    hasFinishFailed.value = true
  } finally {
    isFinishing.value = false
  }
}

function finish() {
  save()
  void completeSession()
}

onMounted(async () => {
  try {
    stream = await camera.start()

    if (video.value) {
      video.value.srcObject = stream
    }

    isCameraReady.value = true
  } catch (error) {
    console.error(error)
    isCameraUnavailable.value = true
  }
})

onBeforeUnmount(() => {
  isLeaving = true
  void recorder?.stop()
  camera.stop()
})
</script>

<template>
  <main class="flex h-full flex-col items-center justify-center gap-[4vh] px-8 py-6">
    <div class="relative overflow-hidden rounded bg-black" :style="viewportStyle">
      <div class="absolute" :style="photoStyle">
        <video
          ref="video"
          autoplay
          muted
          playsinline
          class="absolute inset-0 size-full object-cover"
          :class="{ '-scale-x-100': settings.mirrorPreview }"
        />

        <img
          v-if="capturedUrl"
          :src="capturedUrl"
          alt=""
          class="absolute inset-0 size-full object-cover"
        />
      </div>

      <img
        v-if="frameImage"
        :src="frameImage.url"
        alt=""
        class="pointer-events-none absolute max-w-none"
        :style="overlayStyle"
        draggable="false"
      />

      <div
        v-if="isCountingDown && countdown > 0"
        class="absolute inset-0 flex items-center justify-center text-[30vh] font-semibold text-white drop-shadow-lg"
      >
        {{ countdown }}
      </div>

      <div
        v-if="isRecording"
        class="absolute top-4 right-4 flex items-center gap-2 rounded-full bg-black/50 px-3 py-1 text-lg font-semibold text-white"
      >
        <span class="size-3 rounded-full bg-red-500" />
        {{ copy.recording }}
      </div>

      <div
        v-if="isCameraUnavailable"
        class="absolute inset-0 flex items-center justify-center text-2xl text-neutral-400"
      >
        {{ copy.cameraUnavailable }}
      </div>
    </div>

    <div class="flex min-h-20 items-center justify-center gap-4">
      <p v-if="isFinishing" class="text-2xl text-neutral-400">{{ copy.processing }}</p>

      <template v-else-if="sessionStore.isComplete">
        <p v-if="hasFinishFailed" class="text-xl text-red-400">{{ copy.processingFailed }}</p>
        <KioskButton @click="completeSession">{{ copy.finish }}</KioskButton>
      </template>

      <template v-else-if="captured">
        <KioskButton variant="secondary" @click="retake">{{ copy.retake }}</KioskButton>
        <KioskButton v-if="isLastSlot" @click="finish">{{ copy.finish }}</KioskButton>
        <KioskButton v-else @click="save">{{ copy.save }}</KioskButton>
      </template>

      <template v-else-if="!isCountingDown">
        <p v-if="hasCaptureFailed" class="text-xl text-red-400">{{ copy.captureFailed }}</p>
        <KioskButton :disabled="!isCameraReady" @click="start">{{ copy.start }}</KioskButton>
      </template>
    </div>
  </main>
</template>
