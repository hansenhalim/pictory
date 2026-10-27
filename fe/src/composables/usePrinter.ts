import { ref } from 'vue'
import { checkAgent, printPaper } from '@/lib/agent'
import { useSettingsStore } from '@/stores/settings'

/**
 * Send prints to the kiosk's print agent, tracking the request for the button that sent it,
 * and the sheets the printer has left.
 */
export function usePrinter() {
  const settingsStore = useSettingsStore()

  const isPrinting = ref(false)
  const failure = ref<string | null>(null)
  /** Null when unknown, e.g. the agent cannot be reached or the printer reports no count. */
  const sheetsLeft = ref<number | null>(null)

  async function refreshSheetsLeft() {
    try {
      sheetsLeft.value = (await checkAgent(settingsStore.settings.agentUrl)).sheets_left
    } catch (error) {
      console.error(error)
      sheetsLeft.value = null
    }
  }

  async function print(printCopy: Blob, sheets: number, cut: boolean) {
    isPrinting.value = true
    failure.value = null

    try {
      await printPaper(settingsStore.settings.agentUrl, printCopy, sheets, cut)

      // The printer reports its count only after the job, so it is counted down here.
      if (sheetsLeft.value !== null) {
        sheetsLeft.value = Math.max(0, sheetsLeft.value - sheets)
      }
    } catch (error) {
      console.error(error)
      failure.value = error instanceof Error ? error.message : String(error)
    } finally {
      isPrinting.value = false
    }
  }

  return { isPrinting, failure, sheetsLeft, refreshSheetsLeft, print }
}
