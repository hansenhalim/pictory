<script setup lang="ts">
import { ref } from 'vue'
import { useObjectUrl } from '@vueuse/core'
import PrintCopiesStepper from '@/components/PrintCopiesStepper.vue'
import { usePrinter } from '@/composables/usePrinter'
import type { PrintRecord } from '@/types'

const { print } = defineProps<{
  print: PrintRecord
}>()

const emit = defineEmits<{
  close: []
}>()

const { isPrinting, failure: printFailure, print: sendToPrinter } = usePrinter()
const imageUrl = useObjectUrl(() => print.printCopy)
const sheets = ref(1)
</script>

<template>
  <div
    class="fixed inset-0 z-10 flex items-center justify-center gap-[5vw] bg-neutral-950 px-8 py-12"
    @click.self="emit('close')"
  >
    <img
      :src="imageUrl"
      alt=""
      class="max-h-[84vh] max-w-[55vw] rounded bg-white shadow-lg"
      draggable="false"
    />

    <section class="flex w-80 flex-col items-center gap-8 text-center">
      <h2 class="text-xl font-semibold">Reprint</h2>

      <PrintCopiesStepper v-model="sheets" label="Prints" :cut="print.cut" />

      <div class="w-full space-y-2">
        <button
          type="button"
          class="w-full rounded-lg bg-white py-4 text-2xl font-semibold text-neutral-950 disabled:opacity-40"
          :disabled="isPrinting"
          @click="sendToPrinter(print.printCopy, sheets, print.cut)"
        >
          {{ isPrinting ? 'Printing…' : 'Print' }}
        </button>
        <p v-if="printFailure" class="text-sm text-red-400">Print failed. {{ printFailure }}</p>
      </div>

      <button type="button" class="text-xl text-neutral-400" @click="emit('close')">Close</button>
    </section>
  </div>
</template>
