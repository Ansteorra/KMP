import { Controller } from '@hotwired/stimulus';

/** Loads the shared activity editor through the existing Bootstrap and Turbo Frame lifecycle. */
class GatheringActivityModalController extends Controller {
    static targets = ['frame', 'title', 'loading', 'error', 'form'];
    static values = { addUrl: String, editUrl: String, addTitle: String, editTitle: String };

    load(event) {
        const id = event.relatedTarget?.dataset.activityId;
        this.titleTarget.textContent = id ? this.editTitleValue : this.addTitleValue;
        this.frameTarget.removeAttribute('src');
        this.frameTarget.replaceChildren(this.loadingTarget.content.cloneNode(true));
        this.frameTarget.src = id ? `${this.editUrlValue}/${encodeURIComponent(id)}` : this.addUrlValue;
    }

    focusForm() {
        if (this.element.classList.contains('show')) {
            const field = this.frameTarget.querySelector('input.is-invalid, select.is-invalid, textarea.is-invalid, [aria-invalid="true"]')
                || this.frameTarget.querySelector('input[name="name"]');
            field?.focus();
        }
    }

    formTargetConnected() {
        this.focusForm();
    }

    missing(event) {
        event.preventDefault();
        this.failed();
    }

    failed() {
        this.frameTarget.replaceChildren(this.errorTarget.content.cloneNode(true));
    }
}

window.Controllers = window.Controllers || {};
window.Controllers['gathering-activity-modal'] = GatheringActivityModalController;
export default GatheringActivityModalController;
