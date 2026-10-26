@php
    /**
     * The soft copy page a printed QR code opens. Until the kiosk's upload arrives,
     * it shows a waiting message and reloads itself.
     *
     * @var string $code
     * @var string|null $paperUrl
     * @var string|null $clipUrl
     * @var list<string> $photoUrls
     */
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>pictory.photo</title>
        @unless ($paperUrl)
            <meta http-equiv="refresh" content="5">
        @endunless
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-neutral-950 text-neutral-100 antialiased">
        <main class="mx-auto max-w-md space-y-8 px-4 py-8">
            <p class="text-center text-lg font-semibold tracking-wide">pictory.photo</p>

            @if ($paperUrl)
                <x-soft-copy :download-name="'pictory-'.$code" :paper-url="$paperUrl" :clip-url="$clipUrl" :photo-urls="$photoUrls" />
            @else
                <section class="space-y-2 py-16 text-center">
                    <p class="text-lg">Foto kamu sedang dikirim…</p>
                    <p class="text-sm text-neutral-400">Halaman ini akan memuat ulang sendiri.</p>
                </section>
            @endif
        </main>
    </body>
</html>
