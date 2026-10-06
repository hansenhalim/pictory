@php
    /**
     * Live preview of a frame: every slot placement and QR code drawn over the frame image.
     * In print preview, a sample photo fills each placement behind the frame and a sample
     * QR code sits on top of it at each QR placement, as printed. The print preview can be
     * downloaded as a JPG at the frame image's full resolution.
     *
     * Boxes are positioned in percentages of the image's natural size, so the stored
     * pixel coordinates map onto the image at any rendered width.
     *
     * @var string|null $imageUrl
     * @var string $statePath
     */

    $fileName = 'frame-' . ($record?->getKey() ?? 'new') . '-preview.jpg';
@endphp

<div
    x-data="{
        isPrintPreview: false,
        fileName: @js($fileName),
        naturalWidth: 0,
        naturalHeight: 0,
        measure(image) {
            this.naturalWidth = image.naturalWidth
            this.naturalHeight = image.naturalHeight
        },
        toNumber(value) {
            return value === null || value === undefined || value === '' ? NaN : Number(value)
        },
        slots() {
            if (! this.$wire.$get(@js($statePath . '.slots_as_json'))) {
                return Object.values(this.$wire.$get(@js($statePath . '.slots')) ?? {})
                    .map((slot) => Object.values(slot?.placements ?? {}))
            }

            try {
                const slots = JSON.parse(this.$wire.$get(@js($statePath . '.slots_json')) ?? '')

                return Array.isArray(slots) ? slots.map((placements) => Array.isArray(placements) ? placements : []) : []
            } catch {
                return []
            }
        },
        boxes() {
            const boxes = []

            this.slots().forEach((placements, index) => {
                placements.forEach((placement) => boxes.push({
                    kind: 'slot',
                    label: String(index + 1),
                    x: this.toNumber(placement?.x),
                    y: this.toNumber(placement?.y),
                    width: this.toNumber(placement?.width),
                    height: this.toNumber(placement?.height),
                }))
            })

            Object.values(this.$wire.$get(@js($statePath . '.qr_codes')) ?? {}).forEach((qrCode) => boxes.push({
                kind: 'qr',
                label: 'QR',
                x: this.toNumber(qrCode?.x),
                y: this.toNumber(qrCode?.y),
                width: this.toNumber(qrCode?.size),
                height: this.toNumber(qrCode?.size),
            }))

            return boxes.filter((box) => [box.x, box.y, box.width, box.height].every(Number.isFinite) && box.width > 0 && box.height > 0)
        },
        loadImage(src) {
            return new Promise((resolve, reject) => {
                const image = new Image()

                image.onload = () => resolve(image)
                image.onerror = reject
                image.src = src
            })
        },
        drawCovering(context, image, box) {
            const scale = Math.max(box.width / image.naturalWidth, box.height / image.naturalHeight)
            const sourceWidth = box.width / scale
            const sourceHeight = box.height / scale

            context.drawImage(
                image,
                (image.naturalWidth - sourceWidth) / 2,
                (image.naturalHeight - sourceHeight) / 2,
                sourceWidth,
                sourceHeight,
                box.x,
                box.y,
                box.width,
                box.height,
            )
        },
        async download() {
            try {
                const [photo, qrCode] = await Promise.all([
                    this.loadImage(@js(asset('images/photo_placeholder.jpg'))),
                    this.loadImage(@js(asset('images/qr_placeholder.svg'))),
                ])

                const canvas = document.createElement('canvas')
                canvas.width = this.naturalWidth
                canvas.height = this.naturalHeight

                const context = canvas.getContext('2d')
                context.fillStyle = 'white'
                context.fillRect(0, 0, canvas.width, canvas.height)

                this.boxesOfKind('slot').forEach((box) => this.drawCovering(context, photo, box))
                context.drawImage(this.$refs.frame, 0, 0, canvas.width, canvas.height)
                this.boxesOfKind('qr').forEach((box) => context.drawImage(qrCode, box.x, box.y, box.width, box.height))

                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92))
                const link = document.createElement('a')
                link.href = URL.createObjectURL(blob)
                link.download = this.fileName
                link.click()

                setTimeout(() => URL.revokeObjectURL(link.href), 1000)
            } catch (error) {
                new FilamentNotification()
                    .title('Could not create the preview image')
                    .danger()
                    .send()
            }
        },
        boxesOfKind(kind) {
            return this.boxes().filter((box) => box.kind === kind)
        },
        imageStyle(box) {
            return {
                position: 'absolute',
                left: `${box.x / this.naturalWidth * 100}%`,
                top: `${box.y / this.naturalHeight * 100}%`,
                width: `${box.width / this.naturalWidth * 100}%`,
                height: `${box.height / this.naturalHeight * 100}%`,
                maxWidth: 'none',
                objectFit: 'cover',
            }
        },
        boxStyle(box) {
            const isQrCode = box.kind === 'qr'

            return {
                position: 'absolute',
                left: `${box.x / this.naturalWidth * 100}%`,
                top: `${box.y / this.naturalHeight * 100}%`,
                width: `${box.width / this.naturalWidth * 100}%`,
                height: `${box.height / this.naturalHeight * 100}%`,
                boxSizing: 'border-box',
                border: `2px solid ${isQrCode ? 'rgb(192 38 211)' : 'rgb(2 132 199)'}`,
                background: isQrCode ? 'rgb(217 70 239 / 0.25)' : 'rgb(14 165 233 / 0.25)',
            }
        },
        labelStyle(box) {
            return {
                position: 'absolute',
                top: '2px',
                left: '2px',
                padding: '0 6px',
                borderRadius: '4px',
                fontSize: '12px',
                fontWeight: '600',
                lineHeight: '18px',
                color: 'white',
                background: box.kind === 'qr' ? 'rgb(192 38 211)' : 'rgb(2 132 199)',
            }
        },
    }"
    style="position: sticky; top: 5rem;"
