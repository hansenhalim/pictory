/**
 * Ask a guest to rate the photobooth once they download any part of a soft copy.
 *
 * Each paper is rated once: the first guest to submit closes it for everyone, which the server
 * enforces and the page learns from `data-feedback-rated`. A guest who closes the sheet without
 * submitting is not asked about that paper again on this device.
 */

const sheet = document.querySelector('[data-feedback-sheet]');

/** How long to wait after a download tap, so the download starts before the sheet covers the page. */
const OPEN_DELAY_MS = 600;

if (sheet) {
    const form = sheet.querySelector('[data-feedback-form]');
    const stars = [...sheet.querySelectorAll('[data-feedback-star]')];
    const details = sheet.querySelector('[data-feedback-details]');
    const comment = sheet.querySelector('textarea');
    const submit = sheet.querySelector('button[type="submit"]');
    const error = sheet.querySelector('[data-feedback-error]');
    const done = sheet.querySelector('[data-feedback-done]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    /** The paper's element the sheet is open for. */
    let paper = null;
    let rating = 0;
    let isFinished = false;

    const storageKey = (url) => `pictory:feedback:${url}`;

    const isRemembered = (url) => {
        try {
            return localStorage.getItem(storageKey(url)) !== null;
        } catch {
            return false;
        }
    };

    const remember = (url, outcome) => {
        try {
            localStorage.setItem(storageKey(url), outcome);
        } catch {
            // Without storage the guest may be asked again, which is harmless.
        }
    };

    const setRating = (value) => {
        rating = value;
        for (const star of stars) {
            const isActive = Number(star.dataset.feedbackStar) <= value;
            star.toggleAttribute('data-active', isActive);
            star.setAttribute('aria-checked', String(Number(star.dataset.feedbackStar) === value));
        }
        details.hidden = false;
    };

    const open = (target) => {
        paper = target;
        isFinished = false;
        rating = 0;
        form.reset();
        form.hidden = false;
        details.hidden = true;
        error.hidden = true;
        done.hidden = true;
        submit.disabled = false;
        for (const star of stars) {
            star.removeAttribute('data-active');
            star.setAttribute('aria-checked', 'false');
        }
        sheet.showModal();
    };

    const finish = (message) => {
        isFinished = true;
        paper.toggleAttribute('data-feedback-rated', true);
        remember(paper.dataset.feedbackUrl, 'rated');
        form.hidden = true;
        done.textContent = message;
        done.hidden = false;
        setTimeout(() => sheet.close(), 2000);
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[download]');
        const target = link?.closest('[data-feedback-url]');

        if (!target || target.hasAttribute('data-feedback-rated') || isRemembered(target.dataset.feedbackUrl)) {
            return;
        }

        setTimeout(() => {
            if (!sheet.open) {
                open(target);
            }
        }, OPEN_DELAY_MS);
    });

    for (const star of stars) {
        star.addEventListener('click', () => setRating(Number(star.dataset.feedbackStar)));
    }

    /**
     * Close the sheet without a submit, so this guest is not asked about the paper again. It is
     * remembered right away rather than on `close`, which fires only after the page may have moved on.
     */
    const dismiss = () => {
        if (paper && !isFinished) {
            remember(paper.dataset.feedbackUrl, 'dismissed');
        }
        sheet.close();
    };

    for (const button of sheet.querySelectorAll('[data-feedback-dismiss]')) {
        button.addEventListener('click', dismiss);
    }

    // A tap on the backdrop lands on the dialog itself, as does one on its padding.
    sheet.addEventListener('click', (event) => {
        const box = sheet.getBoundingClientRect();
        const isOutside = event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom;

        if (event.target === sheet && isOutside) {
            dismiss();
        }
    });

    // Escape, or the back gesture on Android.
    sheet.addEventListener('cancel', (event) => {
        event.preventDefault();
        dismiss();
    });

    sheet.addEventListener('close', () => {
        paper = null;
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!rating || !paper) {
            return;
        }

        submit.disabled = true;
        error.hidden = true;

        try {
            const response = await fetch(paper.dataset.feedbackUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ rating, comment: comment.value }),
            });

            if (response.status === 201) {
                finish('Makasih! 🙏');
            } else if (response.status === 409) {
                finish('Foto ini sudah dinilai, makasih!');
            } else {
                throw new Error(`Feedback failed with ${response.status}`);
            }
        } catch {
            error.hidden = false;
            submit.disabled = false;
        }
    });
}
