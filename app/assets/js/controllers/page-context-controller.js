import { Controller } from '@hotwired/stimulus';

/**
 * Keeps hidden page_context_url fields in sync with the browser address bar.
 *
 * Listens for grid navigation and tab changes so modal POSTs preserve filters.
 */
class PageContextController extends Controller {
    connect() {
        this.boundSync = this.sync.bind(this);
        window.addEventListener('grid-view:navigated', this.boundSync);
        window.addEventListener('page-context:sync', this.boundSync);
        this.sync();
    }

    disconnect() {
        window.removeEventListener('grid-view:navigated', this.boundSync);
        window.removeEventListener('page-context:sync', this.boundSync);
    }

    sync() {
        const url = window.location.pathname + window.location.search;
        document.querySelectorAll('input[name="page_context_url"]').forEach((input) => {
            input.value = url;
        });
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers['page-context'] = PageContextController;
