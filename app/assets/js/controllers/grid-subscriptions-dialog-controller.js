import { Controller } from '@hotwired/stimulus';

/** Manage subscriptions in the profile without navigating away after cancellation. */
class GridSubscriptionsDialogController extends Controller {
    static targets = ['frame', 'heading'];
    static values = { url: String, autoOpen: Boolean };

    connect() {
        if (this.autoOpenValue) {
            this.autoOpenRequest = requestAnimationFrame(() => {
                window.bootstrap.Modal.getOrCreateInstance(this.element)
                    .show(document.getElementById('emailSubscriptionsButton'));
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
    }
}
window.Controllers ||= {};
window.Controllers['grid-subscriptions-dialog'] = GridSubscriptionsDialogController;
export default GridSubscriptionsDialogController;
