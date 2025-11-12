import { Controller } from "@hotwired/stimulus"

/**
 * Security Debug Controller
 * 
 * Handles the display and interaction of security debug information in debug mode.
 * Provides toggle functionality to show/hide detailed security information including
 * user policies and authorization check logs.
 * 
 * Features:
 * - Toggle visibility of security debug panel
 * - Smooth slide animations
 * - Persistent state during page session
 * - AJAX loading of security information on first view
 * 
 * HTML Structure:
 * ```html
 * <div data-controller="security-debug">
 *   <button data-action="click->security-debug#toggle" data-security-debug-target="toggleBtn">
 *     Show Security Info
 *   </button>
 *   <div data-security-debug-target="panel" style="display: none;">
 *     <!-- Security info content -->
 *   </div>
 * </div>
 * ```
 */
class SecurityDebugController extends Controller {
    static targets = ["panel", "toggleBtn"]

    /**
     * Initialize controller
     */
    initialize() {
        this.isVisible = false;
    }

    /**
     * Toggle the visibility of the security debug panel
     */
    toggle(event) {
        event.preventDefault();

        if (this.isVisible) {
            this.hide();
        } else {
            this.show();
        }
    }

    /**
     * Show the security debug panel
     */
    show() {
        this.panelTarget.style.display = 'block';
        this.isVisible = true;

        if (this.hasToggleBtnTarget) {
            this.toggleBtnTarget.textContent = 'Hide Security Info';
        }

        // Smooth scroll to panel
        setTimeout(() => {
            this.panelTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }

    /**
     * Hide the security debug panel
     */
    hide() {
        this.panelTarget.style.display = 'none';
        this.isVisible = false;

        if (this.hasToggleBtnTarget) {
            this.toggleBtnTarget.textContent = 'Show Security Info';
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["security-debug"] = SecurityDebugController;
