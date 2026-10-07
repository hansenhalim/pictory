/**
 * Client for the print agent running on the kiosk machine (see agent/ in this repo).
 */

export interface AgentHealth {
  printer: string
  ok: boolean
  status: string
}

function endpoint(agentUrl: string, path: string): string {
  return agentUrl.replace(/\/+$/, '') + path
}

async function failure(response: Response): Promise<Error> {
  const body = (await response.json().catch(() => null)) as { message?: string } | null

  return new Error(body?.message ?? `The print agent responded with ${response.status}.`)
}

export async function checkAgent(agentUrl: string): Promise<AgentHealth> {
  const response = await fetch(endpoint(agentUrl, '/health'))

  if (!response.ok) {
    throw await failure(response)
  }

  return response.json() as Promise<AgentHealth>
}

/** Print `sheets` copies of the paper. Cut frames come out as two strips per sheet. */
export async function printPaper(agentUrl: string, paper: Blob, sheets: number, cut: boolean) {
  const form = new FormData()
  form.append('paper', paper, 'paper.png')
  form.append('copies', String(sheets))
  form.append('cut', String(cut))

  const response = await fetch(endpoint(agentUrl, '/print'), { method: 'POST', body: form })

  if (!response.ok) {
    throw await failure(response)
  }
}
