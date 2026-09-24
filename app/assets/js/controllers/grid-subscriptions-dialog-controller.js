import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/** Manage subscriptions in the profile without navigating away after cancellation. */
class GridSubscriptionsDialogController extends Controller {
    static targets = ['frame', 'heading'];
    static values = { url: String, autoOpen: Boolean };

    connect() {
        this.modal = Modal.getOrCreateInstance(this.element);
        this.modalOpening = false;
        this.onModalOpening = () => { this.modalOpening = true; };
        this.onModalShown = () => { this.modalOpening = false; };
        this.element.addEventListener('show.bs.modal', this.onModalOpening);
        this.element.addEventListener('shown.bs.modal', this.onModalShown);
        if (this.autoOpenValue) {
            this.autoOpenRequest = requestAnimationFrame(() => {
                this.modal.show(document.getElementById('emailSubscriptionsButton'));
            });
        }
    }

    opened(event) {
        this.returnFocus = event.relatedTarget;
        this.frameTarget.innerHTML = '<p role="status">Loading email subscriptions…</p>';
        this.frameTarget.src = this.urlValue;
        this.headingTarget.focus();
    }

    closed() {
        this.frameTarget.removeAttribute('src');
        this.frameTarget.innerHTML = '';
        this.returnFocus?.focus();
    }

    loaded() {
        if (!this.element.classList.contains('show')) { this.closed(); return; }
        const feedback = this.frameTarget.querySelector('[data-subscription-feedback]');
        if (feedback?.textContent.trim()) feedback.focus();
    }

    requestCancellation(event) {
        const button = event.currentTarget;
        const confirmation = button.form.querySelector('[data-cancel-confirmation]');
        button.hidden = true;
        confirmation.hidden = false;
        confirmation.querySelector('[data-keep-subscription]').focus();
    }

    keepSubscription(event) {
        const form = event.currentTarget.form;
        form.querySelector('[data-cancel-confirmation]').hidden = true;
        const button = form.querySelector('[data-cancel-subscription]');
        button.hidden = false;
        button.focus();
    }

    failed(event) {
        event.preventDefault();
        if (!this.element.classList.contains('show')) return;
        this.frameTarget.innerHTML = '<p role="alert" tabindex="-1">We could not load email subscriptions. Close this window and try again. If you have been signed out, sign in first.</p>';
        this.frameTarget.querySelector('[role="alert"]').focus();
    }

    disconnect() {
        cancelAnimationFrame(this.autoOpenRequest);
        this.element.removeEventListener('show.bs.modal', this.onModalOpening);
        this.element.removeEventListener('shown.bs.modal', this.onModalShown);
        const modal = this.modal;
        this.modal = null;
        if (!modal) return;
        if (this.modalOpening || this.element.getAttribute('aria-modal') === 'true') {
            // Let Bootstrap release its backdrop, scroll lock and transition callbacks first.
            const wasRemoved = !this.element.isConnected;
            const hide = () => modal.hide();
            this.element.addEventListener('hidden.bs.modal', () => {
                this.element.removeEventListener('shown.bs.modal', hide);
                if (this.modal === modal) return; // Reconnected while the hide was completing.
                modal.dispose();
                if (wasRemoved) this.element.remove();
            }, { once: true });
            this.element.addEventListener('shown.bs.modal', hide, { once: true });
            modal.hide();
        } else {
            modal.dispose();
        }
    }
}
window.Controllers ||= {};
window.Controllers['grid-subscriptions-dialog'] = GridSubscriptionsDialogController;
export default GridSubscriptionsDialogController;
