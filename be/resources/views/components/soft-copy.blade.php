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
    /** Where a guest's note for the moment's hosts is posted. */
    'noteUrl',
    /** The signed note a guest already left on this paper, which stands for everyone in it. */
    'note' => null,
])

@php
    $noteId = 'note-'.crc32($noteUrl);
@endphp

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

    <section class="space-y-3 rounded-lg bg-neutral-900 p-4" data-note-url="{{ $noteUrl }}">
        <figure data-note-shown @unless ($note) hidden @endunless class="space-y-2">
            <h2 class="font-semibold">Ucapan</h2>
            <blockquote data-note-text class="text-lg whitespace-pre-line text-neutral-100">{{ $note }}</blockquote>
            <figcaption data-note-sent hidden class="text-sm text-neutral-400">Terkirim, terima kasih!</figcaption>
        </figure>

        @unless ($note)
            <form data-note-form class="space-y-3">
                <h2 class="font-semibold">Tinggalkan ucapan</h2>
                <p class="text-sm text-neutral-400">Ucapanmu akan ikut tampil di album acara ini.</p>
                <label for="{{ $noteId }}" class="sr-only">Ucapan</label>
                <textarea
                    id="{{ $noteId }}"
                    name="note"
                    rows="4"
                    required
                    maxlength="{{ \App\Http\Requests\StorePaperNoteRequest::NOTE_MAX_LENGTH }}"
                    placeholder="Tulis ucapanmu di sini…"
                    class="block w-full rounded bg-neutral-800 p-3 text-neutral-100 placeholder:text-neutral-500"
                ></textarea>
                <label class="block space-y-1">
                    <span class="text-sm text-neutral-400">Dari</span>
                    <input
                        type="text"
                        name="sender"
                        required
                        maxlength="{{ \App\Http\Requests\StorePaperNoteRequest::SENDER_MAX_LENGTH }}"
                        autocomplete="name"
                        placeholder="Nama kamu"
                        class="block w-full rounded bg-neutral-800 px-3 py-2 text-neutral-100 placeholder:text-neutral-500"
                    >
                </label>
                <p data-note-error hidden role="alert" class="text-sm text-red-400">Gagal mengirim, coba lagi ya.</p>
                <button type="submit" class="block w-full rounded bg-white py-3 text-center font-semibold text-neutral-950 disabled:opacity-50">
                    Kirim ucapan
                </button>
            </form>
        @endunless
    </section>
</div>
