import { Controller } from "@hotwired/stimulus";

/**
 * DetailTabs Stimulus Controller
 * 
 * Manages tabbed interfaces with URL state management and browser history integration.
 * Automatically handles tab activation, URL updates, and Turbo frame reloading for
 * dynamic content management.
 * 
 * Features:
 * - URL-based tab selection and state persistence
 * - Browser history integration with pushState
 * - Automatic first tab activation
 * - Turbo frame reloading on tab change
 * - Configurable URL update behavior
 * - Scroll management for better UX
 * 
 * Values:
 * - updateUrl: Boolean (default: true) - Whether to update URL on tab change
 * 
 * Targets:
 * - tabBtn: Tab button elements for navigation
 * - tabContent: Tab content panels (optional)
 * 
 * Usage:
 * <div data-controller="detail-tabs" data-detail-tabs-update-url-value="true">
 *   <nav>
 *     <button data-detail-tabs-target="tabBtn" id="nav-info-tab">Info</button>
 *     <button data-detail-tabs-target="tabBtn" id="nav-history-tab">History</button>
 *   </nav>
 *   <turbo-frame id="info-frame">...</turbo-frame>
 *   <turbo-frame id="history-frame">...</turbo-frame>
 * </div>
 */
class DetailTabsController extends Controller {
    static targets = ["tabBtn", "tabContent"]
    static values = { updateUrl: { type: Boolean, default: true } }
    foundFirst = false;

    /**
     * Handle tab button connection to DOM
     * Sets up tab activation based on URL parameters or defaults to first tab
     * 
     * @param {HTMLElement} event - Connected tab button element
     */
    tabBtnTargetConnected(event) {
        var tab = event.id.replace('nav-', '').replace('-tab', '');
        var urlTab = KMP_utils.urlParam('tab');
        if (urlTab) {
            if (tab == urlTab) {
                event.click();
                this.foundFirst = true;
                window.scrollTo(0, 0);
            }
        } else {
            if (!this.foundFirst) {
                // Get the first tab based on CSS order, not DOM order
                const firstTab = this.getFirstTabByOrder();
                if (firstTab) {
                    firstTab.click();
                    this.foundFirst = true;
                }
                window.scrollTo(0, 0);
            }
        }
        event.addEventListener('click', this.tabBtnClicked.bind(this));
    }

    /**
     * Get the first tab button based on CSS order attribute
     * Respects the data-tab-order attribute for mixed plugin/template tabs
     * 
     * @returns {HTMLElement|null} First tab button by order, or null if none found
     */
    getFirstTabByOrder() {
        if (this.tabBtnTargets.length === 0) {
            return null;
        }

        // Sort tabs by their order attribute (lower number = first)
        const sortedTabs = [...this.tabBtnTargets].sort((a, b) => {
            const orderA = parseInt(a.dataset.tabOrder || '999', 10);
            const orderB = parseInt(b.dataset.tabOrder || '999', 10);
            return orderA - orderB;
        });

        return sortedTabs[0];
    }

    /**
     * Handle tab button clicks
     * Updates URL history and triggers frame reloading for dynamic content
     * 
     * @param {Event} event - Click event from tab button
     */
    tabBtnClicked(event) {
        // Get first tab based on order, not DOM position
        const firstTab = this.getFirstTabByOrder();
        const firstTabId = firstTab?.id || this.tabBtnTargets[0]?.id;
        var eventTabId = event.target.id;
        var tab = event.target.id.replace('nav-', '').replace('-tab', '');
        if (this.updateUrlValue) {
            if (firstTabId != eventTabId) {
                window.history.pushState({}, '', '?tab=' + tab);
            } else {
                //only push state if there is a tab in the querystring
                var urlTab = KMP_utils.urlParam('tab');
                if (urlTab) {
                    window.history.pushState({}, '', window.location.pathname);
                }
            }
        }
        var frame = document.getElementById(tab + '-frame');
        if (frame) {
            // Check if frame has been loaded before - if it has a src and is complete, reload it
            // Otherwise, let the lazy loading handle the initial load
            if (frame.loaded || (frame.complete && !frame.hasAttribute('loading'))) {
                frame.reload();
            }
        }
    }

    /**
     * Handle tab button disconnection from DOM
     * Cleans up event listeners to prevent memory leaks
     * 
     * @param {HTMLElement} event - Disconnected tab button element
     */
    tabBtnTargetDisconnected(event) {
        event.removeEventListener('click', this.tabBtnClicked.bind(this));
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["detail-tabs"] = DetailTabsController;