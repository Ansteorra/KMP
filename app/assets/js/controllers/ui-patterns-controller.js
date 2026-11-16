import { updateUiHistory } from '../services/ui-navigation-service.js';
import { Controller } from '@hotwired/stimulus';

/** Local interactions for the review showcase; never submits or uploads example data. */
class UiPatternsController extends Controller {
    static targets = [
        'feedback', 'step', 'stepButton',
        'stepHeading', 'previousButton', 'nextButton', 'submitButton', 'file',
        'fileName', 'waiverType', 'reviewType', 'reviewFile', 'agendaItem',
        'agendaTitle', 'agendaList', 'agendaEmpty', 'court', 'courtName', 'permissions',
        'dashboardScope', 'dashboardItem', 'dashboardMetric',
        'taskComplete', 'completionHeading',
    ];

    /** Restore local workflow state without changing the review scenario. */
    connect() {
        this.initialDisabled = new Map();
        this.stepTargets.forEach(panel => panel.querySelectorAll('input, select, textarea')
            .forEach(field => this.initialDisabled.set(field, field.disabled)));
        this.currentStep = 1;
        this.furthestStep = 1;
        this.sampleFileName = '';
        this.nextAgendaId = 1;
        this.sectionListener = this.sectionChanged.bind(this);
        this.element.addEventListener('shown.bs.tab', this.sectionListener);
        this.sectionFocusListener = this.revealSectionTab.bind(this);
        this.element.addEventListener('focusin', this.sectionFocusListener);
        if (typeof window.ResizeObserver === 'function') {
            this.feedbackObserver = new window.ResizeObserver(() => this.updateFeedbackOffset());
            this.feedbackTargets.forEach(node => this.feedbackObserver.observe(node));
        }
        if (this.stepTargets.length) this.showStep(1, false);
        if (this.agendaListTargets.length) this.updateCourt(false);
        if (this.dashboardScopeTargets.length) {
            this.restoreDashboardScope();
            this.updateDashboard(false);
        }
        const section = new URL(window.location.href).searchParams.get('section');
        const tab = [...this.element.querySelectorAll('[data-bs-toggle="tab"]')].find(node => node.id === section);
        if (tab) {
            window.bootstrap?.Tab?.getOrCreateInstance(tab)?.show();
            this.revealSectionTab({ target: tab });
        }
    }

    /** Discard only local preview state when Turbo removes the showcase. */
    disconnect() {
        this.element.removeEventListener('shown.bs.tab', this.sectionListener);
        this.element.removeEventListener('focusin', this.sectionFocusListener);
        this.feedbackObserver?.disconnect();
        this.element.style.removeProperty('--ui-feedback-height');
        this.initialDisabled?.forEach((disabled, field) => { field.disabled = disabled; });
        this.initialDisabled?.clear();
    }

    /** Show a simulated command result in the shared status region. */
    notice(event) {
        event?.preventDefault();
        this.report(event?.currentTarget?.dataset.message || 'Updated in this preview.');
    }

    /** Validate and update an example locally, then let Bootstrap return dialog focus. */
    savePreview(event) {
        event.preventDefault();
        const form = event.currentTarget.closest('form');
        if (!form || !form.reportValidity()) return;
        if (this.stepTargets.length && !this.validateStep(this.currentStep)) return;
        const selector = form.dataset.updateTarget;
        if (selector && form.dataset.updateField) {
            const value = new FormData(form).get(form.dataset.updateField);
            this.element.querySelectorAll(selector).forEach(node => { node.textContent = String(value ?? ''); });
        }
        this.closeModal(form);
        this.report(form.dataset.message || 'Changes saved in this preview.');
        if (form.dataset.previewTask === 'waiver' && this.taskCompleteTargets.length) {
            form.hidden = true;
            this.taskCompleteTargets.forEach(panel => { panel.hidden = false; });
            this.element.querySelectorAll('[data-task-error]').forEach(panel => { panel.hidden = true; });
            this.completionHeadingTargets[0]?.focus();
        }
    }

    /** A record section owns only its own query key, not embedded collection state. */
    sectionChanged(event) {
        const tab = event.target;
        if (!tab.matches('[data-bs-toggle="tab"]') || !tab.id) return;
        this.revealSectionTab(event);
        const url = new URL(window.location.href);
        url.searchParams.set('section', tab.id);
        updateUiHistory(`${url.pathname}${url.search}${url.hash}`, { replace: true });
    }

