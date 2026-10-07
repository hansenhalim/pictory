import QRCode from 'qrcode'
import type { Frame, Placement, QrPlacement } from '@/types'

/**
 * Renders the printed paper the same way the admin panel's frame preview does: photos
 * cover-fitted into every placement on white, the frame image on top, then the QR codes.
 */

const QR_QUIET_ZONE_MODULES = 4

export function canvasToBlob(
  canvas: HTMLCanvasElement,
  type: string,
  quality?: number,
): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => (blob ? resolve(blob) : reject(new Error('Could not encode the canvas.'))),
      type,
      quality,
    )
  })
}

/** The centred part of a source image that fills a box without stretching, like CSS `object-fit: cover`. */
export function coverCrop(
  sourceWidth: number,
  sourceHeight: number,
  box: Pick<Placement, 'width' | 'height'>,
) {
  const scale = Math.max(box.width / sourceWidth, box.height / sourceHeight)
  const width = box.width / scale
  const height = box.height / scale

  return { x: (sourceWidth - width) / 2, y: (sourceHeight - height) / 2, width, height }
}

function drawCovering(context: CanvasRenderingContext2D, image: ImageBitmap, box: Placement) {
  const crop = coverCrop(image.width, image.height, box)

  context.drawImage(
    image,
    crop.x,
    crop.y,
    crop.width,
    crop.height,
    box.x,
    box.y,
    box.width,
    box.height,
  )
}

/**
 * Draw a QR code as a white square of `size` pixels with a 4-module quiet zone, at error
 * correction level Q. Module edges are snapped to whole pixels so no seams show between them.
 */
function drawQrCode(context: CanvasRenderingContext2D, text: string, { x, y, size }: QrPlacement) {
  const { modules } = QRCode.create(text, { errorCorrectionLevel: 'Q' })
  const moduleSize = size / (modules.size + QR_QUIET_ZONE_MODULES * 2)
  const edge = (offset: number, module: number) =>
    Math.round(offset + (module + QR_QUIET_ZONE_MODULES) * moduleSize)

  context.fillStyle = 'white'
  context.fillRect(x, y, size, size)
  context.fillStyle = 'black'

  for (let row = 0; row < modules.size; row++) {
    for (let column = 0; column < modules.size; column++) {
      if (modules.get(row, column)) {
        const left = edge(x, column)
        const top = edge(y, row)

        context.fillRect(left, top, edge(x, column + 1) - left, edge(y, row + 1) - top)
      }
    }
  }
}

export interface PaperInput {
  frame: Frame
  frameImage: Blob
  /** One photo per slot, in slot order. */
  photos: Blob[]
  /** The soft copy URL to encode, or null to leave the QR areas blank. */
  shareUrl: string | null
}

export async function renderPaper({
  frame,
  frameImage,
  photos,
  shareUrl,
}: PaperInput): Promise<Blob> {
  const [frameBitmap, ...photoBitmaps] = await Promise.all(
    [frameImage, ...photos].map((blob) => createImageBitmap(blob)),
  )

  const canvas = Object.assign(document.createElement('canvas'), {
    width: frameBitmap!.width,
    height: frameBitmap!.height,
  })
  const context = canvas.getContext('2d')!

  context.fillStyle = 'white'
  context.fillRect(0, 0, canvas.width, canvas.height)

  frame.slots.forEach((placements, index) => {
    const photo = photoBitmaps[index]

    if (photo) {
      placements.forEach((placement) => drawCovering(context, photo, placement))
    }
  })

  context.drawImage(frameBitmap!, 0, 0, canvas.width, canvas.height)

  frame.qr_codes.forEach((placement) => {
    if (shareUrl) {
      drawQrCode(context, shareUrl, placement)
    } else {
      context.fillStyle = 'white'
      context.fillRect(placement.x, placement.y, placement.size, placement.size)
    }
  })

  ;[frameBitmap, ...photoBitmaps].forEach((bitmap) => bitmap?.close())

  return canvasToBlob(canvas, 'image/png')
}
