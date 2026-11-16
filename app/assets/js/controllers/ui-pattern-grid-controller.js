import { updateUiHistory } from '../services/ui-navigation-service.js';
import { Controller } from '@hotwired/stimulus';

/** Showcase presentation around the real Dataverse controller; no duplicate query or view store. */
class UiPatternGridController extends Controller {
    static targets = ['search', 'viewStatus', 'restore', 'layoutButton', 'displayOptions',
        'selectionBar', 'selectionCount', 'selectedNames'];

    /** Keep supplemental controls in step with server-rendered Turbo results. */
    connect() {
        this.media = window.matchMedia('(max-width: 767px)');
        this.frameListener = () => {
            cancelAnimationFrame(this.pendingFrame);
            this.pendingFrame = requestAnimationFrame(() => this.sync());
        };
        this.selectionListener = () => this.syncSelection();
        this.mediaListener = () => this.updateLayout();
        this.element.addEventListener('turbo:frame-load', this.frameListener);
        this.element.addEventListener('grid-view:selection-changed', this.selectionListener);
        this.media.addEventListener('change', this.mediaListener);
        this.displayOptionsTarget.open = !this.media.matches;
        const filterMenu = this.element.querySelector('[data-filter-button] + .dropdown-menu');
        if (filterMenu && !filterMenu.querySelector('.ui-grid-filter-done')) {
            const footer = document.createElement('div');
            footer.className = 'ui-grid-filter-done';
            const done = document.createElement('button');
            done.className = 'btn btn-primary w-100';
            done.type = 'button';
            done.textContent = 'Show results';
            done.dataset.action = 'ui-pattern-grid#closeFilters';
            footer.append(done);
            filterMenu.append(footer);
        }
        this.sync();
    }

    /** Release observers when leaving the showcase. */
    disconnect() {
        cancelAnimationFrame(this.pendingFrame);
        this.element.removeEventListener('turbo:frame-load', this.frameListener);
        this.element.removeEventListener('grid-view:selection-changed', this.selectionListener);
        this.media.removeEventListener('change', this.mediaListener);
    }

    /** Dataverse remains the single owner of filters, views, queries, and selection. */
    get grid() {
        return this.application.getControllerForElementAndIdentifier(this.element, 'grid-view');
    }

    /** Mirror the server state; preserve entered search text while it is being edited. */
    sync() {
        const state = this.grid?.state;
        if (!state) return;
        if (document.activeElement !== this.searchTarget) this.searchTarget.value = state.search || '';
        const changed = state.view.hasChanges;
        const hidingFocusedRestore = !changed && !this.restoreTarget.hidden
            && document.activeElement === this.restoreTarget;
        this.viewStatusTarget.textContent = `${state.view.currentName}${changed ? ' · Unsaved changes' : ''}`;
        this.restoreTarget.hidden = !changed;
        if (hidingFocusedRestore) {
            const target = this.element.querySelector('[data-view-tabs-container] [role="tab"][aria-selected="true"]')
                || this.element.querySelector('[data-filter-button]');
            target?.focus({ preventScroll: true });
        }
        this.updateLayout();
        this.syncSelection();
    }

    /** Send the quick search through Dataverse's normal URL and Turbo behavior. */
    search(event) {
        event.preventDefault();
        if (!this.grid?.hasSearchInputTarget) return;
        this.grid.searchInputTarget.value = this.searchTarget.value;
        this.grid.performSearch();
    }

    /** Reload the saved definition, discarding only temporary view edits. */
    restoreView() {
        const viewId = this.grid?.state.view.currentId;
        if (viewId) this.grid.switchView({ currentTarget: { dataset: { viewId } } });
        else this.grid?.showAll();
    }

    /** Store display choice in the current URL, separate from the saved Dataverse schema. */
    layout(event) {
        const choice = event.currentTarget.dataset.layout;
        if (!['cards', 'table'].includes(choice)) return;
        const url = new URL(window.location.href);
        url.searchParams.set('layout', choice);
        updateUiHistory(url.pathname + url.search + url.hash, { replace: true });
        this.grid?.captureStickyParamsFromUrl(url.toString());
        window.dispatchEvent(new CustomEvent('page-context:sync'));
        this.updateLayout();
    }

    /** Present the same rows as labeled cards or a comparison table, without duplicate selection inputs. */
    updateLayout() {
        const requested = new URL(window.location.href).searchParams.get('layout');
        const layout = ['cards', 'table'].includes(requested) ? requested : (this.media.matches ? 'cards' : 'table');
        this.element.dataset.gridLayout = layout;
        this.layoutButtonTargets.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.layout === layout)));
        const table = this.element.querySelector('[data-grid-view-target="gridTable"]');
        if (!table) return;
        table.setAttribute('role', layout === 'cards' ? 'list' : 'table');
        table.setAttribute('aria-label', 'Gatherings');
        table.querySelector('tbody')?.setAttribute('role', layout === 'cards' ? 'presentation' : 'rowgroup');
        const state = this.grid.state;
        table.querySelectorAll('tbody tr').forEach(row => {
            row.setAttribute('role', layout === 'cards' ? 'listitem' : 'row');
            [...row.children].forEach(cell => cell.setAttribute('role', layout === 'cards' ? 'presentation' : 'cell'));
            if (!row.dataset.id) return;
            row.children[0]?.classList.add('ui-grid-selection-cell');
            row.lastElementChild?.classList.add('ui-grid-actions-cell');
            state.columns.visible.forEach((key, index) => {
                const cell = row.children[index + 1];
                if (!cell) return;
                cell.dataset.columnLabel = state.columns.all[key]?.label || key;
                cell.classList.toggle('ui-grid-date-cell', state.columns.all[key]?.filterType === 'date-range');
                if (!cell.querySelector('.ui-grid-card-label')) {
                    const label = document.createElement('span');
                    label.className = 'ui-grid-card-label';
                    label.textContent = cell.dataset.columnLabel;
                    cell.prepend(label);
                }
            });
        });
    }

    /** Select the current page using the same bulk-selection contract as table checkboxes. */
    selectPage() {
        this.grid?.toggleAllSelection({ target: { checked: true } });
    }

    /** Close the phone filter panel using Bootstrap's existing dropdown lifecycle. */
    closeFilters() {
        const trigger = this.element.querySelector('[data-filter-button]');
        if (!trigger) return;
        window.bootstrap?.Dropdown?.getInstance(trigger)?.hide();
        trigger.focus();
    }

    /** Summarize actual Dataverse selection for the review dialog. */
    syncSelection() {
        const grid = this.grid;
        if (!grid?.state) return;
        const ids = grid.selectedIds || [];
        this.selectionBarTarget.hidden = ids.length === 0;
        this.selectionCountTarget.textContent = `${ids.length} selected on this page`;
        this.selectedNamesTarget.replaceChildren();
        const nameIndex = grid.state.columns.visible.indexOf('name') + 1;
        grid.selectedRowCheckboxTargets().forEach(checkbox => {
            const item = document.createElement('li');
            const cell = checkbox.closest('tr')?.children[nameIndex];
            const copy = cell?.cloneNode(true);
            copy?.querySelector('.ui-grid-card-label')?.remove();
            item.textContent = copy?.textContent.trim() || checkbox.value;
            this.selectedNamesTarget.append(item);
        });
    }
}

window.Controllers ||= {};
window.Controllers['ui-pattern-grid'] = UiPatternGridController;