    /** Reveal a section tab horizontally without moving the page or its surrounding panels. */
    revealSectionTab(event) {
        const tab = event.target.closest('[data-bs-toggle="tab"]');
        const list = tab?.closest('[role="tablist"]');
        if (!list || !this.element.contains(list) || list.scrollWidth <= list.clientWidth) return;
        const listBounds = list.getBoundingClientRect();
        const tabBounds = tab.getBoundingClientRect();
        const left = listBounds.left + list.clientLeft;
        const right = left + list.clientWidth;
        if (tabBounds.left < left) list.scrollLeft += tabBounds.left - left;
        else if (tabBounds.right > right) list.scrollLeft += tabBounds.right - right;
    }

    /** Move forward only after the current step is complete. */
    nextStep(event) {
        event.preventDefault();
        if (this.validateStep(this.currentStep)) this.showStep(this.currentStep + 1);
    }

    /** Going back preserves entered data. */
    previousStep(event) {
        event.preventDefault();
        this.showStep(this.currentStep - 1);
    }

    /** Direct step navigation checks every intervening required step. */
    goToStep(event) {
        event.preventDefault();
        const requested = Number(event.currentTarget.dataset.step);
        if (!Number.isInteger(requested)) return;
        for (let step = this.currentStep; step < requested; step += 1) {
            this.showStep(step, false);
            if (!this.validateStep(step)) return;
        }
        this.showStep(requested);
    }

    /** Read only a local filename; the selected file never leaves the browser. */
    selectFile() {
        this.sampleFileName = '';
        this.fileTargets.forEach(input => input.setCustomValidity(''));
        this.updateFileLabels();
    }

    /** Allow a complete upload review without requiring a personal document. */
    useSampleFile(event) {
        event.preventDefault();
        this.sampleFileName = 'gate-waivers-sample.pdf';
        this.fileTargets.forEach(input => { input.value = ''; input.setCustomValidity(''); });
        this.updateFileLabels();
        this.report('Sample waiver selected. No document will be uploaded.');
    }

    /** Move an agenda item using the same controls on desktop and phone. */
    moveUp(event) {
        this.moveAgenda(event, -1);
    }

    /** Move an agenda item down without requiring dragging. */
    moveDown(event) {
        this.moveAgenda(event, 1);
    }

    /** Switch the visible working court without dropping another court’s changes. */
    selectCourt() {
        this.updateCourt(true);
    }

    /** Apply the example branch scope to dashboard exceptions and their totals. */
    filterDashboard() {
        this.updateDashboard(true);
    }

