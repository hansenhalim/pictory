/** Clips are AV1 for the best compression. Kiosks whose browser cannot record it make none. */
const CLIP_TYPE = 'video/mp4;codecs=av01'

/** Clips are scaled down to fit this box, keeping the camera's aspect ratio. */
const MAX_CLIP_WIDTH = 1920
const MAX_CLIP_HEIGHT = 1280

const CLIP_BITS_PER_SECOND = 3_000_000

/** Whether this browser can record behind-the-scenes clips. */
export function canRecordClips(): boolean {
  return 'MediaRecorder' in window && MediaRecorder.isTypeSupported(CLIP_TYPE)
}

export interface ClipOptions {
  /** Flip the clip horizontally, to match photos taken with the result mirrored. */
  mirror: boolean
}

/** Video to record, and how to let go of it once recorded. */
interface ClipSource {
  stream: MediaStream
  close(): void
}

/**
 * A behind-the-scenes clip being recorded from a copy of the camera's stream, so it can be
 * scaled down without touching the preview or the photo. Clips are a bonus: when one fails,
 * it is logged and comes out as null instead of getting in the way of the photo.
 */
export class ClipRecorder {
  /** Settles once recording ends, with the clip, or null when it failed. */
  readonly clip: Promise<Blob | null>

  private constructor(
    private readonly recorder: MediaRecorder,
    source: ClipSource,
  ) {
    const chunks: Blob[] = []
    let hasFailed = false

    recorder.addEventListener('dataavailable', (event) => chunks.push(event.data))
    recorder.addEventListener('error', (event) => {
      console.error(event)
      hasFailed = true
    })

    this.clip = new Promise((resolve) => {
      recorder.addEventListener('stop', () => {
        source.close()

        resolve(hasFailed || chunks.length === 0 ? null : new Blob(chunks, { type: 'video/mp4' }))
      })
    })
  }

  /** Start recording the stream's video, or return null when that is not possible. */
  static async start(stream: MediaStream, options: ClipOptions): Promise<ClipRecorder | null> {
    const track = stream.getVideoTracks()[0]

    if (!track || !canRecordClips()) {
      return null
    }

    let source: ClipSource | null = null

    try {
      source = await openClipSource(track, options)

      const recorder = new MediaRecorder(source.stream, {
        mimeType: CLIP_TYPE,
        videoBitsPerSecond: CLIP_BITS_PER_SECOND,
      })
      const clipRecorder = new ClipRecorder(recorder, source)
      recorder.start()

      return clipRecorder
    } catch (error) {
      console.error(error)
      source?.close()

      return null
    }
  }

  /** Stop recording and return the clip. */
  stop(): Promise<Blob | null> {
    if (this.recorder.state !== 'inactive') {
      this.recorder.stop()
    }

    return this.clip
  }
}

/**
 * The track's video, fitted to the clip box and mirrored when asked. MediaRecorder encodes a
 * camera's frames at their full size even on a track scaled down with `applyConstraints`, so
 * video that is larger, or has to be flipped, is drawn onto a canvas of the clip's size instead.
 * A DSLR's live view is already smaller than the box.
 */
async function openClipSource(
  track: MediaStreamTrack,
  { mirror }: ClipOptions,
): Promise<ClipSource> {
  if (!mirror && fitScale(track.getSettings()) === 1) {
    const copy = track.clone()

    return { stream: new MediaStream([copy]), close: () => copy.stop() }
  }

  const video = Object.assign(document.createElement('video'), { muted: true, playsInline: true })
  video.srcObject = new MediaStream([track])
  await video.play()

  const { videoWidth: width, videoHeight: height } = video
  const scale = fitScale({ width, height })
  const canvas = Object.assign(document.createElement('canvas'), {
    width: Math.round(width * scale),
    height: Math.round(height * scale),
  })
  const context = canvas.getContext('2d')!
  const stream = canvas.captureStream()

  if (mirror) {
    context.translate(canvas.width, 0)
    context.scale(-1, 1)
  }

  let isClosed = false

  const draw = () => {
    if (!isClosed) {
      context.drawImage(video, 0, 0, canvas.width, canvas.height)
      video.requestVideoFrameCallback(draw)
    }
  }

  draw()

  return {
    stream,
    close: () => {
      isClosed = true
      stream.getTracks().forEach((canvasTrack) => canvasTrack.stop())
      video.srcObject = null
    },
  }
}

/** How much video of this size shrinks to fit the clip box; 1 when it already fits or its size is unknown. */
function fitScale({ width = 0, height = 0 }: { width?: number; height?: number }): number {
  return width && height ? Math.min(1, MAX_CLIP_WIDTH / width, MAX_CLIP_HEIGHT / height) : 1
}
