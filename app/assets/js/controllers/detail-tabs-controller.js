import { Controller } from "@hotwired/stimulus";
import { updateUiHistory } from '../services/ui-navigation-service.js';

/** Keeps detail tabs, browser history, and nested tab groups in sync. */
class DetailTabsController extends Controller {
    static targets = ["tabBtn", "tabContent"]
    static values = { updateUrl: { type: Boolean, default: true } }
    foundFirst = false;
    tabListeners = new Map();

    connect() {
        this.boundRestoreTab = this.restoreTab.bind(this);
        window.addEventListener('popstate', this.boundRestoreTab);
        window.addEventListener('kmp:ui-history-restored', this.boundRestoreTab);
        window.addEventListener('kmp:screen-refreshed', this.boundRestoreTab);
        document.addEventListener('turbo:morph', this.boundRestoreTab);
        this.restoreTab();
    }

    disconnect() {
        window.removeEventListener('popstate', this.boundRestoreTab);
        window.removeEventListener('kmp:ui-history-restored', this.boundRestoreTab);
        window.removeEventListener('kmp:screen-refreshed', this.boundRestoreTab);
        document.removeEventListener('turbo:morph', this.boundRestoreTab);
        for (const button of this.tabListeners.keys()) {
            this.tabBtnTargetDisconnected(button);
        }
        this.foundFirst = false;
    }

    /** Nested tabs keep local state without replacing the host detail tab in the URL. */
    get ownsUrl() {
        return this.updateUrlValue
            && !this.element.parentElement?.closest('[data-controller~="detail-tabs"]');
    }

    tabId(button) {
        return button.id.replace(/^nav-/, '').replace(/-tab$/, '');
    }

    get historyKey() {
        return this.element.id || this.getFirstTabByOrder()?.id;
    }

    /** Bind Bootstrap's activation event so keyboard selection also updates context. */
    tabBtnTargetConnected(button) {
        if (!this.tabListeners.has(button)) {
            const listener = this.tabBtnClicked.bind(this);
            this.tabListeners.set(button, listener);
            button.addEventListener('shown.bs.tab', listener);
        }
        if (!this.foundFirst) {
            this.restoreTab();
        }
    }

    /** Select the first tab by the shared core/plugin ordering convention. */
    getFirstTabByOrder() {
        return [...this.tabBtnTargets].sort((a, b) =>
            parseInt(a.dataset.tabOrder || '999', 10) - parseInt(b.dataset.tabOrder || '999', 10),
        )[0] ?? null;
    }

    /** Restore history without creating entries, scrolling, or reloading loaded frames. */
    restoreTab() {
        const requested = this.ownsUrl ? new URL(window.location.href).searchParams.get('tab')
            : window.history.state?.kmp?.nestedTabs?.[this.historyKey];
        const button = this.tabBtnTargets.find(tab => this.tabId(tab) === requested)
            ?? (this.ownsUrl ? null : this.tabBtnTargets.find(tab => tab.classList.contains('active')))
            ?? this.getFirstTabByOrder();
        if (!button) return;

        this.restoringTab = true;
        this.foundFirst = true;
        try {
            // Bootstrap manages panel visibility, roving tabindex, and ARIA state.
            const Tab = window.bootstrap?.Tab;
            if (Tab?.getOrCreateInstance) {
                Tab.getOrCreateInstance(button).show();
            } else {
                button.click();
            }
        } finally {
            this.restoringTab = false;
            this.selectedTabId = this.tabId(button);
        }
    }

    /** Preserve host queries and hashes; Turbo's lazy frames load when made visible. */
    tabBtnClicked(event) {
        if (!this.updateUrlValue || this.restoringTab) return;
        const button = event.currentTarget || event.target.closest('[data-detail-tabs-target="tabBtn"]');
        if (!this.tabBtnTargets.includes(button)) return;

        if (!this.ownsUrl) {
            const selected = this.tabId(button);
            if (!window.history.state?.kmp?.nestedTabs?.[this.historyKey]) {
                const initial = this.selectedTabId || this.tabId(this.getFirstTabByOrder());
                updateUiHistory(window.location.href, { replace: true, nestedTabs: { [this.historyKey]: initial } });
            }
            if (window.history.state?.kmp?.nestedTabs?.[this.historyKey] !== selected) {
                updateUiHistory(window.location.href, { nestedTabs: { [this.historyKey]: selected } });
            }
            this.selectedTabId = selected;
            return;
        }

        const url = new URL(window.location.href);
        if (button === this.getFirstTabByOrder()) {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', this.tabId(button));
        }
        if (url.href === window.location.href) return;
        updateUiHistory(url.pathname + url.search + url.hash);
        window.dispatchEvent(new CustomEvent('page-context:sync'));
    }

    tabBtnTargetDisconnected(button) {
        const listener = this.tabListeners.get(button);
        if (listener) {
            button.removeEventListener('shown.bs.tab', listener);
            this.tabListeners.delete(button);
        }
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["detail-tabs"] = DetailTabsController;
