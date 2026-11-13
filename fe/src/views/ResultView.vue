<script setup lang="ts">
import { useRouter } from 'vue-router'
import PaperScreen from '@/components/PaperScreen.vue'
import { copy } from '@/copy'
import { useSessionStore } from '@/stores/session'

const router = useRouter()
const sessionStore = useSessionStore()

async function assignCard(card: string | null) {
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
</script>

<template>
  <PaperScreen
    v-if="sessionStore.softCopy && sessionStore.printCopy"
    :soft-copy="sessionStore.softCopy"
    :print-copy="sessionStore.printCopy"
    :cut="sessionStore.frame?.cut ?? false"
    :code="sessionStore.code"
    :card="sessionStore.card"
    @assign-card="assignCard"
  >
    <button type="button" class="text-xl text-neutral-400" @click="restart">
      {{ copy.restart }}
    </button>
  </PaperScreen>
</template>
