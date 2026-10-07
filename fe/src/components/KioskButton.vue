<script setup lang="ts">
const { variant = 'primary' } = defineProps<{
  variant?: 'primary' | 'secondary'
}>()
</script>

<template>
  <button
    type="button"
    class="rounded-lg px-10 py-4 text-2xl font-semibold transition active:scale-95 disabled:opacity-40"
    :class="{
      'bg-white text-neutral-950': variant === 'primary',
      'bg-neutral-800 text-neutral-100': variant === 'secondary',
    }"
  >
    <slot />
  </button>
</template>
