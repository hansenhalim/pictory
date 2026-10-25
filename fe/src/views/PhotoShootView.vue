<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'
import { useRouter } from 'vue-router'
import { useObjectUrl } from '@vueuse/core'
import KioskButton from '@/components/KioskButton.vue'
import { copy } from '@/copy'
import { type Camera, DslrCamera, WebcamCamera } from '@/lib/camera'
import { useFramesStore } from '@/stores/frames'
import { useSessionStore } from '@/stores/session'
import { useSettingsStore } from '@/stores/settings'

const router = useRouter()
const framesStore = useFramesStore()
const sessionStore = useSessionStore()
const { settings } = useSettingsStore()

const camera: Camera =
  settings.cameraSource === 'dslr'
    ? new DslrCamera({ mirror: settings.mirror })
    : new WebcamCamera({ deviceId: settings.cameraDeviceId, mirror: settings.mirror })
const video = useTemplateRef('video')

const isCameraUnavailable = ref(false)
const countdown = ref(0)
const isCountingDown = ref(false)
const hasCaptureFailed = ref(false)
const captured = ref<Blob | null>(null)
const capturedUrl = useObjectUrl(captured)
const isFinishing = ref(false)
const hasFinishFailed = ref(false)

let isLeaving = false

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

function wait(milliseconds: number) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds))
}

async function start() {
  isCountingDown.value = true
  hasCaptureFailed.value = false

  for (let seconds = settings.countdownSeconds; seconds > 0; seconds--) {
    countdown.value = seconds
    await wait(1000)

    if (isLeaving) {
      return
    }
  }

  countdown.value = 0

  // A DSLR can refuse to shoot, e.g. when it cannot focus, so the customer may try again.
  try {
    captured.value = await camera.capture()
  } catch (error) {
    console.error(error)
    hasCaptureFailed.value = true
  } finally {
    isCountingDown.value = false
  }
}

function retake() {
  captured.value = null
}

function save() {
  sessionStore.addPhoto(captured.value!)
  captured.value = null
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
    const stream = await camera.start()

    if (video.value) {
      video.value.srcObject = stream
    }
  } catch (error) {
    console.error(error)
    isCameraUnavailable.value = true
  }
})

onBeforeUnmount(() => {
  isLeaving = true
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
          :class="{ '-scale-x-100': settings.mirror }"
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
        <KioskButton :disabled="isCameraUnavailable" @click="start">{{ copy.start }}</KioskButton>
      </template>
    </div>
  </main>
</template>
