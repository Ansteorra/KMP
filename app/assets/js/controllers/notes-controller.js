import { Controller } from '@hotwired/stimulus';

/** Keep existing notes, disclosures, and the draft intact until a save succeeds. */
class NotesController extends Controller {
    static targets = ['list'];

    connect() {
        this.preserveDisclosure = event => {
            if (event.detail.attributeName === 'class' && event.target.matches('.collapse')) event.preventDefault();
            if (event.detail.attributeName === 'aria-expanded' && event.target.matches('[data-bs-toggle="collapse"]')) event.preventDefault();
        };
        this.element.addEventListener('turbo:before-morph-attribute', this.preserveDisclosure);
    }

    disconnect() {
        this.element.removeEventListener('turbo:before-morph-attribute', this.preserveDisclosure);
    }

    saved(event) {
        event.target.reset();
        const button = this.listTarget.lastElementChild?.querySelector('button');
        if (!this.element.closest('.tab-pane:not(.active)')) button?.focus({ preventScroll: true });
    }
}
window.Controllers ??= {};
window.Controllers.notes = NotesController;