    /** Add one predefined example entry to the active agenda using text-only content. */
    addAgendaItem(event) {
        event.preventDefault();
        const trigger = event.currentTarget;
        const list = this.agendaListTargets.find(node => !node.hidden);
        if (!list) return;
        const id = trigger.dataset.itemId;
        if (id && this.agendaItemTargets.some(item => item.dataset.itemId === id)) {
            this.report(`${trigger.dataset.title} is already on a court agenda.`);
            return;
        }
        const item = document.createElement('li');
        item.className = 'ui-agenda-item';
        item.dataset.uiPatternsTarget = 'agendaItem';
        item.dataset.itemId = id || `preview-${this.nextAgendaId++}`;
        const content = document.createElement('div');
        content.className = 'ui-agenda-item-content ui-agenda-item-heading';
        const title = document.createElement('h3');
        title.className = 'h6 mb-1';
        title.dataset.uiPatternsTarget = 'agendaTitle';
        title.textContent = trigger.dataset.title || 'New agenda entry';
        const detail = document.createElement('p');
        detail.className = 'text-muted small mb-0';
        detail.textContent = trigger.dataset.detail || 'Ready for court';
        content.append(title, detail);
        const actions = document.createElement('div');
        actions.className = 'ui-agenda-move ui-agenda-item-actions';
        for (const [label, action, icon] of [['up', 'moveUp', 'arrow-up'], ['down', 'moveDown', 'arrow-down']]) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary';
            button.dataset.action = `ui-patterns#${action}`;
            button.setAttribute('aria-label', `Move ${title.textContent} ${label}`);
            const glyph = document.createElement('kmp-icon');
            glyph.setAttribute('name', icon);
            glyph.setAttribute('aria-hidden', 'true');
            button.append(glyph);
            actions.append(button);
        }
        item.append(content, actions);
        list.append(item);
        this.updateAgendaButtons(list);
        this.agendaEmptyTargets.forEach(node => { node.hidden = true; });
        if (id) {
            trigger.setAttribute('aria-disabled', 'true');
            trigger.setAttribute('aria-label', `${title.textContent} is on a court agenda`);
            trigger.textContent = 'Added';
        }
        this.report(`${title.textContent} added to this court in the preview.`);
    }

    /** Demonstrate the existing permission lookup-and-add relationship pattern. */
    addPermission(event) {
        event.preventDefault();
        const form = event.currentTarget.closest('form');
        if (!form || !form.reportValidity() || !this.permissionsTargets.length) return;
        const values = new FormData(form);
        const row = document.createElement('tr');
        const permission = document.createElement('th');
        permission.scope = 'row';
        permission.textContent = String(values.get('permission') || 'Permission');
        const scope = document.createElement('td');
        scope.textContent = form.querySelector('[name="permission"]')?.selectedOptions[0]?.dataset.scope || 'Assigned branch';
        row.append(permission, scope);
        this.permissionsTargets[0].append(row);
        this.closeModal(form);
        this.report(`${permission.textContent} added for ${scope.textContent} in this preview.`);
    }

    /** Restore only a recognized dashboard branch scope from a shared link. */
    restoreDashboardScope() {
        const value = new URL(window.location.href).searchParams.get('branch');
        this.dashboardScopeTargets.forEach(field => {
            if (value && [...field.options].some(option => option.value === value)) field.value = value;
        });
    }

    /** Make a task step active and keep hidden required fields out of native submission. */
    showStep(step, focus = true) {
        const panel = this.stepTargets.find(node => Number(node.dataset.step) === step);
        if (!panel) return;
        this.currentStep = step;
        this.furthestStep = Math.max(this.furthestStep, step);
        this.stepTargets.forEach(node => {
            node.hidden = node !== panel;
            node.querySelectorAll('input, select, textarea').forEach(field => {
                field.disabled = node !== panel || this.initialDisabled.get(field) === true;
            });
        });
        this.stepButtonTargets.forEach(button => {
            const current = Number(button.dataset.step) === step;
            button.disabled = Number(button.dataset.step) > this.furthestStep;
            if (current) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
            button.classList.toggle('active', current);
        });
        this.previousButtonTargets.forEach(button => { button.hidden = step === 1; });
        this.nextButtonTargets.forEach(button => { button.hidden = step === this.stepTargets.length; });
        this.submitButtonTargets.forEach(button => { button.hidden = step !== this.stepTargets.length; });
        this.reviewTypeTargets.forEach(node => {
            const field = this.waiverTypeTargets[0];
            node.textContent = field?.selectedOptions[0]?.textContent || 'Choose a waiver type';
        });
        this.updateFileLabels();
        if (focus) {
            panel.querySelector('[data-ui-patterns-target~="stepHeading"]')?.focus();
            this.report(`Step ${step} of ${this.stepTargets.length}.`);
        }
    }

    /** Check visible task fields and require a file or the supplied example document. */
    validateStep(step) {
        const panel = this.stepTargets.find(node => Number(node.dataset.step) === step);
        if (!panel) return false;
        for (const field of panel.querySelectorAll('input, select, textarea')) {
            if (!field.disabled && !field.checkValidity()) {
                field.reportValidity();
                field.focus();
                return false;
            }
        }
        if (step === 2 && this.fileTargets.length && !this.selectedFileName()) {
            const input = this.fileTargets[0];
            input.setCustomValidity('Select a waiver file or use the sample document.');
            input.reportValidity();
            input.focus();
            this.report('Select a waiver file or use the sample document before continuing.');
            return false;
        }
        return true;
    }

    /** Return a filename only; do not read or transmit file contents. */
    selectedFileName() {
        return this.sampleFileName || this.fileTargets[0]?.files?.[0]?.name || '';
    }

    /** Keep the selection and review summaries in sync. */
    updateFileLabels() {
        const name = this.selectedFileName() || 'No file selected';
        [...this.fileNameTargets, ...this.reviewFileTargets].forEach(node => { node.textContent = name; });
    }

    /** Preserve the chosen court’s list and hide inactive lists completely. */
    updateCourt(announce) {
        const select = this.courtTargets[0];
        const court = select?.value || this.agendaListTargets[0]?.dataset.court;
        this.agendaListTargets.forEach(list => {
            list.hidden = list.dataset.court !== court;
            this.updateAgendaButtons(list);
        });
        const current = this.agendaListTargets.find(list => !list.hidden);
        this.agendaEmptyTargets.forEach(node => {
            node.hidden = Boolean(current?.querySelector('[data-ui-patterns-target~="agendaItem"]'));
        });
        const name = select?.selectedOptions[0]?.textContent || 'Court';
        this.courtNameTargets.forEach(node => { node.textContent = name; });
        if (announce) this.report(`${name} agenda shown.`);
    }

    /** Dashboard counts are predefined sample counts, never inferred tenant statistics. */
    updateDashboard(announce) {
        const scope = this.dashboardScopeTargets[0]?.value || 'all';
        this.dashboardItemTargets.forEach(item => { item.hidden = scope !== 'all' && item.dataset.branch !== scope; });
        const countKey = `count${scope.charAt(0).toUpperCase()}${scope.slice(1)}`;
        this.dashboardMetricTargets.forEach(metric => { metric.textContent = metric.dataset[countKey] || '0'; });
        if (announce) {
            const url = new URL(window.location.href);
            if (scope === 'all') url.searchParams.delete('branch');
            else url.searchParams.set('branch', scope);
            updateUiHistory(`${url.pathname}${url.search}${url.hash}`, { replace: true });
            this.report('Dashboard scope updated.');
        }
    }

    /** Keep focus on the moved control and explain boundary moves without disabling focus. */
    moveAgenda(event, direction) {
        event.preventDefault();
        const button = event.currentTarget;
        const item = button.closest('[data-ui-patterns-target~="agendaItem"]');
        if (!item) return;
        const list = item.parentElement;
        const items = [...list.children].filter(node => node.matches('[data-ui-patterns-target~="agendaItem"]'));
        const index = items.indexOf(item);
        const sibling = items[index + direction];
        if (!sibling) return;
        if (direction < 0) list.insertBefore(item, sibling);
        else list.insertBefore(sibling, item);
        this.updateAgendaButtons(list);
        button.focus();
        const title = item.querySelector('[data-ui-patterns-target~="agendaTitle"]')?.textContent.trim() || 'Entry';
        this.report(`${title} moved to position ${index + direction + 1} of ${items.length}.`);
    }

    /** Mark unavailable moves while retaining a stable keyboard focus position. */
    updateAgendaButtons(list) {
        const items = [...list.querySelectorAll('[data-ui-patterns-target~="agendaItem"]')];
        items.forEach((item, index) => {
            item.querySelector('[data-action*="moveUp"]')?.setAttribute('aria-disabled', String(index === 0));
            item.querySelector('[data-action*="moveDown"]')?.setAttribute('aria-disabled', String(index === items.length - 1));
        });
    }

    /** Bootstrap owns modal focus restoration. */
    closeModal(form) {
        const modal = form.closest('.modal');
        if (modal) window.bootstrap?.Modal?.getInstance(modal)?.hide();
    }

    /** Prefer the visible shared live region, with the app announcer as a fallback. */
    report(message) {
        if (this.feedbackTargets.length) {
            this.feedbackTargets.forEach(node => { node.textContent = message; node.hidden = false; });
            this.updateFeedbackOffset();
        } else {
            window.KMP_accessibility?.announce(message);
        }
    }

    /** Keep anchor destinations and keyboard focus below the sticky feedback and header. */
    updateFeedbackOffset() {
        const height = Math.max(0, ...this.feedbackTargets.map(node => node.getBoundingClientRect().height));
        this.element.style.setProperty('--ui-feedback-height', `${height}px`);
    }
}

window.Controllers ||= {};
window.Controllers['ui-patterns'] = UiPatternsController;
