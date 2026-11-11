@php
    /**
     * The sheet that asks a guest to rate the photobooth after they download a soft copy, once
     * per page. resources/js/feedback.js opens it for the paper whose download was tapped.
     */
@endphp

<dialog
    data-feedback-sheet
    aria-labelledby="feedback-title"
    aria-describedby="feedback-subtitle"
    class="mx-auto mt-auto mb-0 w-full max-w-md rounded-t-2xl bg-neutral-900 px-6 pt-8 pb-6 text-neutral-100 backdrop:bg-black/60"
>
    <button type="button" data-feedback-dismiss aria-label="Tutup" class="absolute top-3 right-3 p-2 leading-none text-neutral-400">✕</button>

    <form data-feedback-form class="space-y-5">
        <div class="space-y-1 text-center">
            <h2 id="feedback-title" class="text-lg font-semibold">Softcopy berhasil diunduh 🎉</h2>
            <p id="feedback-subtitle" class="text-sm text-neutral-400">Gimana pengalaman photobooth kamu di acara ini?</p>
        </div>

        <div role="radiogroup" aria-label="Nilai" class="flex justify-center gap-2">
            @foreach (range(1, 5) as $star)
                <button
                    type="button"
                    role="radio"
                    aria-checked="false"
                    aria-label="{{ $star }} bintang"
                    data-feedback-star="{{ $star }}"
                    class="p-1 text-neutral-700 transition-transform active:scale-90 data-active:text-amber-400"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="size-10 fill-current stroke-current" stroke-width="3" stroke-linejoin="round">
                        <path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z" />
                    </svg>
                </button>
            @endforeach
        </div>

        <div data-feedback-details hidden class="space-y-3">
            <label for="feedback-comment" class="block text-sm text-neutral-400">Ada yang mau diceritain? (opsional)</label>
            <textarea
                id="feedback-comment"
                name="comment"
                rows="3"
                maxlength="{{ \App\Http\Requests\StorePaperFeedbackRequest::COMMENT_MAX_LENGTH }}"
                class="block w-full rounded bg-neutral-800 p-3 text-neutral-100"
            ></textarea>
            <button type="submit" class="block w-full rounded bg-white py-3 text-center font-semibold text-neutral-950 disabled:opacity-50">
                Kirim
            </button>
        </div>

        <p data-feedback-error hidden role="alert" class="text-center text-sm text-red-400">Gagal mengirim, coba lagi ya.</p>
    </form>

    <p data-feedback-done hidden role="status" class="py-6 text-center text-lg"></p>
</dialog>
