import type { Context, Module } from 'web-gphoto2/libapi'
import { canvasToBlob } from '@/lib/render'

/** Where photos come from: a webcam, or a DSLR driven over WebUSB. */
export interface Camera {
  /** Start the camera and return the live stream to preview. */
  start(): Promise<MediaStream>
  /** Take a photo as a JPEG, mirrored when the result is set to be. */
  capture(): Promise<Blob>
  stop(): void
}

/**
 * DSLR photos are scaled down to this on their longer side, the width the webcam is asked
 * for, which keeps them well under the backend's 10 MB limit per photo.
 */
const MAX_DSLR_PHOTO_SIZE = 4096

/** Encode a photo as JPEG, flipped horizontally when `mirror` is set. */
function encodePhoto(
  image: CanvasImageSource,
  width: number,
  height: number,
  mirror: boolean,
): Promise<Blob> {
  const canvas = Object.assign(document.createElement('canvas'), { width, height })
  const context = canvas.getContext('2d')!

  if (mirror) {
    context.translate(width, 0)
    context.scale(-1, 1)
  }

  context.imageSmoothingQuality = 'high'
  context.drawImage(image, 0, 0, width, height)

  return canvasToBlob(canvas, 'image/jpeg', 0.92)
}

function wait(milliseconds: number) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds))
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

    return encodePhoto(this.video, width, height, this.options.mirror)
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

export interface DslrOptions {
  mirror: boolean
}

/** The USB interface class and subclass of cameras speaking PTP, as web-gphoto2 asks for. */
const PTP_CAMERA_FILTER = { classCode: 6, subclassCode: 1 }

/**
 * A connection to the DSLR through its own instance of web-gphoto2's WebAssembly module,
 * used directly instead of through web-gphoto2's wrapper, which shares one instance. gPhoto2
 * cannot connect twice in one instance, and an instance that crashed (now and then it fails
 * with "memory access out of bounds") stays broken, so every connection gets a fresh one.
 */
class DslrConnection {
  isClosed = false
  private queue: Promise<unknown> = Promise.resolve()
  private readonly listeners = new AbortController()

  private constructor(
    private readonly module: Module,
    private readonly context: Context,
  ) {
    navigator.usb.addEventListener('disconnect', () => this.close(), {
      signal: this.listeners.signal,
    })
  }

  static async open(): Promise<DslrConnection> {
    const { default: initModule } = await import('web-gphoto2/libapi')
    const module = await initModule()

    try {
      return new DslrConnection(module, await new module.Context())
    } catch (error) {
      terminateThreads(module)
      throw error
    }
  }

  /** gPhoto2 handles one call at a time, so calls wait for the ones before them. */
  run<T>(operation: (context: Context) => Promise<T>): Promise<T> {
    if (this.isClosed) {
      return Promise.reject(new Error('The camera was disconnected.'))
    }

    const result = this.queue.then(() => operation(this.context))

    // gPhoto2 reports its own failures as plain errors. Anything else, like a WebAssembly
    // RuntimeError, means the instance crashed.
    this.queue = result.catch((error: unknown) => {
      if (error?.constructor !== Error) {
        this.close()
      }
    })

    return result
  }

  /** Throw the instance away. Calling into it could crash again, so it is not shut down cleanly. */
  close() {
    if (!this.isClosed) {
      this.isClosed = true
      this.listeners.abort()
      terminateThreads(this.module)
    }
  }
}

function terminateThreads(module: Module) {
  const { PThread } = module as unknown as {
    PThread: { runningWorkers: Worker[]; unusedWorkers: Worker[] }
  }
  const workers = [...PThread.runningWorkers, ...PThread.unusedWorkers]

  workers.forEach((worker) => worker.terminate())
}

let dslrConnection: Promise<DslrConnection | null> = Promise.resolve(null)

/** The open connection to the DSLR, or a new one when there is none or it was closed. */
function connectDslr(): Promise<DslrConnection> {
  const connection = dslrConnection.then(
    (current) => (current && !current.isClosed ? current : openDslr()),
    () => openDslr(),
  )

  dslrConnection = connection

  return connection
}

