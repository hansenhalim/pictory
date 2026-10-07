<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useObjectUrl } from '@vueuse/core'
import QRCode from 'qrcode'
import KioskButton from '@/components/KioskButton.vue'
import { copy } from '@/copy'
import { printPaper } from '@/lib/agent'
import { useSessionStore } from '@/stores/session'
import { useSettingsStore } from '@/stores/settings'
import { shareUrl } from '@/stores/shortCodes'

const MAX_SHEETS = 20

const router = useRouter()
const sessionStore = useSessionStore()
const { settings } = useSettingsStore()

const paperUrl = useObjectUrl(computed(() => sessionStore.paper))
const qrCodeUrl = ref<string | null>(null)
const sheets = ref(1)
const isPrinting = ref(false)
const hasPrintFailed = ref(false)

/** Customers count prints, and a cut sheet makes two strips. */
const prints = computed(() => sheets.value * (sessionStore.frame?.cut ? 2 : 1))

async function print() {
  isPrinting.value = true
  hasPrintFailed.value = false

  try {
    await printPaper(settings.agentUrl, sessionStore.paper!, sheets.value, sessionStore.frame!.cut)
  } catch (error) {
    console.error(error)
    hasPrintFailed.value = true
  } finally {
    isPrinting.value = false
  }
}

async function restart() {
  await router.replace({ name: 'frames' })
  sessionStore.reset()
}

onMounted(async () => {
  if (sessionStore.code) {
    qrCodeUrl.value = await QRCode.toDataURL(shareUrl(sessionStore.code), {
      errorCorrectionLevel: 'Q',
      margin: 4,
      width: 480,
    })
  }
})
</script>

<template>
  <main class="relative flex h-full items-center justify-center gap-[5vw] px-8 py-12">
    <img
      v-if="paperUrl"
      :src="paperUrl"
      alt=""
      class="max-h-[84vh] max-w-[55vw] rounded bg-white shadow-lg"
      draggable="false"
    />

    <section class="flex w-88 flex-col items-center gap-8 text-center">
      <div class="space-y-3">
        <p class="tracking-wide text-neutral-400 uppercase">{{ copy.prints }}</p>
        <div class="flex items-center gap-6">
          <button
            type="button"
            class="size-16 rounded-full bg-neutral-800 text-3xl disabled:opacity-30"
            :disabled="sheets <= 1"
            @click="sheets--"
          >
            −
          </button>
          <span class="w-20 text-5xl font-semibold tabular-nums">{{ prints }}</span>
          <button
            type="button"
            class="size-16 rounded-full bg-neutral-800 text-3xl disabled:opacity-30"
            :disabled="sheets >= MAX_SHEETS"
            @click="sheets++"
          >
            +
          </button>
        </div>
      </div>

      <div class="w-full space-y-2">
        <KioskButton class="w-full" :disabled="isPrinting" @click="print">
          {{ isPrinting ? copy.printing : copy.print }}
        </KioskButton>
        <p v-if="hasPrintFailed" class="text-red-400">{{ copy.printFailed }}</p>
      </div>

      <div v-if="qrCodeUrl" class="space-y-3">
        <img :src="qrCodeUrl" alt="" class="mx-auto size-44 rounded" draggable="false" />
        <p>{{ copy.scanForSoftCopy }}</p>
      </div>
      <p v-else class="text-neutral-400">{{ copy.softCopyUnavailable }}</p>

      <button type="button" class="text-xl text-neutral-400" @click="restart">
        {{ copy.restart }}
      </button>
    </section>

    <p class="absolute bottom-6 text-neutral-500">pictory.photo</p>
  </main>
</template>
