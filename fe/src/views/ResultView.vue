<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useObjectUrl } from '@vueuse/core'
import QRCode from 'qrcode'
import CardKeypad from '@/components/CardKeypad.vue'
import KioskButton from '@/components/KioskButton.vue'
import PrintCopiesStepper from '@/components/PrintCopiesStepper.vue'
import { usePrinter } from '@/composables/usePrinter'
import { copy } from '@/copy'
import { nextCardLabel } from '@/lib/cards'
import { useAssignmentsStore } from '@/stores/assignments'
import { useSessionStore } from '@/stores/session'
import { useSettingsStore } from '@/stores/settings'
import { shareUrl } from '@/stores/shortCodes'

const router = useRouter()
const sessionStore = useSessionStore()
const assignmentsStore = useAssignmentsStore()
const { settings } = useSettingsStore()
const { isPrinting, failure: printFailure, print: sendToPrinter } = usePrinter()

const paperUrl = useObjectUrl(computed(() => sessionStore.printCopy))
const qrCodeUrl = ref<string | null>(null)
const sheets = ref(1)
const isKeypadOpen = ref(false)
const isQrCodeEnlarged = ref(false)

/** The choice is kept, so the next session opens on the link the operator last used. */
const softCopyLinks = [
  { value: 'qr', label: copy.qr },
  { value: 'card', label: copy.card },
] as const

/** A returning customer keeps their card, and a new one gets the next card from the deck. */
const cardSuggestions = computed(() => {
  const lastCard = assignmentsStore.lastCard

  return lastCard
    ? [
        { card: lastCard, hint: 'Previous' },
        { card: nextCardLabel(lastCard), hint: 'Next' },
      ]
    : []
})

function print() {
  void sendToPrinter(sessionStore.printCopy!, sheets.value, sessionStore.frame!.cut)
}

async function assignCard(card: string | null) {
  isKeypadOpen.value = false

  try {
    await sessionStore.assignCard(card)
  } catch (error) {
    console.error(error)
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
      // Large enough to stay sharp when enlarged to fill the screen.
      width: 1080,
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

      <div class="w-full space-y-4">
        <div class="mx-auto flex w-fit rounded-lg bg-neutral-900 p-1">
          <button
            v-for="link in softCopyLinks"
            :key="link.value"
            type="button"
            class="rounded-md px-8 py-2 text-lg transition"
            :class="
              settings.softCopyLink === link.value
                ? 'bg-neutral-700 font-semibold text-white'
                : 'text-neutral-400'
            "
            @click="settings.softCopyLink = link.value"
          >
            {{ link.label }}
          </button>
        </div>

        <div class="flex h-56 flex-col items-center justify-center">
          <KioskButton
            v-if="settings.softCopyLink === 'card'"
            variant="secondary"
            class="w-full"
            @click="isKeypadOpen = true"
          >
            <template v-if="sessionStore.card">
              {{ copy.card }} <span class="tabular-nums">{{ sessionStore.card }}</span> ✓
            </template>
            <template v-else>{{ copy.enterCard }}</template>
          </KioskButton>

          <button
            v-else-if="qrCodeUrl"
            type="button"
            class="space-y-3"
            @click="isQrCodeEnlarged = true"
          >
            <img :src="qrCodeUrl" alt="" class="mx-auto size-44 rounded" draggable="false" />
            <span class="block">{{ copy.scanForSoftCopy }}</span>
          </button>
          <p v-else class="text-neutral-400">{{ copy.softCopyUnavailable }}</p>
        </div>
      </div>

      <button type="button" class="text-xl text-neutral-400" @click="restart">
        {{ copy.restart }}
      </button>
    </section>

    <p class="absolute bottom-6 text-neutral-500">pictory.photo</p>

    <div
      v-if="isQrCodeEnlarged && qrCodeUrl"
      class="fixed inset-0 z-20 flex flex-col items-center justify-center gap-6 bg-neutral-950/95"
      @click="isQrCodeEnlarged = false"
    >
      <img :src="qrCodeUrl" alt="" class="size-[min(75vh,90vw)] rounded" draggable="false" />
      <p class="text-2xl">{{ copy.scanForSoftCopy }}</p>
    </div>

    <CardKeypad
      v-if="isKeypadOpen"
      :current="sessionStore.card"
      :suggestions="cardSuggestions"
      @submit="assignCard"
      @close="isKeypadOpen = false"
    />
  </main>
</template>
