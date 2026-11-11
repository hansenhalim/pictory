@props([
    /** The paper's file name when downloaded, without extension; photos get a counter appended. */
    'downloadName',
    'paperUrl',
    /** The clip paper, once the queue has rendered it. */
    'clipUrl' => null,
    'photoUrls' => [],
    /** Where a guest's rating of the photobooth is posted, asked once they download any of it. */
    'feedbackUrl',
    /** Whether a guest has rated this paper already, so nobody else is asked. */
    'isRated' => false,
])

<div class="space-y-8" data-feedback-url="{{ $feedbackUrl }}" @if ($isRated) data-feedback-rated @endif>
    <section class="space-y-3">
        <img src="{{ $paperUrl }}" alt="Hasil cetak" class="w-full rounded">
        <a
            href="{{ $paperUrl }}"
            download="{{ $downloadName }}.png"
            class="block rounded bg-white py-3 text-center font-semibold text-neutral-950"
        >
            Unduh hasil cetak
        </a>
    </section>

    @if ($clipUrl)
        <section class="space-y-3">
            <h2 class="font-semibold">Video</h2>
            <video src="{{ $clipUrl }}" autoplay muted loop playsinline class="w-full rounded"></video>
            <a
                href="{{ $clipUrl }}"
                download="{{ $downloadName }}.mp4"
                class="block rounded bg-white py-3 text-center font-semibold text-neutral-950"
            >
                Unduh video
            </a>
        </section>
    @endif

    @if ($photoUrls)
        <section class="space-y-3">
            <h2 class="font-semibold">Foto</h2>

            <div class="grid grid-cols-2 gap-3">
                @foreach ($photoUrls as $photoUrl)
                    <a href="{{ $photoUrl }}" download="{{ $downloadName }}-{{ $loop->iteration }}.jpg" class="space-y-1">
                        <img src="{{ $photoUrl }}" alt="Foto {{ $loop->iteration }}" class="aspect-square w-full rounded object-cover">
                        <span class="block text-center text-sm text-neutral-400">Unduh</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
