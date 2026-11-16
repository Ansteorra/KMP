import { Controller } from "@hotwired/stimulus";

/**
 * NavBar Stimulus Controller
 * 
 * Manages navigation bar expand/collapse state tracking and server synchronization.
 * Provides persistent navigation state across page loads by recording user
 * preferences via AJAX requests.
 * 
 * Features:
 * - Navigation expand/collapse state tracking
 * - Server-side state persistence via AJAX
 * - Automatic event listener management
 * - Dual-endpoint state recording (expand/collapse)
 * - Bootstrap navigation integration
 * 
 * Targets:
 * - navHeader: Navigation header elements with expand/collapse functionality
 * 
 * Usage:
 * <nav>
 *   <button data-nav-bar-target="navHeader" 
 *           data-expand-url="/api/nav/expand/menu1"
 *           data-collapse-url="/api/nav/collapse/menu1"
 *           aria-expanded="false">
 *     Menu Item
 *   </button>
 * </nav>
 * 
 * Required attributes on navHeader targets:
 * - data-expand-url: URL to call when navigation expands
 * - data-collapse-url: URL to call when navigation collapses
 * - aria-expanded: Current expansion state
 */
class NavBarController extends Controller {
    static targets = ["navHeader"]

    initialize() {
        this.navHeaderClicked = this.navHeaderClicked.bind(this);
    }

    /**
     * Handle navigation header clicks
     * Records expansion state to server based on aria-expanded attribute
     * 
     * @param {Event} event - Click event from navigation header
     */
    async navHeaderClicked(event) {
        const target = event.currentTarget || event.target;
        const attribute = target.getAttribute('aria-expanded') === 'true'
            ? 'data-expand-url' : 'data-collapse-url';
        const url = target.getAttribute(attribute);
        if (!url) return;
        try {
            const response = await fetch(url, this.optionsForFetch());
            if (!response.ok) throw new Error('Navigation preference was not saved.');
        } catch {
            // The menu's local state remains usable during an outage or departure.
            console.warn('Navigation preference could not be saved.');
        }
    }

    /**
     * Handle navigation header connection to DOM
     * Sets up click event listener for state tracking
     * 
     * @param {HTMLElement} event - Connected navigation header element
     */
    navHeaderTargetConnected(event) {
        event.addEventListener('click', this.navHeaderClicked);
    }

    /**
     * Handle navigation header disconnection from DOM
     * Cleans up click event listener
     * 
     * @param {HTMLElement} event - Disconnected navigation header element
     */
    navHeaderTargetDisconnected(event) {
        event.removeEventListener('click', this.navHeaderClicked);
    }

    /**
     * Configure fetch options for AJAX requests
     * Sets up headers for JSON API communication
     * 
     * @returns {Object} Fetch options object
     */
    optionsForFetch() {
        return {
            // This small preference write should finish when its document leaves.
            keepalive: true,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["nav-bar"] = NavBarController;
