import '../services/turbo-fetch-bootstrap-service.js';
import { Controller } from "@hotwired/stimulus";
import * as Turbo from "@hotwired/turbo";
import { driveEnabled } from '../services/ui-navigation-service.js';
import { refreshAfterChange, showRefreshFailure } from '../services/page-refresh-service.js';

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
        changed: String,
        modalId: String,
        returnFocusId: String,
        returnFocusHref: String,
        returnFocusFrame: String,
    }

    initialize() {
        this.modalTrigger = null;
        this.captureModalTriggerListener = this.captureModalTrigger.bind(this);
        this.modalHideCleanup = null;
        this.gridRefreshCleanup = null;
    }

    /**
     * Initialize - log when controller connects
     */
    connect() {
        this.boundTurboResponse = this.handleTurboResponse.bind(this);
        this.boundNativeStart = () => { this.submitting = true; };
        this.boundNativeEnd = event => {
            if (!this.handlingNativeResponse) this.submitting = false;
            if (event.detail?.error && !this.showFallbackFailure()) this.announceFailure();
        };
        this.boundBeforeRequest = event => {
            if (event.target !== this.element) return;
            const headers = event.detail.fetchOptions.headers;
            if (headers instanceof Headers) headers.set('X-KMP-Grid-Reconcile', 'table');
            else headers['X-KMP-Grid-Reconcile'] = 'table';
        };
        this.element.addEventListener('turbo:before-fetch-request', this.boundBeforeRequest);
        this.element.addEventListener('turbo:before-fetch-response', this.boundTurboResponse);
        this.element.addEventListener('turbo:submit-start', this.boundNativeStart);
        this.element.addEventListener('turbo:submit-end', this.boundNativeEnd);
        this.findModalElement()?.addEventListener(
            'show.bs.modal',
            this.captureModalTriggerListener,
        );
    }

    disconnect() {
        this.element.removeEventListener('turbo:before-fetch-request', this.boundBeforeRequest);
        this.element.removeEventListener('turbo:before-fetch-response', this.boundTurboResponse);
        this.element.removeEventListener('turbo:submit-start', this.boundNativeStart);
        this.element.removeEventListener('turbo:submit-end', this.boundNativeEnd);
        this.findModalElement()?.removeEventListener(
            'show.bs.modal',
            this.captureModalTriggerListener,
        );
        this.modalHideCleanup?.();
        this.modalHideCleanup = null;
        this.gridRefreshCleanup?.();
        this.gridRefreshCleanup = null;
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
        // Keep the dialog and draft visible until the server confirms success.
        this.syncPageContext();
    }

    /**
     * Submit a modal form as a Turbo Stream request.
     *
     * Turbo owns compatible submissions in Drive mode; fallback uses one explicit
     * stream request while retaining the same response/focus coordination.
     *
     * @param {SubmitEvent} event - The submit event
     */
    async submitAsTurboStream(event) {
        if (!(this.element instanceof HTMLFormElement)) {
            return;
        }

        if (driveEnabled()) {
            if (this.submitting) { event.preventDefault(); event.stopImmediatePropagation(); return; }
            // Legacy templates opted out to use this controller's manual fetch.
            // In Drive mode the same controller hands the submission to Turbo.
            this.element.dataset.turbo = 'true';
            this.syncPageContext();
            this.clearFailure();
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        if (this.submitting) {
            return;
        }
        this.submitting = true;
        this.syncPageContext();
        this.clearFailure();
        // Capture the clicked action before disabling submit controls.
        const formData = new FormData(this.element);
        const submitter = event.submitter;
        if (submitter?.name && !submitter.disabled) {
            formData.append(submitter.name, submitter.value);
        }
        this.setSubmitting(true);
        const changeContext = this.element.closest('[data-change-context]');

        try {
            const csrfToken = this.element.querySelector('input[name="_csrfToken"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content;
            const headers = {
                'Accept': 'text/vnd.turbo-stream.html',
                'X-Requested-With': 'XMLHttpRequest',
                'X-KMP-Grid-Reconcile': 'table',
            };
            if (csrfToken) {
                headers['X-CSRF-Token'] = csrfToken;
            }

            const response = await fetch(this.element.action, {
                method: (this.element.method || 'POST').toUpperCase(),
                body: formData,
                headers,
                credentials: 'same-origin',
            });
            await this.processResponse(response, changeContext);
        } catch (error) {
            console.error('Unable to submit modal form:', error);
            if (!this.showFallbackFailure()) {
                this.announceFailure();
            }
        } finally {
            this.submitting = false;
            this.setSubmitting(false);
        }
    }

    /** Turbo owns submission; this coordinator owns dialog and response ordering. */
    async handleTurboResponse(event) {
        if (!driveEnabled() || event.target !== this.element) return;
        const response = event.detail.fetchResponse.response;
        const stream = (response.headers.get('Content-Type') || '').includes('text/vnd.turbo-stream.html');
        if (!stream && (response.ok || response.status === 422)) return;
        event.preventDefault();
        // The document StreamObserver must not render the same response before
        // Bootstrap has closed the dialog and grid reconciliation is prepared.
        event.stopImmediatePropagation();
        this.handlingNativeResponse = true;
        try {
            await this.processResponse(response.clone(), this.element.closest('[data-change-context]'));
        } catch {
            if (!this.showFallbackFailure()) this.announceFailure();
        } finally {
            this.handlingNativeResponse = false;
            this.submitting = false;
        }
    }

    async processResponse(response, changeContext) {
        const body = await response.text();
        const contentType = response.headers.get('Content-Type') || '';

        if (contentType.includes('text/vnd.turbo-stream.html') || body.includes('<turbo-stream')) {
            const focusPlan = response.ok ? this.createStreamFocusPlan(body) : null;
            const streamIncludesLiveFeedback = this.streamIncludesLiveFeedback(body);
            const refresh = response.ok
                ? this.prepareGridRefreshStreams(body)
                : { html: body, frameIds: [] };
            let streamRendered = true;
            if (response.ok) {
                await this.closeModalAndWait();
            }
            const refreshed = this.waitForGridRefresh(refresh.frameIds);
            try {
                this.renderTurboStream(refresh.html);
            } catch (renderError) {
                if (!response.ok) {
                    throw renderError;
                }
                console.error('Unable to render successful modal response:', renderError);
                streamRendered = false;
                this.gridRefreshCleanup?.();
                if (!this.showFallbackSuccess()) {
                    this.announceSuccess();
                }
            }
            if (response.ok) {
                await refreshed;
                if (this.changedValue) {
                    const refreshDetails = () => refreshAfterChange(this.changedValue, {
                        source: this.element, context: changeContext,
                    });
                    try { await refreshDetails(); }
                    catch { showRefreshFailure(refreshDetails); }
                }
                await this.restoreFocusAfterStream(focusPlan);
                this.dispatch('saved');
                if (streamRendered && !streamIncludesLiveFeedback) {
                    this.announceSuccess();
                }
            } else {
                // Page-level flash alerts can sit behind the still-open dialog.
                const message = this.streamFailureMessage(body);
                if ((message || !streamIncludesLiveFeedback) && !this.showFallbackFailure(message)) {
                    this.announceFailure();
                }
            }
            return;
        }

        if (response.redirected && response.ok) {
            window.location.assign(response.url);
            return;
        }

        const frame = this.element.closest('turbo-frame');
        if (frame && body !== '' && (response.ok || response.status === 422)) {
            frame.innerHTML = body;
        }
        if (!response.ok) {
            if (!this.showFallbackFailure()) {
                this.announceFailure();
            }
        }
    }

    /** Sync hidden page context to the visible browser URL before posting. */
    syncPageContext() {
        const gridSelector = '[data-controller~="grid-view"]';
        const sourceFrame = this.modalTrigger?.closest('turbo-frame') ?? this.element.closest('turbo-frame');
        const frameGrids = sourceFrame?.querySelectorAll(gridSelector);
        const source = this.modalTrigger?.closest(gridSelector)
            ?? this.modalTrigger?.closest('[role="tabpanel"]')?.querySelector(gridSelector)
            ?? this.element.closest(gridSelector)
            ?? this.element.closest('[role="tabpanel"]')?.querySelector(gridSelector)
            ?? (frameGrids?.length === 1 ? frameGrids[0] : null);
        const frame = source?.querySelector('turbo-frame[id$="-table"]');
        const src = this.gridFrameSource(frame);
        const url = new URL(window.location.href);
        if (src) {
            const gridUrl = new URL(src, window.location.origin);
            if (gridUrl.origin === window.location.origin) {
                const tab = url.searchParams.get('tab');
                url.search = gridUrl.search;
                url.searchParams.delete('tab');
                ['frame_id', 'member_id', 'branch_id', 'gathering_id'].forEach(key => url.searchParams.delete(key));
                if (tab) {
                    url.searchParams.set('tab', tab);
                }
                if (source.dataset.gridViewSyncUrlValue === 'false' || source.closest('[role="tabpanel"]')) {
                    url.searchParams.set('grid_context', frame.id.slice(0, -'-table'.length));
                }
            }
        }
        this.element.querySelectorAll(
            'input[name="page_context_url"]',
        ).forEach((input) => {
            if (input.dataset.pageContextStatic !== 'true') {
                input.value = url.pathname + url.search;
            }
        });
    }

    /** Return the most recently loaded grid URL, including a clamped page. */
    gridFrameSource(frame) {
        return frame?.dataset.gridCurrentSrc
            || frame?.getAttribute('src')
            || frame?.dataset.gridSrc
            || null;
    }

    /** Refresh affected grid pages once so row order, membership, and counts stay current. */
    prepareGridRefreshStreams(streamHtml) {
        const fragment = document.createElement('template');
        fragment.innerHTML = streamHtml;
        const frameIds = new Set();

        fragment.content.querySelectorAll('turbo-stream[target]').forEach((stream) => {
            const action = stream.getAttribute('action');
            if (action !== 'replace' && action !== 'remove' && action !== 'update') {
                return;
            }
            const target = document.getElementById(stream.getAttribute('target'));
            const frame = target?.matches('turbo-frame[id$="-table"]')
                ? target
                : target?.matches('tr') ? target.closest('turbo-frame[id$="-table"]') : null;
            if (!frame?.closest('[data-controller~="grid-view"]')) {
                return;
            }
            const src = this.gridFrameSource(frame);
            if (!src) {
                return;
            }
            const url = new URL(src, window.location.origin);
            if (url.origin !== window.location.origin) {
                return;
            }
            if (frameIds.has(frame.id)) {
                stream.remove();
                return;
            }
            frameIds.add(frame.id);
            stream.setAttribute('action', 'replace');
            stream.setAttribute('target', frame.id);
            const template = document.createElement('template');
            const replacement = document.createElement('turbo-frame');
            replacement.id = frame.id;
            replacement.setAttribute('src', url.pathname + url.search);
            const loading = document.createElement('div');
            loading.className = 'text-center p-3';
            loading.setAttribute('role', 'status');
            loading.textContent = 'Loading grid...';
            replacement.append(loading);
            template.content.append(replacement);
            stream.replaceChildren(template);
        });

        return { html: frameIds.size ? fragment.innerHTML : streamHtml, frameIds: [...frameIds] };
    }

    /** Wait for refreshed tables before restoring focus; errors and disconnects release the wait. */
    waitForGridRefresh(frameIds) {
        if (!frameIds.length) {
            return Promise.resolve();
        }
        this.gridRefreshCleanup?.();
        return new Promise((resolve) => {
            const pending = new Set(frameIds);
            const cleanup = () => {
                document.removeEventListener('turbo:frame-load', loaded);
                document.removeEventListener('turbo:frame-missing', loaded);
                document.removeEventListener('turbo:fetch-request-error', loaded);
                window.clearTimeout(timeoutId);
                if (this.gridRefreshCleanup === cleanup) {
                    this.gridRefreshCleanup = null;
                }
                resolve();
            };
            const loaded = (event) => {
                pending.delete(event.target.id);
                if (!pending.size) {
                    cleanup();
                }
            };
            document.addEventListener('turbo:frame-load', loaded);
            document.addEventListener('turbo:frame-missing', loaded);
            document.addEventListener('turbo:fetch-request-error', loaded);
            const timeoutId = window.setTimeout(cleanup, 5000);
            this.gridRefreshCleanup = cleanup;
        });
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

    /** Read server validation feedback as plain text for the open dialog. */
    streamFailureMessage(streamHtml) {
        const fragment = document.createElement('template');
        fragment.innerHTML = streamHtml;
        const flash = Array.from(fragment.content.querySelectorAll('turbo-stream'))
            .find(stream => stream.getAttribute('target') === 'flash-messages');
        const content = flash?.querySelector('template')?.content;
        return Array.from(content?.querySelectorAll('.alert-danger, .alert-warning') ?? [])
            .map(alert => alert.textContent.trim()).filter(Boolean).join(' ');
    }

    /**
     * Resolve the Bootstrap modal for this form.
     *
     * Officers (and similar) wrap the modal markup inside the form; others nest the
     * form inside the modal. Support both DOM shapes.
     */
    findModalElement() {
        if (this.modalIdValue) {
            return document.getElementById(this.modalIdValue);
        }
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
            return this.modalTrigger || this.returnFocusIdValue || this.returnFocusHrefValue || this.returnFocusFrameValue ? {
                trigger: this.modalTrigger,
                triggerId: this.modalTrigger?.id || this.returnFocusIdValue,
                triggerHref: this.modalTrigger?.getAttribute('href') || this.returnFocusHrefValue,
                gatheringId: this.modalTrigger?.dataset.gatheringId,
                containerId: this.returnFocusFrameValue,
            } : null;
        }

        const currentTarget = document.getElementById(targetId);
        return {
            targetId,
            nextTargetId: currentTarget?.nextElementSibling?.id,
            previousTargetId: currentTarget?.previousElementSibling?.id,
            containerId: currentTarget?.closest(
                'turbo-frame, [data-controller~="grid-view"], table',
            )?.id,
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
            ?? document.getElementById(focusPlan.nextTargetId)
            ?? document.getElementById(focusPlan.previousTargetId)
            ?? this.connectedElement(focusPlan.nextTarget)
            ?? this.connectedElement(focusPlan.previousTarget)
            ?? this.connectedElement(focusPlan.trigger)
            ?? document.getElementById(focusPlan.triggerId)
            ?? (focusPlan.triggerHref ? [...document.querySelectorAll('a[href]')]
                .find(element => element.getAttribute('href') === focusPlan.triggerHref && !element.closest('.modal')) : null)
            ?? (focusPlan.gatheringId ? [...document.querySelectorAll('[data-gathering-id]')]
                .find(element => element.dataset.gatheringId === focusPlan.gatheringId && !element.closest('.modal')) : null)
            ?? document.getElementById(focusPlan.containerId)
            ?? this.connectedElement(focusPlan.container);

        if (!(target instanceof HTMLElement)) {
            return;
        }
        const focusTarget = this.findFocusable(target) ?? target;
        if (focusTarget === target && !target.hasAttribute('tabindex')) {
            target.setAttribute('tabindex', '-1');
        }
        focusTarget.focus({ preventScroll: true });
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
        const container = this.findModalElement()
            ?.querySelector('[data-turbo-modal-feedback]')
            ?? this.element.querySelector('[data-turbo-modal-feedback]');
        if (!container) {
            return;
        }

        container.replaceChildren();
        container.classList.add('d-none');
    }

    /** Resolve or create the visible feedback container inside the active modal. */
    failureContainer() {
        let container = this.findModalElement()
            ?.querySelector('[data-turbo-modal-feedback]')
            ?? this.element.querySelector('[data-turbo-modal-feedback]');
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
    showFallbackFailure(message = null) {
        if (!this.element.isConnected) {
            return false;
        }
        const container = this.failureContainer();
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show mb-0';
        alert.setAttribute('role', 'alert');
        alert.append(document.createTextNode(message || this.failureMessage()));

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
