@php
    /**
     * One paper of a moment, to download with its clip and photos.
     *
     * @var \App\Models\Moment $moment
     * @var int $number
     * @var int $paperCount
     * @var string $description
     * @var string $coverUrl
     * @var \Illuminate\Support\Carbon $takenAt
     * @var string|null $note A guest's message to the moment's hosts.
     * @var string $downloadName The paper's file name when downloaded, without extension; photos get a counter appended.
     * @var string $paperUrl
     * @var string|null $clipUrl
     * @var list<string> $photoUrls
     * @var string|null $previousUrl
     * @var string|null $nextUrl
     */
    $albumUrl = route('moments.shared', $moment->slug);
@endphp

<x-moment.layout
    :moment="$moment"
    :url="url()->current()"
    :description="$description"
    :image-url="$coverUrl"
>
    <x-slot:nav-end>
        <a href="{{ $albumUrl }}">← Album</a>
    </x-slot>

    <section class="moment-delivery">
        <div class="moment-delivery-copy">
            <p class="moment-eyebrow">Photo {{ str_pad($number, 2, '0', STR_PAD_LEFT) }} / {{ $paperCount }}</p>
            <h1>{{ $moment->name }}</h1>
            <p><time datetime="{{ $takenAt->toIso8601String() }}" data-local-time></time></p>

            @if ($note)
                <figure class="moment-note">
                    <figcaption class="moment-eyebrow">A message</figcaption>
                    <blockquote>{{ $note }}</blockquote>
                </figure>
            @endif

            <div class="moment-buttons">
                <a class="moment-button moment-button-light" href="{{ $paperUrl }}" download="{{ $downloadName }}.png">Download photo <span>↓</span></a>
                @if ($clipUrl)
                    <a class="moment-button" href="{{ $clipUrl }}" download="{{ $downloadName }}.mp4">Download video <span>↓</span></a>
                @endif
            </div>

            @if ($photoUrls)
                <div class="moment-section">
                    <p class="moment-eyebrow">Originals</p>
                    <div class="moment-originals">
                        @foreach ($photoUrls as $photoUrl)
                            <a href="{{ $photoUrl }}" download="{{ $downloadName }}-{{ $loop->iteration }}.jpg">
                                <img src="{{ $photoUrl }}" alt="Original {{ $loop->iteration }}" loading="lazy">
                                <span>DOWNLOAD ↓</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <nav class="moment-pager">
                @if ($previousUrl)
                    <a href="{{ $previousUrl }}" rel="prev">← Prev</a>
                @else
                    <span aria-disabled="true">← Prev</span>
                @endif
                <a href="{{ $albumUrl }}">Back to album</a>
                @if ($nextUrl)
                    <a href="{{ $nextUrl }}" rel="next">Next →</a>
                @else
                    <span aria-disabled="true">Next →</span>
                @endif
            </nav>
        </div>

        <div class="moment-focus">
            <img src="{{ $paperUrl }}" alt="Photo {{ $number }}">
            @if ($clipUrl)
                <video src="{{ $clipUrl }}" autoplay muted loop playsinline></video>
            @endif
        </div>
    </section>
</x-moment.layout>
