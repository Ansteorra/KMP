import { Controller } from '@hotwired/stimulus';

/** Load private settings only while their Bootstrap dialog is open. */
class SecurityDialogController extends Controller {
    static targets = ['frame'];
    static values = { url: String };

    connect() {
        this.opened = event => {
            const opener = event.relatedTarget;
            this.returnFocus = opener?.closest('[data-controller="member-mobile-card-menu"]')
                ?.querySelector('[data-member-mobile-card-menu-target="fab"]') || opener;
            this.frameTarget.innerHTML = '<p role="status">Loading your passkeys…</p>';
            this.frameTarget.src = this.urlValue;
        };
        this.closed = () => {
            this.frameTarget.removeAttribute('src');
            this.frameTarget.innerHTML = '';
            this.returnFocus?.focus();
        };
        this.element.addEventListener('shown.bs.modal', this.opened);
        this.element.addEventListener('hidden.bs.modal', this.closed);
    }

    loaded() {
        if (!this.element.classList.contains('show')) { this.closed(); return; }
        this.frameTarget.querySelector('[data-passkey-target="heading"]')?.focus();
    }

    failed(event) {
        event.preventDefault();
        if (!this.element.classList.contains('show')) return;
        this.frameTarget.innerHTML = '<p role="alert">We could not load your passkeys. Close this window and try again. If you have been signed out, sign in first.</p>';
    }

    disconnect() {
        this.element.removeEventListener('shown.bs.modal', this.opened);
        this.element.removeEventListener('hidden.bs.modal', this.closed);
    }
}
window.Controllers ||= {};
window.Controllers['security-dialog'] = SecurityDialogController;
export default SecurityDialogController;
