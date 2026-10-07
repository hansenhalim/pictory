<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useOnline } from '@vueuse/core'
import { checkAgent } from '@/lib/agent'
import { listCameras } from '@/lib/camera'
import { useFramesStore } from '@/stores/frames'
import { useSettingsStore } from '@/stores/settings'
import { useShortCodesStore } from '@/stores/shortCodes'
import { useUploadsStore } from '@/stores/uploads'

const router = useRouter()
const framesStore = useFramesStore()
const settingsStore = useSettingsStore()
const shortCodesStore = useShortCodesStore()
const uploadsStore = useUploadsStore()
const isOnline = useOnline()

const cameras = ref<MediaDeviceInfo[]>([])
const cameraError = ref<string | null>(null)
const printerStatus = ref<{ isReady: boolean; message: string } | null>(null)
const isTestingPrinter = ref(false)

const countdownSeconds = computed({
  get: () => settingsStore.settings.countdownSeconds,
  set: (seconds: number) => {
    settingsStore.settings.countdownSeconds = Math.min(10, Math.max(1, Math.round(seconds) || 3))
  },
})

function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error)
}

function positionOf(frameId: number): number | null {
  const index = settingsStore.settings.enabledFrameIds.indexOf(frameId)

  return index === -1 ? null : index + 1
}

async function loadCameras() {
  try {
    cameras.value = await listCameras()
  } catch (error) {
    cameraError.value = errorMessage(error)
  }
}

async function testPrinter() {
  isTestingPrinter.value = true
  printerStatus.value = null

  try {
    const health = await checkAgent(settingsStore.settings.agentUrl)

    printerStatus.value = {
      isReady: health.ok,
      message: health.ok
        ? `${health.printer} is ready.`
        : `${health.printer} is not ready. ${health.status}`,
    }
  } catch (error) {
    printerStatus.value = {
      isReady: false,
      message: `Could not reach the print agent. ${errorMessage(error)}`,
    }
  } finally {
    isTestingPrinter.value = false
  }
}

function launch() {
  void shortCodesStore.topUp().catch(() => {})
  void router.push({ name: 'frames' })
}

onMounted(() => {
  void framesStore.sync().catch(() => {})
  void loadCameras()
})
</script>

<template>
  <main class="mx-auto max-w-4xl space-y-6 px-6 py-10 select-text">
    <header class="flex items-center justify-between gap-4">
      <div>
        <p class="text-lg font-semibold tracking-wide">pictory.photo</p>
        <h1 class="text-neutral-400">Kiosk settings</h1>
      </div>

      <button
        type="button"
        class="rounded-lg bg-white px-6 py-3 font-semibold text-neutral-950 disabled:opacity-40"
        :disabled="framesStore.enabledFrames.length === 0"
        @click="launch"
      >
        Launch
      </button>
    </header>

    <section class="space-y-4 rounded-lg bg-neutral-900 p-6">
      <div class="flex items-baseline justify-between gap-4">
        <h2 class="font-semibold">Frames</h2>
        <p class="text-sm text-neutral-400">
          <template v-if="framesStore.isSyncing">Syncing…</template>
          <template v-else-if="framesStore.syncError"
            >Using cached frames. {{ framesStore.syncError }}</template
          >
          <template v-else>Tap frames to offer them, in the order you want them shown.</template>
        </p>
      </div>

      <p v-if="framesStore.frames.length === 0" class="text-neutral-400">
        No frames yet. Add them in the admin panel under Frames.
      </p>

      <div class="grid grid-cols-3 gap-4 sm:grid-cols-5">
        <button
          v-for="frame in framesStore.frames"
          :key="frame.id"
          type="button"
          class="relative rounded-lg p-1 ring-2 transition"
          :class="positionOf(frame.id) ? 'ring-white' : 'opacity-50 ring-transparent'"
          @click="settingsStore.toggleFrame(frame.id)"
        >
          <img
            :src="framesStore.image(frame.id)?.url"
            :alt="`Frame ${frame.id}`"
            class="w-full rounded bg-white"
            draggable="false"
          />
          <span
            v-if="positionOf(frame.id)"
            class="absolute top-2 left-2 flex size-7 items-center justify-center rounded-full bg-white text-sm font-semibold text-neutral-950"
          >
            {{ positionOf(frame.id) }}
          </span>
          <span
            v-if="frame.cut"
            class="absolute top-2 right-2 rounded bg-neutral-950/80 px-2 text-xs"
            >Cut</span
          >
        </button>
      </div>
    </section>

    <section class="space-y-4 rounded-lg bg-neutral-900 p-6">
      <h2 class="font-semibold">Camera</h2>

      <label class="block space-y-1">
        <span class="text-sm text-neutral-400">Device</span>
        <select
          v-model="settingsStore.settings.cameraDeviceId"
          class="w-full rounded bg-neutral-800 px-3 py-2"
        >
          <option value="">Default camera</option>
          <option v-for="camera in cameras" :key="camera.deviceId" :value="camera.deviceId">
            {{ camera.label || camera.deviceId }}
          </option>
        </select>
        <span v-if="cameraError" class="text-sm text-red-400">{{ cameraError }}</span>
      </label>

      <label class="flex items-center gap-3">
        <input v-model="settingsStore.settings.mirror" type="checkbox" class="size-5" />
        <span>Mirror the preview and photos</span>
      </label>

      <label class="block space-y-1">
        <span class="text-sm text-neutral-400">Countdown (seconds)</span>
        <input
          v-model.number="countdownSeconds"
          type="number"
          min="1"
          max="10"
          class="w-24 rounded bg-neutral-800 px-3 py-2"
        />
      </label>
    </section>

    <section class="space-y-4 rounded-lg bg-neutral-900 p-6">
      <h2 class="font-semibold">Printer</h2>

      <label class="block space-y-1">
        <span class="text-sm text-neutral-400">Print agent URL</span>
        <div class="flex gap-3">
          <input
            v-model.trim="settingsStore.settings.agentUrl"
            type="url"
            class="min-w-0 flex-1 rounded bg-neutral-800 px-3 py-2"
          />
          <button
            type="button"
            class="rounded bg-neutral-700 px-4 py-2 disabled:opacity-40"
            :disabled="isTestingPrinter"
            @click="testPrinter"
          >
            {{ isTestingPrinter ? 'Testing…' : 'Test printer' }}
          </button>
        </div>
      </label>

      <p
        v-if="printerStatus"
        class="text-sm"
        :class="printerStatus.isReady ? 'text-green-400' : 'text-red-400'"
      >
        {{ printerStatus.message }}
      </p>
    </section>

    <section class="grid grid-cols-3 gap-4 rounded-lg bg-neutral-900 p-6 text-center">
      <div>
        <p class="text-2xl font-semibold" :class="isOnline ? 'text-green-400' : 'text-red-400'">
          {{ isOnline ? 'Online' : 'Offline' }}
        </p>
        <p class="text-sm text-neutral-400">Connection</p>
      </div>
      <div>
        <p class="text-2xl font-semibold">{{ uploadsStore.pendingCount }}</p>
        <p class="text-sm text-neutral-400">Sessions waiting to upload</p>
      </div>
      <div>
        <p class="text-2xl font-semibold">{{ shortCodesStore.pool.length }}</p>
        <p class="text-sm text-neutral-400">QR codes in reserve</p>
      </div>
    </section>
  </main>
</template>
