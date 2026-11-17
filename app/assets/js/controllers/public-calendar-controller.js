import { Controller } from '@hotwired/stimulus';

/** Enhances native public calendar disclosures and bounded activity filtering. */
class PublicCalendarController extends Controller {
    static targets = ['activityOption', 'activityGroup', 'groupCount', 'activitySearch', 'activityStatus', 'activityCount', 'filterBar',
        'selectedSummary', 'selectedLabels', 'selectionStatus', 'filterDisclosure', 'eventDisclosure'];
    static values = { pendingMessage: String };

    connect() {
        this.appliedSelection = this.selectionKey(this.selectedOptions());
        this.updateCount();
        if (this.hasFilterDisclosureTarget) {
            this.filterMedia = window.matchMedia('(max-width: 700px), (max-height: 500px)');
            this.filterLayoutListener = () => this.layoutFilters();
            this.filterMedia.addEventListener('change', this.filterLayoutListener);
            this.layoutFilters();
        }
        this.restoreEvent();
        this.restoreAfterPageShow = () => {
            this.restoreFrame = window.requestAnimationFrame(() => {
                // Native fragment focus can clear our focus after connect, especially from
                // a cached passkey return. Do not override a control the visitor has selected.
                if (document.activeElement === document.body) this.restoreEvent();
            });
        };
        window.addEventListener('pageshow', this.restoreAfterPageShow);
    }

    disconnect() {
        this.filterMedia?.removeEventListener('change', this.filterLayoutListener);
        window.removeEventListener('pageshow', this.restoreAfterPageShow);
        window.cancelAnimationFrame(this.restoreFrame);
    }

    /** Restore the calendar row selected before login, including repeated event rows. */
    restoreEvent() {
        const details = this.eventDisclosureTargets.find(item => `#${item.dataset.panel}` === window.location.hash);
        if (!details) return;
        details.open = true;
        this.sync({ currentTarget: details });
        const panel = document.getElementById(details.dataset.panel);
        const row = details.closest('tr');
        if (!panel || !row) return;
        const filterHeight = this.hasFilterBarTarget ? this.filterBarTarget.getBoundingClientRect().height : 0;
        const filterTop = this.hasFilterBarTarget
            ? Math.max(0, parseFloat(window.getComputedStyle(this.filterBarTarget).top) || 0) : 0;
        const summary = details.querySelector('summary');
        if (!summary) return;
        const inset = filterTop + filterHeight + 12;
        const summaryRect = summary.getBoundingClientRect();
        const rowRect = row.getBoundingClientRect();
        const summaryInset = inset + (rowRect.height <= window.innerHeight - inset
            ? Math.max(0, summaryRect.top - rowRect.top) : 0);
        summary.style.scrollMarginTop = `${summaryInset}px`;
        // Native fragment scrolling can follow connect. Align it with the focused summary,
        // including tall mobile rows where the summary sits far below the row's top.
        const panelRect = panel.getBoundingClientRect();
        panel.style.scrollMarginTop = `${summaryInset + panelRect.top - summaryRect.top}px`;
        panel.style.scrollMarginLeft = `${panelRect.left - summaryRect.left}px`;
        panel.style.scrollMarginRight = `${summaryRect.right - panelRect.right}px`;
        summary.focus({ preventScroll: true });
        summary.scrollIntoView({ block: 'start', inline: 'nearest', behavior: 'instant' });
    }

    layoutFilters() {
        const disclosure = this.filterDisclosureTarget;
        const focusInside = disclosure.contains(document.activeElement)
            && document.activeElement !== disclosure.querySelector('summary');
        if (!this.filterMedia.matches || !focusInside) {
            disclosure.open = !this.filterMedia.matches;
        }
    }

    revealFocus(event) {
        if (!this.hasFilterBarTarget || this.filterBarTarget.contains(event.target)) return;
        if (!event.target.matches(':focus-visible')) return;
        const header = this.filterBarTarget.getBoundingClientRect();
        const focused = event.target.getBoundingClientRect();
        if (header.top <= 0 && focused.top < header.bottom) {
            window.scrollBy({ top: focused.top - header.bottom - 12, behavior: 'instant' });
        }
    }

    toggleRow(event) {
        if (event.target.closest('a, button, input, select, textarea, summary') || window.getSelection()?.toString()) return;
        const details = event.currentTarget.querySelector('details');
        if (details) details.open = !details.open;
    }

    expand(event) {
        event.stopPropagation();
        const details = event.currentTarget.closest('tr').querySelector('details');
        if (!details) return;
        details.open = true;
        details.querySelector('summary').focus();
    }

    sync(event) {
        const details = event.currentTarget;
        const panel = document.getElementById(details.dataset.panel);
        if (panel) panel.hidden = !details.open;
        details.querySelector('summary').setAttribute('aria-expanded', String(details.open));
    }

    filterActivities() {
        const query = this.activitySearchTarget.value.trim().toLocaleLowerCase();
        let visible = 0;
        this.activityOptionTargets.forEach(option => {
            const type = option.closest('[data-activity-type]')?.dataset.activityType || '';
            option.hidden = !`${type} ${option.textContent}`.toLocaleLowerCase().includes(query);
            if (!option.hidden) visible++;
        });
        this.activityGroupTargets.forEach(group => {
            group.hidden = !Array.from(group.querySelectorAll('label')).some(option => !option.hidden);
            if (query) {
                if (!group.hasAttribute('data-search-open')) group.dataset.searchOpen = String(group.open);
                group.open = !group.hidden;
            } else if (group.hasAttribute('data-search-open')) {
                group.open = group.dataset.searchOpen === 'true';
                delete group.dataset.searchOpen;
            }
        });
        this.activityStatusTarget.textContent = `${visible} ${visible === 1 ? 'option' : 'options'} shown`;
    }

    updateCount() {
        const selected = this.selectedOptions();
        this.activityCountTarget.textContent = selected.length ? `(${selected.length})` : '';
        this.groupCountTargets.forEach(count => {
            const checked = count.closest('details').querySelectorAll('input:checked').length;
            count.textContent = checked ? `(${checked})` : '';
        });
        if (this.hasSelectedLabelsTarget) {
            this.selectedLabelsTarget.replaceChildren(...selected.map(option => {
                const item = document.createElement('li');
                item.textContent = option.querySelector('.kc-choice-name').textContent.trim();
                return item;
            }));
            this.selectedSummaryTarget.hidden = selected.length === 0;
            this.selectionStatusTarget.textContent = this.appliedSelection !== this.selectionKey(selected)
                ? this.pendingMessageValue : '';
        }
    }

    selectedOptions() {
        return this.activityOptionTargets.filter(option => option.querySelector('input').checked);
    }

    selectionKey(options) {
        return JSON.stringify(options.map(option => {
            const input = option.querySelector('input');
            return [input.name, input.value];
        }));
    }
}

window.Controllers = window.Controllers || {};
window.Controllers['public-calendar'] = PublicCalendarController;
export default PublicCalendarController;
