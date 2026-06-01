import { Controller } from "@hotwired/stimulus";
import * as Turbo from "@hotwired/turbo";

/**
 * TurboModal Stimulus Controller
 * 
 * Handles modal closing before form submission to prevent modal being open during
 * Turbo Stream updates. Closes the modal when form is submitted, allowing the
 * background page to update cleanly.
 * 
 * Features:
 * - Modal closing before form submission
 * - Bootstrap modal integration
 * - Turbo Form submission handling
 * - Prevents modal from interfering with page updates
 * 
 * Usage:
 * <form data-controller="turbo-modal"
 *       data-action="submit->turbo-modal#submitAsTurboStream turbo:submit-start->turbo-modal#closeModalBeforeSubmit"
 *       data-turbo="true">
 *   <!-- Form contents -->
 * </form>
 * 
 * The modal will close immediately when the form is submitted.
 */
class TurboModal extends Controller {
    /**
     * Initialize - log when controller connects
     */
    connect() {
        console.log('TurboModal controller connected');
    }
    
    /**
     * Close the modal before form submission starts
     * 
     * @param {Event} event - The turbo:submit-start event
     */
    closeModalBeforeSubmit(event) {
        console.log('turbo:submit-start - closing modal before submission');

        this.closeModal();
    }

    /**
     * Submit a modal form as a Turbo Stream request.
     *
     * Turbo Drive is disabled globally, so modal forms that should update the
     * current grid need an explicit stream fetch instead of browser navigation.
     *
     * @param {SubmitEvent} event - The submit event
     */
    async submitAsTurboStream(event) {
        if (!(this.element instanceof HTMLFormElement)) {
            return;
        }

        event.preventDefault();
        this.syncPageContext();
        this.closeModal();

        const response = await fetch(this.element.action, {
            method: (this.element.method || 'POST').toUpperCase(),
            body: new FormData(this.element),
            headers: {
                'Accept': 'text/vnd.turbo-stream.html',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const body = await response.text();
        const contentType = response.headers.get('Content-Type') || '';

        if (contentType.includes('text/vnd.turbo-stream.html') || body.includes('<turbo-stream')) {
            this.renderTurboStream(body);
            return;
        }

        if (response.redirected) {
            window.location.assign(response.url);
            return;
        }

        const frame = this.element.closest('turbo-frame');
        if (frame && body !== '') {
            frame.innerHTML = body;
        }
    }

    /** Sync hidden page context to the visible browser URL before posting. */
    syncPageContext() {
        const input = this.element.querySelector('input[name="page_context_url"]');
        if (input) {
            input.value = window.location.pathname + window.location.search;
        }
    }

    /**
     * @param {string} streamHtml Turbo stream HTML
     */
    renderTurboStream(streamHtml) {
        Turbo.renderStreamMessage(streamHtml);
    }

    /** Hide the containing Bootstrap modal if one exists. */
    closeModal() {
        // Find the modal element (closest modal parent)
        const modal = this.element.closest('.modal');
        if (modal && window.bootstrap?.Modal) {
            const modalInstance = window.bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                console.log('Closing modal...');
                modalInstance.hide();
            }
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["turbo-modal"] = TurboModal;
