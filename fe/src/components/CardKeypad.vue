<script setup lang="ts">
import { computed, ref } from 'vue'
import { useEventListener } from '@vueuse/core'
import { parseCardLabel } from '@/lib/cards'

const { current = null, suggestions = [] } = defineProps<{
  /** The card the session is on now, if any. */
  current?: string | null
  /** Cards to fill in with one tap. Tapping one does not submit, so a stray tap assigns nothing. */
  suggestions?: { card: string; hint: string }[]
}>()

const emit = defineEmits<{
  submit: [card: string | null]
  close: []
}>()

const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0']

const input = ref('')
const card = computed(() => parseCardLabel(input.value))

function press(key: string) {
  if (input.value.length < 11) {
    input.value += key
  }
}

function backspace() {
  input.value = input.value.slice(0, -1)
}

function submit() {
  if (card.value) {
    emit('submit', card.value)
  }
}

/** A keyboard works too, for kiosks that have one. */
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
  if (/^\d$/.test(event.key)) {
    press(event.key)
  } else if (event.key === 'Backspace') {
    backspace()
  } else if (event.key === 'Enter') {
    submit()
  } else if (event.key === 'Escape') {
    emit('close')
  } else {
    return
  }

  event.preventDefault()
})
</script>

<template>
  <div
    class="fixed inset-0 z-20 flex items-center justify-center bg-neutral-950/95"
    @click.self="emit('close')"
  >
    <div class="w-96 space-y-4">
      <p class="text-center text-neutral-400">
        Card<template v-if="current"> · now {{ current }}</template>
      </p>

      <p
        class="rounded-lg bg-neutral-900 py-3 text-center text-6xl font-semibold tabular-nums"
        :class="{ 'text-neutral-600': !input }"
      >
        {{ input || 'card no.' }}
      </p>

      <div v-if="suggestions.length" class="flex gap-3">
        <button
          v-for="suggestion in suggestions"
          :key="suggestion.card"
          type="button"
          class="flex-1 rounded-lg bg-neutral-800 py-2 active:bg-neutral-700"
          @click="input = suggestion.card"
        >
          <span class="block text-2xl font-semibold tabular-nums">{{ suggestion.card }}</span>
          <span class="block text-sm text-neutral-400">{{ suggestion.hint }}</span>
        </button>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <button
          v-for="key in KEYS"
          :key="key"
          type="button"
          class="rounded-lg bg-neutral-800 py-4 text-3xl font-semibold active:bg-neutral-700"
          :class="{ 'col-start-2': key === '0' }"
          @click="press(key)"
        >
          {{ key }}
        </button>
        <button
          type="button"
          class="rounded-lg bg-neutral-800 py-4 text-3xl active:bg-neutral-700"
          aria-label="Delete"
          @click="backspace"
        >
          ⌫
        </button>
      </div>

      <div class="flex gap-3">
        <button
          type="button"
          class="flex-1 rounded-lg bg-neutral-800 py-4 text-xl"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="button"
          class="flex-1 rounded-lg bg-neutral-50 py-4 text-xl font-semibold text-neutral-950 disabled:opacity-40"
          :disabled="!card"
          @click="submit"
        >
          OK
        </button>
      </div>

      <button
        v-if="current"
        type="button"
        class="w-full py-2 text-neutral-400"
        @click="emit('submit', null)"
      >
        Remove card
      </button>
    </div>
  </div>
</template>
