import { Controller } from '@hotwired/stimulus';
import { createControllerScope } from '../services/controller-scope-service.js';

/** Load the authorized RSVP form without allowing an obsolete read to replace a draft. */
class AttendanceDialogController extends Controller {
    static targets = ['content'];
    static values = { loading: String, error: String, close: String };

    connect() {
        this.lifecycle = createControllerScope();
        this.lifecycle.listen(this.element, 'show.bs.modal', event => this.load(event));
        this.lifecycle.listen(this.element, 'hide.bs.modal', () => this.cancel());
        this.lifecycle.listen(this.element, 'shown.bs.modal', () => this.focus());
    }

    disconnect() {
        this.cancel();
        this.lifecycle.dispose();
    }

    cancel() {
        this.request?.abort();
        this.request = null;
    }

    async load(event) {
        this.cancel();
        const attendance = event.relatedTarget?.dataset.attendanceId;
        const gathering = event.relatedTarget?.dataset.gatheringId;
        if (!attendance || !gathering) return;
        const request = new AbortController();
        this.request = request;
        this.message(this.loadingValue, 'status');
        this.contentTarget.setAttribute('aria-busy', 'true');
        try {
            const url = `/gatherings/attendance-modal/${encodeURIComponent(gathering)}?attendance_id=${encodeURIComponent(attendance)}`;
            const response = await this.lifecycle.fetch(url, { signal: request.signal, headers: {
                'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html',
            } });
            if (!response.ok || response.redirected) throw new Error('Attendance form unavailable');
            const html = await response.text();
            if (this.request !== request || request.signal.aborted) return;
            this.contentTarget.innerHTML = html;
            const title = this.contentTarget.querySelector('.modal-title');
            if (title) {
                title.id = `${this.element.id}Label`;
                this.element.setAttribute('aria-labelledby', title.id);
            }
            this.focus();
        } catch (error) {
            if (this.request === request && !request.signal.aborted && !this.lifecycle.signal.aborted) {
                this.message(this.errorValue, 'alert');
            }
        } finally {
            if (this.request === request) this.contentTarget.removeAttribute('aria-busy');
        }
    }

    focus() {
        if (!this.element.classList.contains('show')) return;
        this.contentTarget.querySelector('textarea, select, input:not([type="hidden"]), .btn-close')?.focus();
    }

    message(text, role) {
        const titleId = `${this.element.id}Label`;
        this.contentTarget.innerHTML = `<div class="modal-header"><h5 class="modal-title" id="${titleId}"></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p role="${role}"></p></div>`;
        this.contentTarget.querySelector('.modal-title').textContent = text;
        this.contentTarget.querySelector('[role]').textContent = text;
        this.contentTarget.querySelector('.btn-close').setAttribute('aria-label', this.closeValue);
        this.element.setAttribute('aria-labelledby', titleId);
    }
}
window.Controllers ??= {};
window.Controllers['attendance-dialog'] = AttendanceDialogController;
export default AttendanceDialogController;
