import { Controller } from '@hotwired/stimulus';

const decode = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
const encode = value => btoa(String.fromCharCode(...new Uint8Array(value))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

/** Native member authentication. The OS owns verification and private key storage. */
class PasskeyController extends Controller {
    static targets = ['status', 'password', 'label', 'continue', 'cancel'];

    connect() { this.connected = true; }
    disconnect() { this.connected = false; this.abort?.abort(); this.options = null; }
    message(text) { if (this.connected) this.statusTarget.textContent = text; }

    async post(action, data = {}) {
        const response = await fetch(`/passkeys/${action}`, {
            method: 'POST', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            signal: this.abort.signal, body: JSON.stringify(data)
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Passkey request failed. Retry or use your password.');
        return result;
    }

    async run(operation) {
        if (this.busy) return;
        this.busy = true;
        this.abort = new AbortController();
        const timeout = setTimeout(() => this.abort.abort(), 60000);
        try {
            await Promise.race([operation(), new Promise((resolve, reject) => {
                this.abort.signal.addEventListener('abort', () => reject(new DOMException('Cancelled', 'AbortError')), { once: true });
            })]);
        }
        catch (error) { this.message(error.name === 'AbortError' || error.name === 'NotAllowedError'
            ? 'Passkey request cancelled or timed out. Retry or use your password.' : error.message); }
        finally {
            clearTimeout(timeout); this.busy = false;
            if (this.hasPasswordTarget) this.passwordTarget.value = '';
        }
    }

    async login() {
        await this.run(async () => {
            if (!navigator.credentials?.get) throw new Error('Passkeys are unavailable in this browser. Use your password.');
            this.message('Choose your KMP login passkey…');
            const options = await this.post('login-options');
            options.publicKey.challenge = decode(options.publicKey.challenge);
            const signal = this.abort.signal;
            const credential = await navigator.credentials.get({ ...options, signal });
            signal.throwIfAborted();
            const result = await this.post('login', { credential: this.response(credential) });
            if (this.connected) window.location.assign(result.redirect);
        });
    }

    async prepareRegistration(event) {
        event.preventDefault();
        await this.run(async () => {
            this.message('Verifying your password…');
            const options = await this.post('register-options', { password: this.passwordTarget.value });
            options.publicKey.challenge = decode(options.publicKey.challenge);
            options.publicKey.user.id = decode(options.publicKey.user.id);
            options.publicKey.excludeCredentials?.forEach(item => { item.id = decode(item.id); });
            this.options = options;
            this.continueTarget.hidden = false;
            this.cancelTarget.hidden = false;
            this.message('Ready. Choose Create passkey to open your device prompt.');
            this.continueTarget.focus();
        });
    }

    async register() {
        await this.run(async () => {
            if (!this.options) throw new Error('Verify your password to start again.');
            const options = this.options;
            this.options = null;
            // A distinct tap keeps the OS prompt inside user activation on mobile Safari.
            const signal = this.abort.signal;
            const credential = await navigator.credentials.create({ ...options, signal });
            signal.throwIfAborted();
            this.message('Saving verified passkey…');
            const result = await this.post('register', { credential: this.response(credential), label: this.labelTarget.value });
            if (this.connected) window.location.assign(result.redirect);
        });
    }

    cancel() {
        this.abort?.abort(); this.options = null;
        this.continueTarget.hidden = true; this.cancelTarget.hidden = true;
        this.message('Passkey setup cancelled.'); this.passwordTarget.focus();
    }

    response(credential) {
        const response = credential.response;
        const data = { id: encode(credential.rawId), clientDataJSON: encode(response.clientDataJSON) };
        for (const field of ['attestationObject', 'authenticatorData', 'signature', 'userHandle']) {
            if (response[field]) data[field] = encode(response[field]);
        }
        return data;
    }
}
window.Controllers ||= {};
window.Controllers.passkey = PasskeyController;
export default PasskeyController;
