import { Controller } from "@hotwired/stimulus";
import * as Turbo from "@hotwired/turbo";

/**
 * TurboModal Stimulus Controller
 * 
 * Handles explicit Turbo Stream form submission while coordinating Bootstrap
 * modal transitions, visible feedback, and focus restoration after page updates.
 * 
 * Features:
 * - Modal closing before successful Turbo Stream rendering
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
 * Successful stream responses close the modal before updating the page. Failed
 * requests leave it open so the user can review feedback and retry.
 */
class TurboModal extends Controller {
    static values = {
        successMessage: String,
        errorMessage: String,
    }

    initialize() {
        this.modalTrigger = null;
        this.captureModalTriggerListener = this.captureModalTrigger.bind(this);
        this.modalHideCleanup = null;
    }

    /**
     * Initialize - log when controller connects
     */
    connect() {
        console.log('TurboModal controller connected');
        this.findModalElement()?.addEventListener(
            'show.bs.modal',
            this.captureModalTriggerListener,
        );
    }

    disconnect() {
        this.findModalElement()?.removeEventListener(
            'show.bs.modal',
            this.captureModalTriggerListener,
        );
        this.modalHideCleanup?.();
        this.modalHideCleanup = null;
    }

    /** Remember the control that opened the modal for focus restoration. */
    captureModalTrigger(event) {
        this.modalTrigger = event.relatedTarget instanceof HTMLElement
            ? event.relatedTarget
            : null;
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
        this.clearFailure();
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
                const focusPlan = response.ok ? this.createStreamFocusPlan(body) : null;
                const streamIncludesLiveFeedback = this.streamIncludesLiveFeedback(body);
                let streamRendered = true;
                if (response.ok) {
                    await this.closeModalAndWait();
                }
                try {
                    this.renderTurboStream(body);
                } catch (renderError) {
                    if (!response.ok) {
                        throw renderError;
                    }
                    console.error('Unable to render successful modal response:', renderError);
                    streamRendered = false;
                    if (!this.showFallbackSuccess()) {
                        this.announceSuccess();
                    }
                }
                if (response.ok) {
                    await this.restoreFocusAfterStream(focusPlan);
                    if (streamRendered && !streamIncludesLiveFeedback) {
                        this.announceSuccess();
                    }
                } else if (!streamIncludesLiveFeedback && !this.showFallbackFailure()) {
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
            if (!response.ok) {
                if (!this.showFallbackFailure()) {
                    this.announceFailure();
                }
            }
        } catch (error) {
            console.error('Unable to submit modal form:', error);
            if (!this.showFallbackFailure()) {
                this.announceFailure();
            }
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

    /** Return true when the stream already provides an accessible live-region message. */
    streamIncludesLiveFeedback(streamHtml) {
        const fragment = document.createElement('template');
        fragment.innerHTML = streamHtml;
        const liveRegionSelector = '[role="alert"], [role="status"], [aria-live]';
        if (fragment.content.querySelector(liveRegionSelector)) {
            return true;
        }

        return Array.from(fragment.content.querySelectorAll('template')).some(
            (template) => template.content.querySelector(liveRegionSelector) !== null,
        );
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

    /** Return the Bootstrap instance for a modal, creating it when supported. */
    modalInstance(modal) {
        if (!modal || !window.bootstrap?.Modal) {
            return null;
        }

        const Modal = window.bootstrap.Modal;
        const existingInstance = Modal.getInstance(modal);
        if (existingInstance) {
            return existingInstance;
        }

        return typeof Modal.getOrCreateInstance === 'function'
            ? Modal.getOrCreateInstance(modal)
            : null;
    }

    /** Hide the containing Bootstrap modal if one exists. */
    closeModal() {
        const modal = this.findModalElement();
        const modalInstance = this.modalInstance(modal);
        if (!modal || !modalInstance) {
            return;
        }

        modalInstance.hide();
        this.dismissModalBackdrop();
    }

    /** Hide a visible modal and resolve only after Bootstrap finishes its transition. */
    async closeModalAndWait() {
        const modal = this.findModalElement();
        const modalInstance = this.modalInstance(modal);
        if (!modal || !modalInstance) {
            return;
        }

        const isVisible = modal.classList.contains('show')
            && modal.getAttribute('aria-hidden') !== 'true';
        if (!isVisible) {
            modalInstance.hide();
            this.dismissModalBackdrop();
            return;
        }

        await new Promise((resolve, reject) => {
            let timeoutId;
            const cleanup = () => {
                modal.removeEventListener('hidden.bs.modal', finish);
                window.clearTimeout(timeoutId);
                if (this.modalHideCleanup === finish) {
                    this.modalHideCleanup = null;
                }
            };
            const finish = () => {
                cleanup();
                resolve();
            };

            modal.addEventListener('hidden.bs.modal', finish, { once: true });
            timeoutId = window.setTimeout(finish, 750);
            this.modalHideCleanup = finish;
            try {
                modalInstance.hide();
            } catch (error) {
                cleanup();
                reject(error);
            }
        });
        this.dismissModalBackdrop();
    }

    /**
     * Capture enough of a row-changing Turbo Stream to select a logical focus target afterward.
     *
     * @param {string} streamHtml Turbo Stream response body.
     * @returns {object|null} Focus restoration plan.
     */
    createStreamFocusPlan(streamHtml) {
        const template = document.createElement('template');
        template.innerHTML = streamHtml;
        const stream = Array.from(template.content.querySelectorAll('turbo-stream[target]'))
            .find((candidate) => candidate.getAttribute('target') !== 'flash-messages');
        const targetId = stream?.getAttribute('target');
        if (!targetId) {
            return this.modalTrigger ? { trigger: this.modalTrigger } : null;
        }

        const currentTarget = document.getElementById(targetId);
        return {
            targetId,
            nextTarget: this.findFocusable(currentTarget?.nextElementSibling)
                ?? currentTarget?.nextElementSibling,
            previousTarget: this.findFocusable(currentTarget?.previousElementSibling)
                ?? currentTarget?.previousElementSibling,
            container: currentTarget?.closest(
                'turbo-frame, [data-controller~="grid-view"], table',
            ),
            trigger: this.modalTrigger,
        };
    }

    /** Find the preferred actionable descendant of a potential focus target. */
    findFocusable(root) {
        if (!(root instanceof HTMLElement)) {
            return null;
        }

        const selector = [
            '.edit-btn:not([disabled])',
            '[data-bs-toggle="modal"]:not([disabled])',
            'button:not([disabled])',
            'a[href]',
            'input:not([disabled])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"])',
        ].join(', ');

        return root.matches(selector) ? root : root.querySelector(selector);
    }

    /** Focus the replacement action or the nearest surviving grid context. */
    async restoreFocusAfterStream(focusPlan) {
        if (!focusPlan) {
            return;
        }

        await this.waitForRenderFrame();
        const replacement = focusPlan.targetId
            ? document.getElementById(focusPlan.targetId)
            : null;
        const target = this.findFocusable(replacement)
            ?? replacement
            ?? this.connectedElement(focusPlan.nextTarget)
            ?? this.connectedElement(focusPlan.previousTarget)
            ?? this.connectedElement(focusPlan.trigger)
            ?? this.connectedElement(focusPlan.container);

        if (!(target instanceof HTMLElement)) {
            return;
        }
        if (!this.findFocusable(target) && !target.hasAttribute('tabindex')) {
            target.setAttribute('tabindex', '-1');
        }
        target.focus();
    }

    /** Wait one bounded rendering step for Turbo custom elements to update the DOM. */
    async waitForRenderFrame() {
        await new Promise((resolve) => {
            let settled = false;
            let frameId = null;
            const finish = () => {
                if (settled) {
                    return;
                }
                settled = true;
                window.clearTimeout(timeoutId);
                if (frameId !== null && typeof window.cancelAnimationFrame === 'function') {
                    window.cancelAnimationFrame(frameId);
                }
                resolve();
            };
            const timeoutId = window.setTimeout(finish, 50);

            if (typeof window.requestAnimationFrame === 'function') {
                frameId = window.requestAnimationFrame(finish);
            } else {
                window.setTimeout(finish, 0);
            }
        });
    }

    /** Return an element only while it remains connected to the current document. */
    connectedElement(element) {
        return element instanceof HTMLElement && element.isConnected ? element : null;
    }

    /** Announce successful completion after the modal starts closing. */
    announceSuccess() {
        window.KMP_accessibility?.announce?.(this.successMessage());
    }

    /** Announce a failed submission after any server-provided stream is rendered. */
    announceFailure() {
        window.KMP_accessibility?.announce?.(
            this.failureMessage(),
            { assertive: true },
        );
    }

    /** Return the configured failure copy for visible and announced feedback. */
    failureMessage() {
        return this.errorMessageValue
            || this.element.dataset.turboModalErrorMessageValue
            || 'Unable to save. Please try again.';
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
            return false;
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

        return true;
    }

    /** Remove stale in-modal failure feedback before retrying a submission. */
    clearFailure() {
        const container = this.element.querySelector('[data-turbo-modal-feedback]');
        if (!container) {
            return;
        }

        container.replaceChildren();
        container.classList.add('d-none');
    }

    /** Resolve or create the visible feedback container inside the active modal. */
    failureContainer() {
        let container = this.element.querySelector('[data-turbo-modal-feedback]');
        if (container) {
            return container;
        }

        container = document.createElement('div');
        container.setAttribute('data-turbo-modal-feedback', '');
        container.className = 'mb-3';
        const modalBody = this.findModalElement()?.querySelector('.modal-body');
        (modalBody ?? this.element).prepend(container);

        return container;
    }

    /** Show a visible retryable error while keeping the modal and entered values open. */
    showFallbackFailure() {
        if (!this.element.isConnected) {
            return false;
        }
        const container = this.failureContainer();
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show mb-0';
        alert.setAttribute('role', 'alert');
        alert.append(document.createTextNode(this.failureMessage()));

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.setAttribute('data-bs-dismiss', 'alert');
        closeButton.setAttribute('aria-label', 'Close');
        alert.append(closeButton);

        container.replaceChildren(alert);
        container.classList.remove('d-none');

        return container.isConnected;
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
