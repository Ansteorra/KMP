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
    static values = {
        successMessage: String,
        errorMessage: String,
    }

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
        event.stopImmediatePropagation();
        this.syncPageContext();
        this.setSubmitting(true);

        try {
            const csrfToken = this.element.querySelector('input[name="_csrfToken"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content;
            const headers = {
                'Accept': 'text/vnd.turbo-stream.html',
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (csrfToken) {
                headers['X-CSRF-Token'] = csrfToken;
            }

            const response = await fetch(this.element.action, {
                method: (this.element.method || 'POST').toUpperCase(),
                body: new FormData(this.element),
                headers,
                credentials: 'same-origin',
            });
            const body = await response.text();
            const contentType = response.headers.get('Content-Type') || '';

            if (contentType.includes('text/vnd.turbo-stream.html') || body.includes('<turbo-stream')) {
                if (response.ok) {
                    // Hide first so visible flash feedback is not left behind the modal.
                    this.closeModal();
                }
                try {
                    this.renderTurboStream(body);
                } catch (renderError) {
                    if (!response.ok) {
                        throw renderError;
                    }
                    console.error('Unable to render successful modal response:', renderError);
                    this.showFallbackSuccess();
                }
                if (response.ok) {
                    this.announceSuccess();
                } else {
                    this.announceFailure();
                }
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
        } catch (error) {
            console.error('Unable to submit modal form:', error);
            this.announceFailure();
        } finally {
            this.setSubmitting(false);
        }
    }

    /** Sync hidden page context to the visible browser URL before posting. */
    syncPageContext() {
        const input = this.element.querySelector('input[name="page_context_url"]');
        if (input && input.dataset.pageContextStatic !== 'true') {
            input.value = window.location.pathname + window.location.search;
        }
    }

    /**
     * @param {string} streamHtml Turbo stream HTML
     */
    renderTurboStream(streamHtml) {
        Turbo.renderStreamMessage(streamHtml);
    }

    /**
     * Resolve the Bootstrap modal for this form.
     *
     * Officers (and similar) wrap the modal markup inside the form; others nest the
     * form inside the modal. Support both DOM shapes.
     */
    findModalElement() {
        if (!(this.element instanceof HTMLFormElement)) {
            return null;
        }

        return this.element.querySelector('.modal') ?? this.element.closest('.modal');
    }

    /** Hide the containing Bootstrap modal if one exists. */
    closeModal() {
        const modal = this.findModalElement();
        if (!modal || !window.bootstrap?.Modal) {
            return;
        }

        const Modal = window.bootstrap.Modal;
        let modalInstance = Modal.getInstance(modal);
        if (!modalInstance) {
            if (typeof Modal.getOrCreateInstance !== 'function') {
                return;
            }
            modalInstance = Modal.getOrCreateInstance(modal);
        }
        modalInstance.hide();
        this.dismissModalBackdrop();
    }

    /** Announce successful completion after the modal starts closing. */
    announceSuccess() {
        window.KMP_accessibility?.announce?.(this.successMessage());
    }

    /** Announce a failed submission after any server-provided stream is rendered. */
    announceFailure() {
        window.KMP_accessibility?.announce?.(
            this.errorMessageValue
                || this.element.dataset.turboModalErrorMessageValue
                || 'Unable to save. Please try again.',
            { assertive: true },
        );
    }

    /** Return the configured success copy for visible and announced feedback. */
    successMessage() {
        return this.successMessageValue
            || this.element.dataset.turboModalSuccessMessageValue
            || 'Saved successfully.';
    }

    /** Show visible feedback if Turbo cannot apply an otherwise successful stream. */
    showFallbackSuccess() {
        const container = document.getElementById('flash-messages');
        if (!container) {
            return;
        }

        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show';
        alert.setAttribute('role', 'status');
        alert.append(document.createTextNode(this.successMessage()));

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.setAttribute('data-bs-dismiss', 'alert');
        closeButton.setAttribute('aria-label', 'Close');
        alert.append(closeButton);
        container.replaceChildren(alert);
    }

    /** Prevent duplicate submits while the Turbo Stream request is in-flight. */
    setSubmitting(isSubmitting) {
        const controls = Array.from(
            this.element.querySelectorAll('button[type="submit"], input[type="submit"]'),
        );
        if (this.element.id) {
            const escapedId = window.CSS?.escape
                ? window.CSS.escape(this.element.id)
                : this.element.id.replace(/(["\\])/g, '\\$1');
            controls.push(...document.querySelectorAll(
                `button[type="submit"][form="${escapedId}"], input[type="submit"][form="${escapedId}"]`,
            ));
        }

        new Set(controls).forEach((control) => {
            control.disabled = isSubmitting;
            control.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
        });
    }

    /** Remove stray backdrops when Bootstrap did not fully tear down the modal. */
    dismissModalBackdrop() {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["turbo-modal"] = TurboModal;
