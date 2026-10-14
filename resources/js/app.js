/**
 * Secrets are encrypted here, in the browser, with AES-256-GCM. The key only
 * ever travels in the link's #fragment, which browsers never send to the
 * server, so the server stores and returns ciphertext it cannot read.
 *
 * Link format: https://host/{24-character id}#{base64url key}
 * Ciphertext:  base64url(12-byte IV + AES-GCM output)
 */

const IV_LENGTH = 12;

// A 256-bit key is 43 characters of unpadded base64url.
const KEY_PATTERN = /^[A-Za-z0-9_-]{43}$/;

class RequestError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

function toBase64Url(bytes) {
    let binary = '';
    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function fromBase64Url(text) {
    const binary = atob(text.replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from(binary, (char) => char.charCodeAt(0));
}

async function encrypt(plaintext) {
    const key = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, [
        'encrypt',
    ]);
    const iv = crypto.getRandomValues(new Uint8Array(IV_LENGTH));
    const encrypted = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        new TextEncoder().encode(plaintext)
    );

    const payload = new Uint8Array(IV_LENGTH + encrypted.byteLength);
    payload.set(iv);
    payload.set(new Uint8Array(encrypted), IV_LENGTH);

    return {
        ciphertext: toBase64Url(payload),
        key: toBase64Url(new Uint8Array(await crypto.subtle.exportKey('raw', key))),
    };
}

// Resolves to null for anything that isn't a well-formed key.
async function importKey(encodedKey) {
    if (!KEY_PATTERN.test(encodedKey)) {
        return null;
    }

    try {
        return await crypto.subtle.importKey(
            'raw',
            fromBase64Url(encodedKey),
            { name: 'AES-GCM' },
            false,
            ['decrypt']
        );
    } catch {
        return null;
    }
}

async function decrypt(ciphertext, key) {
    const payload = fromBase64Url(ciphertext);
    const decrypted = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: payload.slice(0, IV_LENGTH) },
        key,
        payload.slice(IV_LENGTH)
    );

    return new TextDecoder().decode(decrypted);
}

function errorMessage(status, data) {
    switch (status) {
        case 404:
            return data.message;
        case 419:
            return 'This page has expired. Reload it and try again.';
        case 422:
            return Object.values(data.errors ?? {}).flat()[0] ?? data.message;
        case 429:
            return 'Too many requests. Wait a minute and try again.';
        default:
            return 'Something went wrong. Please try again.';
    }
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
        cache: 'no-store',
        credentials: 'same-origin',
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new RequestError(errorMessage(response.status, data), response.status);
    }

    return data;
}

function showError(message) {
    const error = document.getElementById('error-msg');
    error.textContent = message;
    error.hidden = false;
}

function hideError() {
    document.getElementById('error-msg').hidden = true;
}

function initCreate(form) {
    const secret = form.querySelector('#secret');
    const expires = form.querySelector('#expires');
    const submit = form.querySelector('button[type="submit"]');
    const share = document.getElementById('share');
    const link = document.getElementById('my-link');
    // Matches the server's limit: maxlength characters of at most 3 UTF-8
    // bytes each. Some browsers count maxlength in graphemes, which can be more.
    const maxBytes = secret.maxLength * 3;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideError();

        if (new TextEncoder().encode(secret.value).length > maxBytes) {
            showError('This secret is too long. Shorten it and try again.');
            return;
        }

        submit.disabled = true;

        try {
            const { ciphertext, key } = await encrypt(secret.value);
            const { id } = await postJson('/save', { ciphertext, expires: Number(expires.value) });

            secret.value = '';
            form.hidden = true;
            link.value = `${window.location.origin}/${id}#${key}`;
            share.hidden = false;
            link.select();
        } catch (error) {
            showError(
                error instanceof RequestError
                    ? error.message
                    : 'Something went wrong. Please try again.'
            );
            submit.disabled = false;
        }
    });

    link.addEventListener('click', () => link.select());
    submit.disabled = false;
}

async function initReveal(container) {
    const button = document.getElementById('reveal-btn');
    // Read from the URL, not the page, so the id never sits in a DOM attribute.
    const id = window.location.pathname.slice(1);

    // Check the key before asking the server: requesting the secret destroys it,
    // so a cut-off or mangled link must fail here, not after.
    const key = await importKey(window.location.hash.slice(1));
    if (!key) {
        showError(
            'This link is incomplete or damaged. Make sure you have the whole link exactly as it was sent, including the part after the # sign.'
        );
        return;
    }

    button.addEventListener('click', async () => {
        button.disabled = true;
        hideError();

        let data;
        try {
            data = await postJson('/get', { id });
        } catch (error) {
            if (error instanceof RequestError) {
                showError(error.message);
                // A 404 is final; anything else never reached the secret.
                button.disabled = error.status === 404;
            } else {
                showError('Could not reach the server. Check your connection and try again.');
                button.disabled = false;
            }
            return;
        }

        // The secret is gone from the server now. Drop the key from the address bar and history too.
        history.replaceState(null, '', window.location.pathname);

        try {
            const secret = await decrypt(data.ciphertext, key);

            container.hidden = true;
            document.getElementById('secret').value = secret;
            document.getElementById('found').hidden = false;
        } catch {
            showError(
                'This secret could not be decrypted. The link may have been damaged when it was copied.'
            );
        }
    });

    button.disabled = false;
}

function initCopy(button) {
    const target = document.querySelector(button.dataset.copySelector);
    const label = button.querySelector('.copy-label');

    button.addEventListener('click', async () => {
        await navigator.clipboard.writeText(target.value);
        label.textContent = 'Copied!';
        setTimeout(() => (label.textContent = 'Copy'), 3000);
    });
}

const createForm = document.getElementById('create-secret');
const revealContainer = document.getElementById('reveal');

if (!window.isSecureContext || !window.crypto?.subtle) {
    if (createForm || revealContainer) {
        showError('This page must be opened over HTTPS to encrypt or decrypt secrets.');
    }
} else {
    if (createForm) {
        initCreate(createForm);
    }
    if (revealContainer) {
        initReveal(revealContainer);
    }
}

document.querySelectorAll('.copy-btn').forEach(initCopy);

// Don't leave a secret, or a link to one, for the back/forward cache or session restore.
window.addEventListener('pagehide', () => {
    for (const field of document.querySelectorAll('#secret, #my-link')) {
        field.value = '';
    }
});
