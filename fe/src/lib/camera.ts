import { canvasToBlob } from '@/lib/render'

/**
 * Where photos come from. The webcam is the only source for now; a DSLR driven over
 * WebUSB can implement the same interface later.
 */
export interface Camera {
  /** Start the camera and return the live stream to preview. */
  start(): Promise<MediaStream>
  /** Take a full-resolution photo, mirrored when the preview is. */
  capture(): Promise<Blob>
  stop(): void
}

export interface WebcamOptions {
  /** An id from `listCameras()`, or empty for the default camera. */
  deviceId: string
  mirror: boolean
}

export class WebcamCamera implements Camera {
  private stream: MediaStream | null = null
  private readonly video = Object.assign(document.createElement('video'), {
    muted: true,
    playsInline: true,
  })

  constructor(private readonly options: WebcamOptions) {}

  async start(): Promise<MediaStream> {
    this.stream = await this.open(this.options.deviceId).catch((error: unknown) => {
      if (this.options.deviceId && error instanceof DOMException) {
        return this.open('')
      }

      throw error
    })

    this.video.srcObject = this.stream
    await this.video.play()

    return this.stream
  }

  async capture(): Promise<Blob> {
    const { videoWidth: width, videoHeight: height } = this.video
    const canvas = Object.assign(document.createElement('canvas'), { width, height })
    const context = canvas.getContext('2d')!

    if (this.options.mirror) {
      context.translate(width, 0)
      context.scale(-1, 1)
    }

    context.drawImage(this.video, 0, 0, width, height)

    return canvasToBlob(canvas, 'image/jpeg', 0.92)
  }

  stop() {
    this.stream?.getTracks().forEach((track) => track.stop())
    this.stream = null
    this.video.srcObject = null
  }

  /** Ask for the camera's highest resolution; the browser settles on the closest it supports. */
  private open(deviceId: string): Promise<MediaStream> {
    return navigator.mediaDevices.getUserMedia({
      audio: false,
      video: {
        ...(deviceId ? { deviceId: { exact: deviceId } } : {}),
        width: { ideal: 4096 },
        height: { ideal: 2160 },
      },
    })
  }
}

/** List the connected cameras. Asks for camera permission first, since labels are hidden until then. */
export async function listCameras(): Promise<MediaDeviceInfo[]> {
  const probe = await navigator.mediaDevices.getUserMedia({ video: true })
  probe.getTracks().forEach((track) => track.stop())

  const devices = await navigator.mediaDevices.enumerateDevices()

  return devices.filter((device) => device.kind === 'videoinput')
}
