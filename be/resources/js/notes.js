/**
 * Let a guest leave a signed note on a paper for the moment's hosts, e.g. a wish for the couple.
 *
 * A paper takes one note: the first guest to send one writes it for everyone in the photo,
 * which the server enforces. Whoever opens the page afterwards sees the note instead of the form.
 */

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

const storageKey = (url) => `pictory:note:${url}`;

const wasSentHere = (url) => {
    try {
        return localStorage.getItem(storageKey(url)) !== null;
    } catch {
        return false;
    }
};

const rememberSent = (url) => {
    try {
        localStorage.setItem(storageKey(url), 'sent');
    } catch {
        // Without storage the note just shows without "sent", which is harmless.
    }
};

for (const section of document.querySelectorAll('[data-note-url]')) {
    const url = section.dataset.noteUrl;
    const shown = section.querySelector('[data-note-shown]');
    const text = section.querySelector('[data-note-text]');
    const sent = section.querySelector('[data-note-sent]');
    const form = section.querySelector('[data-note-form]');

    const show = (note, isSentHere) => {
        text.textContent = note;
        sent.hidden = !isSentHere;
        shown.hidden = false;
        form?.remove();
    };

    if (!shown.hidden) {
        sent.hidden = !wasSentHere(url);
    }

    if (!form) {
        continue;
    }

    const textarea = form.querySelector('textarea');
    const senderInput = form.querySelector('input[name="sender"]');
    const submit = form.querySelector('button[type="submit"]');
    const error = form.querySelector('[data-note-error]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const note = textarea.value.trim();
        const sender = senderInput.value.trim();

        if (!note || !sender) {
            (note ? senderInput : textarea).focus();
            return;
        }

        submit.disabled = true;
        error.hidden = true;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ note, sender }),
            });

            if (response.status !== 201 && response.status !== 409) {
                throw new Error(`Sending the note failed with ${response.status}`);
            }

            const { data } = await response.json();

            if (response.status === 201) {
                rememberSent(url);
            }

            // The server answers with the note as stored, signature included. On 409 it is the
            // note someone else in the photo wrote first.
            show(data.note, response.status === 201);
        } catch {
            error.hidden = false;
            submit.disabled = false;
        }
    });
}
