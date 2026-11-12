@php
    /**
     * A moment's album: every paper in it, oldest first, four to a page.
     *
     * @var \App\Models\Moment $moment
     * @var int $paperCount
     * @var string $description
     * @var string $coverUrl
     * @var list<list<array{number: int, url: string, paperUrl: string, hasNote: bool}>> $pages
     */
    $photos = $paperCount.' '.Str::plural('photo', $paperCount);
@endphp

<x-moment.layout
    :moment="$moment"
    :url="route('moments.shared', $moment->slug)"
    :description="$description"
    :image-url="$coverUrl"
>
    <x-slot:nav-end>{{ $photos }}</x-slot>

    <section class="moment-hero">
        <p class="moment-eyebrow">Pictory Moment</p>
        <h1>{{ $moment->name }}</h1>
        <p>The people. The room. <em>The little moments.</em></p>
        <div class="moment-meta">
            <span>{{ $moment->event_date?->format('j F Y') ?? 'Pictory Photobooth' }}</span>
            <span>{{ $photos }}</span>
        </div>
    </section>

    <section class="moment-album">
        <div class="moment-album-head">
            <span>THE ALBUM</span>
            <span>{{ count($pages) }} {{ Str::upper(Str::plural('page', count($pages))) }}</span>
        </div>

        @forelse ($pages as $page)
            <article class="moment-page">
                <div class="moment-page-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                <div class="moment-grid">
                    @foreach ($page as $entry)
                        <a class="moment-entry" href="{{ $entry['url'] }}">
                            <img src="{{ $entry['paperUrl'] }}" alt="Photo {{ $entry['number'] }}" loading="lazy">
                            @if ($entry['hasNote'])
                                <span class="moment-entry-note" role="img" aria-label="Has a message">💌</span>
                            @endif
                            <span>{{ str_pad($entry['number'], 2, '0', STR_PAD_LEFT) }}</span>
                        </a>
                    @endforeach
                </div>
                <div class="moment-page-caption">PICTORY · THE PEOPLE · THE ROOM · THE LITTLE MOMENTS</div>
            </article>
        @empty
            <p class="moment-empty">Your photos are on their way.</p>
        @endforelse

        @if ($pages)
            <div class="moment-more">
                <span>THE NIGHT ENDS.</span>
                <strong>The pictures <em>don't.</em></strong>
            </div>
        @endif
    </section>
</x-moment.layout>
