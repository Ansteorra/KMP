import { Controller } from '@hotwired/stimulus';

const decode = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
const encode = value => btoa(String.fromCharCode(...new Uint8Array(value))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

/** Native member authentication. The OS owns verification and private key storage. */
class PasskeyController extends Controller {
    static targets = ['status', 'password', 'label', 'continue', 'panel', 'heading', 'progress', 'work', 'removeName'];
    static values = { initial: String, autofill: Boolean };

    connect() {
        this.connected = true;
        if (this.autofillValue) this.autofill();
        if (this.hasHeadingTarget) this.show(this.initialValue || 'intro', false);
        this.modal = this.element.closest('.modal');
        this.onClosing = () => { this.connected = false; this.abort?.abort(); this.options = null; this.clearPassword(); };
        this.modal?.addEventListener('hide.bs.modal', this.onClosing);
    }
    disconnect() {
        this.cancelAutofill();
        this.connected = false; this.abort?.abort(); this.options = null; this.clearPassword();
        this.modal?.removeEventListener('hide.bs.modal', this.onClosing);
    }
    clearPassword() { if (this.hasPasswordTarget) this.passwordTarget.value = ''; }

    show(step, focus = true) {
        this.step = step;
        const titles = { manage: 'Your passkeys', intro: 'An easier way to sign in', password: 'Confirm it is you',
            device: 'Save your passkey', success: 'Your passkey is ready', remove: 'Remove this passkey?', removed: 'Passkey removed' };
        this.panelTargets.forEach(panel => { panel.hidden = panel.dataset.step !== step; });
        this.headingTarget.textContent = titles[step];
        this.progressTarget.textContent = ({ intro: 'Step 1 of 3', password: 'Step 2 of 3', device: 'Step 3 of 3', success: 'Setup complete' })[step] || '';
        this.message('');
        if (focus) this.headingTarget.focus();
    }

    start() { this.abort?.abort(); this.options = null; this.clearPassword(); this.show('intro'); }
    passwordStep() { this.abort?.abort(); this.options = null; this.clearPassword(); this.show('password'); }
    manage() { this.show('manage'); }
    refresh() {
        const frame = this.element.closest('turbo-frame');
        frame.dataset.securitySection = 'passkeys';
        frame.reload();
    }
    confirmRemove(event) {
        this.removeId = event.currentTarget.dataset.id;
        this.removeNameTarget.textContent = event.currentTarget.dataset.label;
        this.show('remove');
    }
    async remove() {
        await this.run(async () => {
            await this.post(`delete/${encodeURIComponent(this.removeId)}`);
            if (this.connected) this.show('removed');
        });
    }
    message(text) { if (this.connected) this.statusTarget.textContent = text; }

    async post(action, data = {}, signal = this.abort.signal) {
        const response = await fetch(`/passkeys/${action}`, {
            method: 'POST', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            signal, body: JSON.stringify(data)
        });
        let result;
        try { result = await response.json(); }
        catch { throw new Error('We could not complete the request. Close this window and try again.'); }
        signal.throwIfAborted();
        if (!response.ok) {
            if (action === 'register-options' && response.status === 400) {
                throw new Error('We could not confirm your password. Check your KMP password and try again. If you have tried several times, wait a few minutes.');
            }
            throw new Error(result.error || 'We could not complete the request. Please try again.');
        }
        return result;
    }

    async run(operation) {
        if (this.busy) return;
        this.busy = true;
        (this.workTargets || []).forEach(button => { button.disabled = true; });
        this.abort = new AbortController();
        const timeout = setTimeout(() => this.abort.abort(), 60000);
        try {
            await Promise.race([operation(), new Promise((resolve, reject) => {
                this.abort.signal.addEventListener('abort', () => reject(new DOMException('Cancelled', 'AbortError')), { once: true });
            })]);
        }
        catch (error) {
            if (this.connected && this.step === 'device' && !this.options) this.show('password');
            if (this.connected && this.step === 'password') this.passwordTarget.setAttribute('aria-invalid', 'true');
            this.message(error.name === 'AbortError' || error.name === 'NotAllowedError'
                ? 'Passkey request cancelled or timed out. You can try again or keep using your password.' : error.message);
        }
        finally {
            clearTimeout(timeout); this.busy = false;
            this.clearPassword();
            (this.workTargets || []).forEach(button => { button.disabled = false; });
        }
    }

    cancelAutofill() { this.autofillAbort?.abort(); }
    cancelLogin() { this.cancelAutofill(); this.abort?.abort(); }

    async autofill() {
        if (!this.autofillValue || this.autofillPending || this.busy || !this.connected) return;
        const abort = new AbortController();
        this.autofillAbort = abort;
        this.autofillPending = Promise.race([
            this.offerAutofill(abort),
            new Promise(resolve => abort.signal.addEventListener('abort', resolve, { once: true }))
        ]);
        try { await this.autofillPending; }
        finally { this.autofillPending = null; }
    }

    async offerAutofill(abort) {
        const signal = abort.signal;
        // Bound the pending request to less than the server challenge lifetime.
        const timeout = setTimeout(() => abort.abort(), 90000);
        signal.addEventListener('abort', () => clearTimeout(timeout), { once: true });
        try {
            if (!await window.PublicKeyCredential?.isConditionalMediationAvailable?.()) return;
            signal.throwIfAborted();
            const options = await this.post('login-options', {}, signal);
            options.publicKey.challenge = decode(options.publicKey.challenge);
            const credential = await navigator.credentials.get({ ...options, mediation: 'conditional', signal });
            signal.throwIfAborted();
            if (!credential) return;
            const result = await this.post('login', { credential: this.response(credential) }, signal);
            if (this.connected) window.location.assign(result.redirect);
        } catch { /* Autofill is optional. Password and explicit passkey login remain available. */ }
        finally { clearTimeout(timeout); }
    }

    async login() {
        this.cancelAutofill();
        await this.autofillPending;
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
            this.passwordTarget.removeAttribute('aria-invalid');
            this.message('Verifying your password…');
            const options = await this.post('register-options', { password: this.passwordTarget.value });
            options.publicKey.challenge = decode(options.publicKey.challenge);
            options.publicKey.user.id = decode(options.publicKey.user.id);
            options.publicKey.excludeCredentials?.forEach(item => { item.id = decode(item.id); });
            this.options = options;
            this.show('device');
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
            await this.post('register', { credential: this.response(credential), label: this.labelTarget.value });
            if (this.connected) this.show('success');
        });
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
