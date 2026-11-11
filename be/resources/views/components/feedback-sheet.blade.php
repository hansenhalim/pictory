@php
    /**
     * The sheet that asks a guest to rate the photobooth after they download a soft copy, once
     * per page. resources/js/feedback.js opens it for the paper whose download was tapped.
     */
@endphp

<dialog
    data-feedback-sheet
    aria-labelledby="feedback-title"
    class="mx-auto mt-auto mb-0 w-full max-w-md rounded-t-2xl bg-neutral-900 p-6 text-neutral-100 backdrop:bg-black/60"
>
    <form data-feedback-form class="space-y-5">
        <div class="flex items-start justify-between gap-4">
            <h2 id="feedback-title" class="text-lg font-semibold">Gimana pengalaman photobooth-nya?</h2>
            <button type="button" data-feedback-dismiss aria-label="Tutup" class="-m-2 p-2 text-neutral-400">✕</button>
        </div>

        <div role="radiogroup" aria-label="Nilai" class="flex justify-center gap-2">
            @foreach (range(1, 5) as $star)
                <button
                    type="button"
                    role="radio"
                    aria-checked="false"
                    aria-label="{{ $star }} bintang"
                    data-feedback-star="{{ $star }}"
                    class="px-1 text-4xl leading-none text-neutral-700 data-active:text-amber-400"
                >★</button>
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

        <button type="button" data-feedback-dismiss class="block w-full py-1 text-center text-sm text-neutral-400">Nanti aja</button>
    </form>

    <p data-feedback-done hidden role="status" class="py-6 text-center text-lg"></p>
</dialog>
