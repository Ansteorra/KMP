import { Controller } from '@hotwired/stimulus';

/**
 * Keeps hidden page_context_url fields in sync with the browser address bar.
 *
 * Listens for grid navigation and tab changes so modal POSTs preserve filters.
 */
class PageContextController extends Controller {
    connect() {
        this.modalGrids = new WeakMap();
        this.boundCaptureModalGrid = this.captureModalGrid.bind(this);
        this.boundSync = this.sync.bind(this);
        this.boundSyncRequestReferrer = this.syncRequestReferrer.bind(this);
        window.addEventListener('grid-view:navigated', this.boundSync);
        window.addEventListener('page-context:sync', this.boundSync);
        window.addEventListener('popstate', this.boundSync);
        document.addEventListener('turbo:frame-load', this.boundSync);
        document.addEventListener('submit', this.boundSync, true);
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
        document.removeEventListener('turbo:before-fetch-request', this.boundSyncRequestReferrer);
        document.removeEventListener('show.bs.modal', this.boundCaptureModalGrid);
    }

    /** Remember the originating grid for native forms whose modal sits outside the table. */
    captureModalGrid(event) {
        const gridSelector = '[data-controller~="grid-view"]';
        const trigger = event.relatedTarget;
        const grid = trigger?.closest(gridSelector)
            ?? trigger?.closest('[role="tabpanel"]')?.querySelector(gridSelector)
            ?? event.target.closest('[role="tabpanel"]')?.querySelector(gridSelector);
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
            options.referrer = window.location.href;
        }
    }

    sync(event) {
        const url = window.location.pathname + window.location.search;
        const form = event?.type === 'submit' && event.target instanceof HTMLFormElement
            ? event.target : null;
        const modal = form?.closest('.modal') ?? form?.querySelector('.modal');
        const grid = this.modalGrids.get(modal) ?? form?.closest('[data-controller~="grid-view"]');
        const frame = grid?.querySelector('turbo-frame[id$="-table"]');
        const src = frame?.dataset.gridCurrentSrc || frame?.getAttribute('src') || frame?.dataset.gridSrc;
        const formUrl = new URL(window.location.href);
        if (src) {
            const tab = formUrl.searchParams.get('tab');
            formUrl.search = new URL(src, window.location.origin).search;
            if (tab && !formUrl.searchParams.has('tab')) {
                formUrl.searchParams.set('tab', tab);
            }
        }
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
