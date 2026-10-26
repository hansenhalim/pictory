/** Where one photo is printed on the paper, in the frame image's pixels. */
export interface Placement {
  x: number
  y: number
  width: number
  height: number
}

/** Where a QR code is printed: a white square of `size` pixels, quiet zone included. */
export interface QrPlacement {
  x: number
  y: number
  size: number
}

export interface Frame {
  id: number
  image_url: string
  /** One entry per photo, each listing every placement of that photo on the paper. */
  slots: Placement[][]
  qr_codes: QrPlacement[]
  /** Whether the 4R print is cut in half into two 2R strips. */
  cut: boolean
  updated_at: string
}

/** A finished session kept on the kiosk so the operator can print it again. */
export interface PrintRecord {
  ref: string
  createdAt: number
  /** Exactly what was sent to the printer, QR codes included. */
  printCopy: Blob
  cut: boolean
  /** The QR card the operator assigned the session to, e.g. "342". Missing on older prints. */
  card?: string | null
  /** Why the backend refused the card, e.g. because it does not exist. */
  cardError?: string | null
}

/** A finished session waiting to be uploaded to the backend. */
export interface PendingUpload {
  ref: string
  /** The frame the session was shot with. Missing on sessions queued before it was sent. */
  frameId?: number
  code: string | null
  /** The soft copy, without QR codes. */
  paper: Blob
  photos: Blob[]
  /** Each photo's behind-the-scenes clip, or null where none was made. Missing on sessions queued before clips. */
  clips?: (Blob | null)[]
  createdAt: number
}

/** A session's QR card, waiting to be sent to the backend once the session has been uploaded. */
export interface PendingAssignment {
  ref: string
  /** The card's label, or null to take the session off its card. */
  card: string | null
  createdAt: number
}
