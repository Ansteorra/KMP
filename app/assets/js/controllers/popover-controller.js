import { Controller } from "@hotwired/stimulus";

/**
 * Popover Controller
 * 
 * A reusable Stimulus controller for Bootstrap popovers with support for:
 * - HTML content with close buttons
 * - Custom allowList for sanitizer (allows button elements)
 * - Auto-initialization on connect
 * - Proper cleanup on disconnect
 * 
 * Usage:
 * <button type="button" 
 *     data-controller="popover"
 *     data-bs-toggle="popover"
 *     data-bs-trigger="click"
 *     data-bs-html="true"
 *     data-bs-content="<div>Content with <button class='btn-close popover-close-btn'></button></div>">
 *     Open Popover
 * </button>
 */
class PopoverController extends Controller {
    static values = {
        placement: { type: String, default: "auto" },
        trigger: { type: String, default: "click" },
        html: { type: Boolean, default: true },
        customClass: { type: String, default: "" }
    };

    connect() {
        this.initializePopover();
        this.setupCloseButtonHandler();
    }

    disconnect() {
        this.removeCloseButtonHandler();
        this.destroyPopover();
    }

    initializePopover() {
        // Custom allowList to permit button elements in popover content
        const allowList = Object.assign({}, bootstrap.Popover.Default.allowList);
        allowList.button = ['type', 'class', 'aria-label'];

        // Get options from data attributes or use defaults
        const options = {
            allowList: allowList,
            placement: this.placementValue,
            trigger: this.triggerValue,
            html: this.htmlValue,
        };

        if (this.customClassValue) {
            options.customClass = this.customClassValue;
        }

        // Initialize Bootstrap popover
        this.popover = new bootstrap.Popover(this.element, options);
    }

    destroyPopover() {
        if (this.popover) {
            this.popover.dispose();
            this.popover = null;
        }
    }

    setupCloseButtonHandler() {
        // Use bound method for proper removal later
        this.handleCloseClick = this.handleCloseClick.bind(this);
        document.addEventListener('click', this.handleCloseClick);
    }

    removeCloseButtonHandler() {
        document.removeEventListener('click', this.handleCloseClick);
    }

    handleCloseClick(event) {
        const closeBtn = event.target.closest('.popover .btn-close, .popover .popover-close-btn');
        if (!closeBtn) return;

        const popoverElement = closeBtn.closest('.popover');
        if (!popoverElement) return;

        // Check if this popover belongs to this controller's element
        const popoverId = popoverElement.id;
        if (this.element.getAttribute('aria-describedby') !== popoverId) return;

        // Hide the popover
        if (this.popover) {
            this.popover.hide();
        }

        event.preventDefault();
        event.stopPropagation();
    }

    // Action to programmatically show the popover
    show() {
        if (this.popover) {
            this.popover.show();
        }
    }

    // Action to programmatically hide the popover
    hide() {
        if (this.popover) {
            this.popover.hide();
        }
    }

    // Action to toggle the popover
    toggle() {
        if (this.popover) {
            this.popover.toggle();
        }
    }
}

// Register in global Controllers object
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["popover"] = PopoverController;

export default PopoverController;
