import { Controller } from '@hotwired/stimulus';

/**
 * Keeps hidden page_context_url fields in sync with the browser address bar.
 *
 * Preserves grid queries on modal POSTs and full-page departures from detail tabs.
 */
class PageContextController extends Controller {
    connect() {
        this.modalGrids = new WeakMap();
        this.boundCaptureModalGrid = this.captureModalGrid.bind(this);
        this.boundSync = this.sync.bind(this);
        this.boundPrepareLink = this.prepareLink.bind(this);
        this.boundSyncRequestReferrer = this.syncRequestReferrer.bind(this);
        window.addEventListener('grid-view:navigated', this.boundSync);
        window.addEventListener('page-context:sync', this.boundSync);
        window.addEventListener('popstate', this.boundSync);
        document.addEventListener('turbo:frame-load', this.boundSync);
        document.addEventListener('submit', this.boundSync, true);
        document.addEventListener('page-context:before-submit', this.boundSync);
        document.addEventListener('click', this.boundPrepareLink, true);
        document.addEventListener('turbo:before-fetch-request', this.boundSyncRequestReferrer);
        document.addEventListener('show.bs.modal', this.boundCaptureModalGrid);
        this.sync();
    }

    disconnect() {
        window.removeEventListener('grid-view:navigated', this.boundSync);
        window.removeEventListener('page-context:sync', this.boundSync);
        window.removeEventListener('popstate', this.boundSync);
        document.removeEventListener('turbo:frame-load', this.boundSync);
        document.removeEventListener('submit', this.boundSync, true);
        document.removeEventListener('page-context:before-submit', this.boundSync);
        document.removeEventListener('click', this.boundPrepareLink, true);
        document.removeEventListener('turbo:before-fetch-request', this.boundSyncRequestReferrer);
        document.removeEventListener('show.bs.modal', this.boundCaptureModalGrid);
    }

    /** Remember the originating grid for native forms whose modal sits outside the table. */
    captureModalGrid(event) {
        const gridSelector = '[data-controller~="grid-view"]';
        const trigger = event.relatedTarget;
        const sourceFrame = trigger?.closest('turbo-frame') ?? event.target.closest('turbo-frame');
        const frameGrids = sourceFrame?.querySelectorAll(gridSelector);
        const grid = trigger?.closest(gridSelector)
            ?? trigger?.closest('[role="tabpanel"]')?.querySelector(gridSelector)
            ?? event.target.closest('[role="tabpanel"]')?.querySelector(gridSelector)
            ?? (frameGrids?.length === 1 ? frameGrids[0] : null);
        if (grid) {
            this.modalGrids.set(event.target, grid);
        }
    }

    /** Keep record-page Back links aware of the live grid URL with Turbo Drive disabled. */
    syncRequestReferrer(event) {
        const options = event.detail?.fetchOptions;
        const requestUrl = event.detail?.url;
        if (!options || !requestUrl) {
            return;
        }
        const url = new URL(requestUrl, window.location.origin);
        if (url.origin === window.location.origin) {
            const form = event.target instanceof HTMLFormElement ? event.target : null;
            options.referrer = this.gridContextUrl(this.gridForForm(form)).href;
        }
    }

    gridForForm(form) {
        const modal = form?.closest('.modal') ?? form?.querySelector('.modal');
        return this.modalGrids.get(modal) ?? form?.closest('[data-controller~="grid-view"]');
    }

    /** Promote an embedded grid's live query before a same-window record navigation. */
    prepareLink(event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || link.closest('.paginator, .pagination')
            || link.hasAttribute('data-bs-toggle') || !['', '_self', '_top'].includes(link.target)) {
            return;
        }
        const url = new URL(link.href, window.location.origin);
        if (url.origin !== window.location.origin || url.pathname === window.location.pathname) {
            return;
        }
        const frame = link.closest('turbo-frame');
        const target = link.closest('[data-turbo-frame]')?.dataset.turboFrame || frame?.getAttribute('target');
        const native = link.closest('[data-turbo]')?.dataset.turbo === 'false';
        if (frame && !native && target !== '_top' && link.target !== '_top') {
            return;
        }
        this.prepareDeparture(link.closest('[data-controller~="grid-view"]'));
    }

    /** Use the host path and tab, omitting endpoint identities that sibling grids own. */
    gridContextUrl(grid) {
        const url = new URL(window.location.href);
        const frame = grid?.querySelector('turbo-frame[id$="-table"]');
        const src = frame?.dataset.gridCurrentSrc || frame?.getAttribute('src') || frame?.dataset.gridSrc;
        if (!src) {
            return url;
        }
        const source = new URL(src, window.location.origin);
        if (source.origin !== window.location.origin) {
            return url;
        }
        const tab = url.searchParams.get('tab');
        url.search = source.search;
        ['frame_id', 'member_id', 'branch_id', 'gathering_id'].forEach(key => url.searchParams.delete(key));
        if (tab) {
            url.searchParams.set('tab', tab);
        }
        if (grid.dataset.gridViewSyncUrlValue === 'false' || grid.closest('[role="tabpanel"]')) {
            url.searchParams.set('grid_context', frame.id.slice(0, -'-table'.length));
        }
        return url;
    }

    prepareDeparture(grid) {
        if (!grid || (grid.dataset.gridViewSyncUrlValue !== 'false' && !grid.closest('[role="tabpanel"]'))) {
            return;
        }
        const url = this.gridContextUrl(grid);
        window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
    }

    sync(event) {
        const candidate = event?.type === 'page-context:before-submit' ? event.detail?.form : event?.target;
        const form = candidate instanceof HTMLFormElement ? candidate : null;
        const grid = this.gridForForm(form);
        const formUrl = this.gridContextUrl(grid);
        const native = event?.type === 'page-context:before-submit'
            || (form?.closest('[data-turbo]')?.dataset.turbo !== 'true'
                && !form?.matches('[data-controller~="turbo-modal"]'));
        const action = form ? new URL(form.action, window.location.origin) : null;
        if (form && native && action.origin === window.location.origin && ['', '_self', '_top'].includes(form.target)) {
            this.prepareDeparture(grid);
        }
        const url = window.location.pathname + window.location.search;
        document.querySelectorAll(
            'input[name="page_context_url"]',
        ).forEach((input) => {
            if (input.dataset.pageContextStatic === 'true') {
                return;
            }
            input.value = form && input.form === form ? formUrl.pathname + formUrl.search : url;
        });
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers['page-context'] = PageContextController;
