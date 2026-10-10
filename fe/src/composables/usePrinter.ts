import { ref } from 'vue'
import { printPaper } from '@/lib/agent'
import { useSettingsStore } from '@/stores/settings'

/** Send prints to the kiosk's print agent, tracking the request for the button that sent it. */
export function usePrinter() {
  const settingsStore = useSettingsStore()

  const isPrinting = ref(false)
  const failure = ref<string | null>(null)

  async function print(printCopy: Blob, sheets: number, cut: boolean) {
    isPrinting.value = true
    failure.value = null

    try {
      await printPaper(settingsStore.settings.agentUrl, printCopy, sheets, cut)
    } catch (error) {
      console.error(error)
      failure.value = error instanceof Error ? error.message : String(error)
    } finally {
      isPrinting.value = false
    }
  }

  return { isPrinting, failure, print }
}
