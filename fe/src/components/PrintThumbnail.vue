<script setup lang="ts">
import { computed } from 'vue'
import { useObjectUrl } from '@vueuse/core'
import type { PrintRecord } from '@/types'

const { print } = defineProps<{
  print: PrintRecord
}>()

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
    <img
      :src="imageUrl"
      alt=""
      loading="lazy"
      decoding="async"
      class="aspect-[2/3] w-full rounded bg-white object-contain"
      draggable="false"
    />
    <span class="block text-xs text-neutral-400">{{ label }}</span>
  </button>
</template>
