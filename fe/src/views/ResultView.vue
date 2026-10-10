<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useObjectUrl } from '@vueuse/core'
import QRCode from 'qrcode'
import KioskButton from '@/components/KioskButton.vue'
import PrintCopiesStepper from '@/components/PrintCopiesStepper.vue'
import { usePrinter } from '@/composables/usePrinter'
import { copy } from '@/copy'
import { useSessionStore } from '@/stores/session'
import { shareUrl } from '@/stores/shortCodes'

const router = useRouter()
const sessionStore = useSessionStore()
const { isPrinting, failure: printFailure, print: sendToPrinter } = usePrinter()

const paperUrl = useObjectUrl(computed(() => sessionStore.printCopy))
const qrCodeUrl = ref<string | null>(null)
const sheets = ref(1)

function print() {
  void sendToPrinter(sessionStore.printCopy!, sheets.value, sessionStore.frame!.cut)
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
      <PrintCopiesStepper
        v-model="sheets"
        :label="copy.prints"
        :cut="sessionStore.frame?.cut ?? false"
      />

      <div class="w-full space-y-2">
        <KioskButton class="w-full" :disabled="isPrinting" @click="print">
          {{ isPrinting ? copy.printing : copy.print }}
        </KioskButton>
        <p v-if="printFailure" class="text-red-400">{{ copy.printFailed }}</p>
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
