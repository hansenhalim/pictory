<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import PaperScreen from '@/components/PaperScreen.vue'
import PrintThumbnail from '@/components/PrintThumbnail.vue'
import { copy } from '@/copy'
import { useAssignmentsStore } from '@/stores/assignments'
import { useDeviceStore } from '@/stores/device'
import { usePrintsStore } from '@/stores/prints'
import type { PrintRecord } from '@/types'

const assignmentsStore = useAssignmentsStore()
const deviceStore = useDeviceStore()
const printsStore = usePrintsStore()

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

async function assignCard(print: PrintRecord, card: string | null) {
  try {
    await assignmentsStore.assign(print.ref, card)
  } catch (error) {
    console.error(error)
  }
}

onMounted(() => {
  void printsStore.load()
  void deviceStore.refreshStorageUsage()
})
</script>

<template>
  <main class="mx-auto max-w-4xl space-y-6 px-6 py-10 select-text">
    <header class="flex items-center justify-between gap-4">
      <div>
        <p class="text-lg font-semibold tracking-wide">pictory.photo</p>
        <h1 class="text-neutral-400">
          Gallery · {{ printsStore.total }} {{ printsStore.total === 1 ? 'print' : 'prints' }}
          <template v-if="storageUsed">· {{ storageUsed }} stored on this kiosk</template>
        </h1>
      </div>

      <div class="flex items-center gap-3">
        <button
          v-if="printsStore.total > 0"
          type="button"
          class="rounded-lg px-6 py-3"
          :class="isConfirmingClear ? 'bg-red-700 font-semibold' : 'bg-neutral-800'"
          @click="clearHistory"
        >
          {{
            isConfirmingClear ? `Tap again to delete ${printsStore.total} prints` : 'Clear history'
          }}
        </button>
        <RouterLink
          :to="{ name: 'frames' }"
          class="rounded-lg bg-neutral-50 px-6 py-3 font-semibold text-neutral-950"
        >
          Back
        </RouterLink>
      </div>
    </header>

    <p v-if="printsStore.saveError" class="text-sm text-amber-400">
      The latest print could not be kept for reprinting. {{ printsStore.saveError }}
    </p>

    <p v-if="printsStore.total === 0" class="text-neutral-400">
      No prints yet. Every paper printed on this kiosk is kept here for reprinting.
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

    <div v-if="selectedPrint" class="fixed inset-0 z-10 bg-neutral-950 select-none">
      <PaperScreen
        :soft-copy="selectedPrint.softCopy"
        :print-copy="selectedPrint.printCopy"
        :cut="selectedPrint.cut"
        :code="selectedPrint.code"
        :card="selectedPrint.card ?? null"
        :card-error="selectedPrint.cardError"
        @assign-card="assignCard(selectedPrint, $event)"
      >
        <button type="button" class="text-xl text-neutral-400" @click="selectedPrint = null">
          {{ copy.close }}
        </button>
      </PaperScreen>
    </div>
  </main>
</template>
