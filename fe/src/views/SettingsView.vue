<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useIntervalFn, useNow, useOnline } from '@vueuse/core'
import { checkAgent } from '@/lib/agent'
import PrintThumbnail from '@/components/PrintThumbnail.vue'
import ReprintDialog from '@/components/ReprintDialog.vue'
import { chooseDslr, DslrCamera, listCameras, listDslrs } from '@/lib/camera'
import { canRecordClips } from '@/lib/clip'
import { useDeviceStore } from '@/stores/device'
import { useFramesStore } from '@/stores/frames'
import { usePrintsStore } from '@/stores/prints'
import { useSettingsStore } from '@/stores/settings'
import { useShortCodesStore } from '@/stores/shortCodes'
import { useUploadsStore } from '@/stores/uploads'
import type { PrintRecord } from '@/types'

const router = useRouter()
const framesStore = useFramesStore()
const settingsStore = useSettingsStore()
const shortCodesStore = useShortCodesStore()
const uploadsStore = useUploadsStore()
const deviceStore = useDeviceStore()
const printsStore = usePrintsStore()
const isOnline = useOnline()
const now = useNow({ scheduler: (tick) => useIntervalFn(tick, 1000) })

const cameras = ref<MediaDeviceInfo[]>([])
const isClipRecordingSupported = canRecordClips()
const cameraError = ref<string | null>(null)
const dslrs = ref<string[]>([])
const dslrStatus = ref<{ isReady: boolean; message: string } | null>(null)
const isTestingDslr = ref(false)
const printerStatus = ref<{ isReady: boolean; message: string } | null>(null)
const isTestingPrinter = ref(false)
const isRetryingUploads = ref(false)
const selectedPrint = ref<PrintRecord | null>(null)
const isConfirmingClear = ref(false)

let clearConfirmationTimer: ReturnType<typeof setTimeout> | undefined

const storageUsed = computed(() => {
  let size = deviceStore.storageUsedBytes

  if (size === null) {
    return null
  }

  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let unit = 0

  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024
    unit++
  }

  return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
})

/** Clearing takes a second tap within a few seconds, so a stray tap cannot wipe the history. */
async function clearHistory() {
  if (!isConfirmingClear.value) {
    isConfirmingClear.value = true
    clearConfirmationTimer = setTimeout(() => (isConfirmingClear.value = false), 4000)
    return
  }

  clearTimeout(clearConfirmationTimer)
  isConfirmingClear.value = false
  await printsStore.clear()
  await deviceStore.refreshStorageUsage()
}

const waitingSessions = computed(() => {
  const count = uploadsStore.pendingCount

  return `${count} ${count === 1 ? 'session is' : 'sessions are'} saved on this kiosk, waiting to upload.`
})

const nextRetryLabel = computed(() => {
  if (isRetryingUploads.value || uploadsStore.nextRetryAt === null) {
    return 'Retrying…'
  }

  const seconds = Math.max(0, Math.ceil((uploadsStore.nextRetryAt - now.value.getTime()) / 1000))

  return seconds < 60
    ? `Retrying automatically in ${seconds}s.`
    : `Retrying automatically in ${Math.ceil(seconds / 60)} min.`
})

async function retryUploads() {
  isRetryingUploads.value = true

  try {
    await uploadsStore.retryNow()
  } finally {
    isRetryingUploads.value = false
  }
}

const cameraSources = [
  { value: 'webcam', label: 'Webcam' },
  { value: 'dslr', label: 'DSLR' },
] as const

const themes = [
  { value: 'dark', label: 'Dark' },
  { value: 'light', label: 'Light' },
] as const

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

async function loadDslrs() {
  try {
    dslrs.value = await listDslrs()
  } catch (error) {
    dslrStatus.value = { isReady: false, message: errorMessage(error) }
  }
}

/** Closing Chrome's device list without picking a camera is not worth reporting. */
async function chooseCamera() {
  dslrStatus.value = null

  try {
    await chooseDslr()
  } catch (error) {
    if (!(error instanceof DOMException && error.name === 'NotFoundError')) {
      dslrStatus.value = { isReady: false, message: errorMessage(error) }
    }
  }

  await loadDslrs()
}

