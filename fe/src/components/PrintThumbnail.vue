<script setup lang="ts">
import { computed } from 'vue'
import { useObjectUrl } from '@vueuse/core'
import { useAssignmentsStore } from '@/stores/assignments'
import type { PrintRecord } from '@/types'

const { print } = defineProps<{
  print: PrintRecord
}>()

const assignmentsStore = useAssignmentsStore()
const imageUrl = useObjectUrl(() => print.printCopy)

const timeFormat = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' })
const dateFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' })

/** "Today 10:42", or "8 Oct 19:05" for older prints. */
const label = computed(() => {
  const printedAt = new Date(print.createdAt)
  const isToday = printedAt.toDateString() === new Date().toDateString()

  return `${isToday ? 'Today' : dateFormat.format(printedAt)} ${timeFormat.format(printedAt)}`
})
</script>

<template>
  <button type="button" class="space-y-1 text-left">
    <span class="relative block">
      <img
        :src="imageUrl"
        alt=""
        loading="lazy"
        decoding="async"
        class="aspect-[2/3] w-full rounded bg-white object-contain"
        draggable="false"
      />
      <span
        v-if="print.card || print.cardError"
        class="absolute top-1 left-1 rounded px-1.5 text-xs font-semibold tabular-nums"
        :class="
          print.cardError
            ? 'bg-red-600 text-white'
            : assignmentsStore.pendingRefs.has(print.ref)
              ? 'bg-neutral-950/80 text-neutral-300'
              : 'bg-neutral-950 text-white'
        "
        >{{ print.card ?? 'Card' }}</span
      >
    </span>
    <span class="block text-xs text-neutral-400">{{ label }}</span>
  </button>
</template>
