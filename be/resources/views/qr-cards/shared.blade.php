@php
    /**
     * The soft copy page a QR card opens: every session assigned to the card, newest first.
     * Until the first one arrives, it shows a waiting message and reloads itself.
     *
     * @var list<array{takenAt: \Illuminate\Support\Carbon, downloadName: string, paperUrl: string, clipUrl: string|null, photoUrls: list<string>}> $sessions
     */
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>pictory.photo</title>
        @unless ($sessions)
            <meta http-equiv="refresh" content="5">
        @endunless
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-neutral-950 text-neutral-100 antialiased">
        <main class="mx-auto max-w-md space-y-8 px-4 py-8">
            <p class="text-center text-lg font-semibold tracking-wide">pictory.photo</p>

            @forelse ($sessions as $session)
                <article class="space-y-6 border-t border-neutral-800 pt-8 first-of-type:border-0 first-of-type:pt-0">
                    <h2 class="text-sm text-neutral-400">
                        <time datetime="{{ $session['takenAt']->toIso8601String() }}" data-local-time></time>
                    </h2>

                    <x-soft-copy
                        :download-name="$session['downloadName']"
                        :paper-url="$session['paperUrl']"
                        :clip-url="$session['clipUrl']"
                        :photo-urls="$session['photoUrls']"
                    />
                </article>
            @empty
                <section class="space-y-2 py-16 text-center">
                    <p class="text-lg">Foto kamu sedang dikirim…</p>
                    <p class="text-sm text-neutral-400">Halaman ini akan memuat ulang sendiri.</p>
                </section>
            @endforelse
        </main>

        <script>
            {{-- Times are stored in UTC; show them in the customer's own time zone. --}}
            for (const time of document.querySelectorAll('time[data-local-time]')) {
                time.textContent = new Date(time.dateTime).toLocaleString('id-ID', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                })
            }
        </script>
    </body>
</html>
