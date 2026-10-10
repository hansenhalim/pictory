<script setup lang="ts">
import { computed } from 'vue'

const MAX_SHEETS = 20

const { cut } = defineProps<{
  label: string
  /** A cut sheet makes two strips. */
  cut: boolean
}>()

/** Sheets sent to the printer. */
const sheets = defineModel<number>({ required: true })

/** People count prints, not sheets. */
const prints = computed(() => sheets.value * (cut ? 2 : 1))
</script>

<template>
  <div class="space-y-3 text-center">
    <p class="tracking-wide text-neutral-400 uppercase">{{ label }}</p>
    <div class="flex items-center justify-center gap-6">
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
</template>
