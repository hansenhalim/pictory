@php
    /**
     * The soft copy page a printed QR code opens. Until the kiosk's upload arrives,
     * it shows a waiting message and reloads itself.
     *
     * @var string $code
     * @var string|null $paperUrl
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
                <section class="space-y-3">
                    <img src="{{ $paperUrl }}" alt="Hasil cetak" class="w-full rounded">
                    <a
                        href="{{ $paperUrl }}"
                        download="pictory-{{ $code }}.png"
                        class="block rounded bg-white py-3 text-center font-semibold text-neutral-950"
                    >
                        Unduh hasil cetak
                    </a>
                </section>

                @if ($photoUrls)
                    <section class="space-y-3">
                        <h2 class="font-semibold">Foto</h2>

                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($photoUrls as $photoUrl)
                                <a href="{{ $photoUrl }}" download="pictory-{{ $code }}-{{ $loop->iteration }}.jpg" class="space-y-1">
                                    <img src="{{ $photoUrl }}" alt="Foto {{ $loop->iteration }}" class="aspect-square w-full rounded object-cover">
                                    <span class="block text-center text-sm text-neutral-400">Unduh</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @else
                <section class="space-y-2 py-16 text-center">
                    <p class="text-lg">Foto kamu sedang dikirim…</p>
                    <p class="text-sm text-neutral-400">Halaman ini akan memuat ulang sendiri.</p>
                </section>
            @endif
        </main>
    </body>
</html>
