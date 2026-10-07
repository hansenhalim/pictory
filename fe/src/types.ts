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

/** A finished session waiting to be uploaded to the backend. */
export interface PendingUpload {
  ref: string
  code: string | null
  paper: Blob
  photos: Blob[]
  createdAt: number
}
