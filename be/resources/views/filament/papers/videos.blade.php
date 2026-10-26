@php
    /**
     * One muted, looping player per video, like a session's behind-the-scenes clips.
     *
     * @var list<string> $videoUrls
     */

    $videoUrls = $getState() ?? [];
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if (filled($videoUrls))
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: 0.75rem;">
            @foreach ($videoUrls as $videoUrl)
                <video
                    src="{{ $videoUrl }}"
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
