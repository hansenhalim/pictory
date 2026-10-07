<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'
import { useRouter } from 'vue-router'
import { useObjectUrl } from '@vueuse/core'
import KioskButton from '@/components/KioskButton.vue'
import { copy } from '@/copy'
import { WebcamCamera } from '@/lib/camera'
import { useFramesStore } from '@/stores/frames'
import { useSessionStore } from '@/stores/session'
import { useSettingsStore } from '@/stores/settings'

const router = useRouter()
const framesStore = useFramesStore()
const sessionStore = useSessionStore()
const { settings } = useSettingsStore()

const camera = new WebcamCamera({ deviceId: settings.cameraDeviceId, mirror: settings.mirror })
const video = useTemplateRef('video')

const isCameraUnavailable = ref(false)
const countdown = ref(0)
const isCountingDown = ref(false)
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

/** The preview has the slot's shape, as large as fits above the buttons. */
const viewportStyle = computed(() => ({
  aspectRatio: `${placement.value.width} / ${placement.value.height}`,
  height: `min(78vh, calc(94vw * ${placement.value.height / placement.value.width}))`,
}))

/** The frame image, scaled and shifted so the slot's area of it lies exactly over the preview. */
const overlayStyle = computed(() => {
  const { x, y, width, height } = placement.value
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

  for (let seconds = settings.countdownSeconds; seconds > 0; seconds--) {
    countdown.value = seconds
    await wait(1000)

    if (isLeaving) {
      return
    }
  }

  countdown.value = 0
  captured.value = await camera.capture()
  isCountingDown.value = false
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

      <KioskButton v-else-if="!isCountingDown" :disabled="isCameraUnavailable" @click="start">
        {{ copy.start }}
      </KioskButton>
    </div>
  </main>
</template>
