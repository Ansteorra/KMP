import { Controller } from '@hotwired/stimulus';

/** Enhances native public calendar disclosures and bounded activity filtering. */
class PublicCalendarController extends Controller {
    static targets = ['activityOption', 'activityGroup', 'groupCount', 'activitySearch', 'activityStatus', 'activityCount', 'filterBar',
        'selectedSummary', 'selectedLabels', 'selectionStatus'];
    static values = { pendingMessage: String };

    connect() {
        this.appliedSelection = this.selectionKey(this.selectedOptions());
        this.updateCount();
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