/** Connect and take one live view frame, which is what the shoot screen needs first. */
async function testDslr() {
  isTestingDslr.value = true
  dslrStatus.value = null

  const camera = new DslrCamera({ mirror: false })

  try {
    await camera.start()
    dslrStatus.value = { isReady: true, message: 'The camera is ready.' }
  } catch (error) {
    dslrStatus.value = {
      isReady: false,
      message: `Could not start the camera. ${errorMessage(error)}`,
    }
  } finally {
    camera.stop()
    isTestingDslr.value = false
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
  void deviceStore.requestPersistentStorage()
  void shortCodesStore.topUp().catch(() => {})
  void router.push({ name: 'frames' })
}

onMounted(() => {
  void printsStore.load()
  void deviceStore.refreshStorageUsage()
  void deviceStore.requestPersistentStorage()
  void framesStore.sync().catch(() => {})
  void loadCameras()
  void loadDslrs()
})
</script>

<template>
  <main class="mx-auto max-w-4xl space-y-6 px-6 py-10 select-text">
    <header class="flex items-center justify-between gap-4">
      <div>
        <p class="text-lg font-semibold tracking-wide">pictory.photo</p>
        <h1 class="text-neutral-400">Kiosk settings</h1>
      </div>

      <div class="flex items-center gap-3">
        <button
          v-if="deviceStore.installPrompt"
          type="button"
          class="rounded-lg bg-neutral-800 px-6 py-3 font-semibold"
          @click="deviceStore.install()"
        >
          Install kiosk app
        </button>
        <button
          type="button"
          class="rounded-lg bg-neutral-50 px-6 py-3 font-semibold text-neutral-950 disabled:opacity-40"
          :disabled="framesStore.enabledFrames.length === 0"
          @click="launch"
        >
          Launch
        </button>
      </div>
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
          :class="positionOf(frame.id) ? 'ring-neutral-50' : 'opacity-50 ring-transparent'"
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
            class="absolute top-2 left-2 flex size-7 items-center justify-center rounded-full bg-neutral-50 text-sm font-semibold text-neutral-950"
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
      <div class="flex items-center justify-between gap-4">
        <h2 class="font-semibold">Camera</h2>

        <div class="flex rounded-lg bg-neutral-800 p-1">
          <button
            v-for="source in cameraSources"
            :key="source.value"
            type="button"
            class="rounded-md px-6 py-2 transition"
            :class="
              settingsStore.settings.cameraSource === source.value
                ? 'bg-neutral-600 font-semibold'
                : 'text-neutral-400'
            "
            @click="settingsStore.settings.cameraSource = source.value"
          >
            {{ source.label }}
          </button>
        </div>
      </div>

      <label v-if="settingsStore.settings.cameraSource === 'webcam'" class="block space-y-1">
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

      <div v-else class="space-y-1">
        <span class="text-sm text-neutral-400">Device</span>
        <div class="flex gap-3">
          <p class="min-w-0 flex-1 truncate rounded bg-neutral-800 px-3 py-2">
            {{ dslrs.length > 0 ? dslrs.join(', ') : 'No allowed camera is connected' }}
          </p>
          <button type="button" class="rounded bg-neutral-700 px-4 py-2" @click="chooseCamera">
            Choose
          </button>
          <button
            type="button"
            class="rounded bg-neutral-700 px-4 py-2 disabled:opacity-40"
            :disabled="isTestingDslr"
            @click="testDslr"
          >
            {{ isTestingDslr ? 'Testing…' : 'Test camera' }}
          </button>
        </div>
        <p
          v-if="dslrStatus"
          class="text-sm"
          :class="dslrStatus.isReady ? 'text-green-400' : 'text-red-400'"
        >
          {{ dslrStatus.message }}
        </p>
      </div>

      <label class="flex items-center gap-3">
        <input v-model="settingsStore.settings.mirror" type="checkbox" class="size-5" />
        <span>Mirror the preview and photos</span>
      </label>

      <div class="space-y-1">
        <label class="flex items-center gap-3">
          <input v-model="settingsStore.settings.recordClips" type="checkbox" class="size-5" />
          <span>Record a behind-the-scenes clip of each countdown</span>
        </label>
        <p
          v-if="settingsStore.settings.recordClips && !isClipRecordingSupported"
          class="text-sm text-red-400"
        >
          This browser cannot record AV1 video, so no clips are made.
        </p>
      </div>

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

    <section class="flex items-center justify-between gap-4 rounded-lg bg-neutral-900 p-6">
      <h2 class="font-semibold">Theme</h2>

      <div class="flex rounded-lg bg-neutral-800 p-1">
        <button
          v-for="theme in themes"
          :key="theme.value"
          type="button"
          class="rounded-md px-6 py-2 transition"
          :class="
            settingsStore.settings.theme === theme.value
              ? 'bg-neutral-600 font-semibold'
              : 'text-neutral-400'
          "
          @click="settingsStore.settings.theme = theme.value"
        >
          {{ theme.label }}
        </button>
      </div>
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
        <p class="text-2xl font-semibold">{{ shortCodesStore.pool.length }}</p>
        <p class="text-sm text-neutral-400">QR codes in reserve</p>
      </div>
      <div>
        <p
          class="text-2xl font-semibold"
          :class="deviceStore.isStoragePersistent ? 'text-green-400' : 'text-amber-400'"
        >
          {{ deviceStore.isStoragePersistent ? 'Protected' : 'Not protected' }}
        </p>
        <p class="text-sm text-neutral-400">Storage</p>
      </div>
      <p v-if="!deviceStore.isStoragePersistent" class="col-span-3 text-sm text-amber-300/80">
        Chrome may clear the print history and pending uploads when the disk runs low. Install the
        kiosk app to keep them.
      </p>
    </section>

    <section
      v-if="printsStore.total > 0 || printsStore.saveError || uploadsStore.lastError"
      class="space-y-4 rounded-lg bg-neutral-900 p-6"
    >
      <div class="flex items-center justify-between gap-4">
        <div>
          <h2 class="font-semibold">Gallery</h2>
          <p class="text-sm text-neutral-400">
            {{ printsStore.total }} {{ printsStore.total === 1 ? 'print' : 'prints' }}
            <template v-if="storageUsed">· {{ storageUsed }} stored on this kiosk</template>
          </p>
        </div>

        <button
          v-if="printsStore.total > 0"
          type="button"
          class="rounded px-4 py-2 text-sm"
          :class="isConfirmingClear ? 'bg-red-700 font-semibold' : 'bg-neutral-800'"
          @click="clearHistory"
        >
          {{
            isConfirmingClear ? `Tap again to delete ${printsStore.total} prints` : 'Clear history'
          }}
        </button>
      </div>

      <div
        v-if="uploadsStore.lastError"
        class="flex items-start justify-between gap-4 rounded-lg border border-red-500/40 bg-red-950/60 p-4"
      >
        <div class="space-y-1">
          <h3 class="font-semibold text-red-300">Uploads are failing</h3>
          <p class="text-red-100">{{ uploadsStore.lastError }}</p>
          <p class="text-sm text-red-300/80">{{ waitingSessions }} {{ nextRetryLabel }}</p>
        </div>

        <button
          type="button"
          class="shrink-0 rounded bg-red-700 px-4 py-2 font-semibold disabled:opacity-40"
          :disabled="isRetryingUploads"
          @click="retryUploads"
        >
          Retry now
        </button>
      </div>

      <p v-if="printsStore.saveError" class="text-sm text-amber-400">
        The latest print could not be kept for reprinting. {{ printsStore.saveError }}
      </p>

      <div class="grid grid-cols-4 gap-4 sm:grid-cols-6">
        <PrintThumbnail
          v-for="print in printsStore.prints"
          :key="print.ref"
          :print="print"
          @click="selectedPrint = print"
        />
      </div>

      <button
        v-if="printsStore.prints.length < printsStore.total"
        type="button"
        class="w-full rounded bg-neutral-800 py-2"
        @click="printsStore.loadMore()"
      >
        Show more
      </button>
    </section>

    <ReprintDialog v-if="selectedPrint" :print="selectedPrint" @close="selectedPrint = null" />
  </main>
</template>