async function openDslr(): Promise<DslrConnection> {
  if (!('usb' in navigator)) {
    throw new Error('This browser cannot use USB devices.')
  }

  // web-gphoto2 shares memory with its worker threads, which needs cross-origin isolation.
  if (!crossOriginIsolated) {
    throw new Error(
      'The page is not cross-origin isolated. Serve /kiosk/ with the Cross-Origin-Opener-Policy and Cross-Origin-Embedder-Policy headers.',
    )
  }

  const devices = await navigator.usb.getDevices()

  if (devices.length === 0) {
    throw new Error('No allowed camera is connected. Turn it on, or choose it in the settings.')
  }

  // Let go of the camera the way a reload would, in case a crashed instance still holds it.
  await Promise.all(devices.filter((device) => device.opened).map((device) => device.close()))

  return DslrConnection.open()
}

/**
 * A DSLR or mirrorless camera on USB, driven by gPhoto2 compiled to WebAssembly. Its live
 * view comes as separate JPEG frames, painted onto a canvas so it streams like a webcam.
 */
export class DslrCamera implements Camera {
  private readonly canvas = document.createElement('canvas')
  private readonly context = this.canvas.getContext('2d')!
  private stream: MediaStream | null = null
  private isStarted = false
  private isStopped = false

  constructor(private readonly options: DslrOptions) {}

  async start(): Promise<MediaStream> {
    this.isStarted = true

    // The first frame sizes the canvas, and fails early on cameras without live view.
    await this.showPreviewFrame()
    void this.preview()

    this.stream = this.canvas.captureStream()

    return this.stream
  }

  async capture(): Promise<Blob> {
    const file = await this.call((camera) => camera.captureImageAsFile())
    const image = await createImageBitmap(file).catch(() => {
      throw new Error(
        `The camera sent ${file.name}, which is not a JPEG. Set it to shoot JPEG only.`,
      )
    })
    const scale = Math.min(1, MAX_DSLR_PHOTO_SIZE / Math.max(image.width, image.height))

    try {
      return await encodePhoto(
        image,
        Math.round(image.width * scale),
        Math.round(image.height * scale),
        this.options.mirror,
      )
    } finally {
      image.close()
    }
  }

  /** Stops the live view, leaving the camera connected for the next shoot. */
  stop() {
    this.isStopped = true
    this.stream?.getTracks().forEach((track) => track.stop())
    this.stream = null
  }

  /** Each call reconnects if the connection was closed, so a crash costs one frame or photo. */
  private async call<T>(operation: (context: Context) => Promise<T>): Promise<T> {
    if (!this.isStarted || this.isStopped) {
      throw new Error('The camera is not started.')
    }

    return (await connectDslr()).run(operation)
  }

  /** Paint live view frames until stopped. A frame fails now and then, e.g. while the camera is busy. */
  private async preview() {
    while (!this.isStopped) {
      try {
        await this.showPreviewFrame()
      } catch (error) {
        if (!this.isStopped) {
          console.error(error)
          await wait(1000)
        }
      }
    }
  }

  private async showPreviewFrame() {
    const frame = await createImageBitmap(
      await this.call((camera) => camera.capturePreviewAsBlob()),
    )

    if (this.canvas.width !== frame.width || this.canvas.height !== frame.height) {
      Object.assign(this.canvas, { width: frame.width, height: frame.height })
    }

    this.context.drawImage(frame, 0, 0)
    frame.close()
  }
}

/** Show Chrome's list of USB cameras, so the operator can allow the kiosk to use one. Needs a tap. */
export async function chooseDslr(): Promise<void> {
  await navigator.usb.requestDevice({ filters: [PTP_CAMERA_FILTER] })
}

/** The names of the USB cameras the operator has allowed the kiosk to use. */
export async function listDslrs(): Promise<string[]> {
  if (!('usb' in navigator)) {
    return []
  }

  const devices = await navigator.usb.getDevices()

  return devices.map((device) => device.productName || 'Unnamed camera')
}
