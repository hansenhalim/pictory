@php
    /**
     * The behind-the-scenes clips of a session, one muted, looping player per clip.
     *
     * @var list<string> $clipUrls
     */

    $clipUrls = $getState() ?? [];
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if (filled($clipUrls))
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: 0.75rem;">
            @foreach ($clipUrls as $clipUrl)
                <video
                    src="{{ $clipUrl }}"
                    controls
                    muted
                    loop
                    playsinline
                    preload="metadata"
                    style="width: 100%; border-radius: 0.5rem; background: black;"
                ></video>
            @endforeach
        </div>
    @else
        <p class="fi-in-placeholder">{{ $getPlaceholder() }}</p>
    @endif
</x-dynamic-component>
