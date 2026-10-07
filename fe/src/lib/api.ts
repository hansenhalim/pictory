import type { Frame } from '@/types'

/**
 * Client for the backend, which shares this page's origin and authenticates it with the
 * admin panel's session cookie. Laravel accepts same-origin requests without a CSRF token.
 */

export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly body: unknown,
  ) {
    super(`The backend responded with ${status}.`)
  }
}

let onUnauthorized = () => {}

/** Register what to do when the session is gone and the operator has to sign in again. */
export function whenUnauthorized(callback: () => void) {
  onUnauthorized = callback
}

async function send(path: string, init: RequestInit = {}): Promise<Response> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')

  const response = await fetch(path, { ...init, headers, credentials: 'same-origin' })

  if (response.status === 401) {
    onUnauthorized()
  }

  if (!response.ok) {
    throw new ApiError(response.status, await response.json().catch(() => null))
  }

  return response
}

async function sendJson<T>(path: string, init: RequestInit = {}): Promise<T> {
  return (await send(path, init)).json() as Promise<T>
}

export interface StoredPaper {
  ref: string
  replacement_code: string | null
}

export const api = {
  async frames(): Promise<Frame[]> {
    return (await sendJson<{ data: Frame[] }>('/api/frames')).data
  },

  async frameImage(frame: Frame): Promise<Blob> {
    return (await send(frame.image_url)).blob()
  },

  async reserveShortCodes(count: number): Promise<string[]> {
    const response = await sendJson<{ data: string[] }>('/api/short-codes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ count }),
    })

    return response.data
  },

  async storePaper(form: FormData): Promise<StoredPaper> {
    return (await sendJson<{ data: StoredPaper }>('/api/papers', { method: 'POST', body: form }))
      .data
  },
}

/** Whether a failed upload was rejected only because its short code is unusable. */
export function isShortCodeRejected(error: unknown): boolean {
  if (!(error instanceof ApiError) || error.status !== 422) {
    return false
  }

  const errors = (error.body as { errors?: Record<string, unknown> } | null)?.errors ?? {}

  return Object.keys(errors).length === 1 && 'code' in errors
}