>
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.5rem;">
        <p style="font-size: 0.875rem; font-weight: 500;">Preview</p>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                <x-filament::input.checkbox x-model="isPrintPreview" />
                Print preview
            </label>

            @if ($imageUrl)
                <x-filament::button
                    color="gray"
                    size="sm"
                    icon="heroicon-m-arrow-down-tray"
                    x-bind:disabled="naturalWidth === 0"
                    x-on:click="download()"
                >
                    Download JPG
                </x-filament::button>
            @endif
        </div>
    </div>

    @if ($imageUrl)
        <div
            style="position: relative; overflow: hidden; line-height: 0;"
            x-bind:style="{ background: isPrintPreview ? 'white' : '' }"
        >
            <template x-if="isPrintPreview && naturalWidth > 0 && naturalHeight > 0">
                <div>
                    <template x-for="(box, index) in boxesOfKind('slot')" :key="index">
                        <img src="{{ asset('images/photo_placeholder.jpg') }}" alt="" :style="imageStyle(box)" />
                    </template>
                </div>
            </template>

            <img
                src="{{ $imageUrl }}"
                alt="Frame preview"
                x-ref="frame"
                style="position: relative; display: block; width: 100%; height: auto;"
                x-init="$el.complete && measure($el)"
                x-on:load="measure($el)"
            />

            <template x-if="isPrintPreview && naturalWidth > 0 && naturalHeight > 0">
                <div>
                    <template x-for="(box, index) in boxesOfKind('qr')" :key="index">
                        <img src="{{ asset('images/qr_placeholder.svg') }}" alt="" :style="imageStyle(box)" />
                    </template>
                </div>
            </template>

            <template x-if="! isPrintPreview && naturalWidth > 0 && naturalHeight > 0">
                <div>
                    <template x-for="(box, index) in boxes()" :key="index">
                        <div :style="boxStyle(box)">
                            <span :style="labelStyle(box)" x-text="box.label"></span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    @else
        <p style="font-size: 0.875rem; color: var(--gray-500);">Upload a frame image to see the preview.</p>
    @endif
</div>
