@props([
    'moment',
    /** The canonical URL of this page, which chat apps show and link to. */
    'url',
    'description',
    /** The link preview image, 1200 × 630. */
    'imageUrl',
    'navEnd' => null,
])

<!DOCTYPE html>
<html lang="en" data-theme="{{ $moment->theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $moment->name }} · Pictory</title>
        <meta name="description" content="{{ $description }}">
        <meta name="robots" content="noindex, nofollow">
        <link rel="canonical" href="{{ $url }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Pictory">
        <meta property="og:title" content="{{ $moment->name }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $url }}">
        <meta property="og:image" content="{{ $imageUrl }}">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="{{ \App\Jobs\RenderMomentCover::WIDTH }}">
        <meta property="og:image:height" content="{{ \App\Jobs\RenderMomentCover::HEIGHT }}">
        <meta property="og:image:alt" content="{{ $moment->name }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $moment->name }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $imageUrl }}">
        <meta name="theme-color" content="{{ $moment->theme === 'light' ? '#f4f1ec' : '#080808' }}">

        @fonts(['bodoni-moda', 'manrope'])
        @vite(['resources/css/moment.css'])
    </head>
    <body>
        <div class="moment-grain"></div>

        <header class="moment-nav">
            <a href="{{ route('moments.shared', $moment->slug) }}">
                <img src="{{ asset('images/pictory-logo.svg') }}" alt="Pictory Photobooth" width="118" height="75">
            </a>
            <div class="moment-nav-center">Pictory Moment · {{ $moment->name }}</div>
            <div class="moment-nav-end">{{ $navEnd }}</div>
        </header>

        {{ $slot }}

        <footer class="moment-footer">PICTORY PHOTOBOOTH · WE HELPED PRESERVE THE MOMENT</footer>

        <script>
            {{-- Times are stored in UTC; show them in the viewer's own time zone. --}}
            for (const time of document.querySelectorAll('time[data-local-time]')) {
                time.textContent = new Date(time.dateTime).toLocaleString('en-GB', {
                    dateStyle: 'long',
                    timeStyle: 'short',
                })
            }
        </script>
    </body>
</html>
