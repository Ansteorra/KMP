import { Controller } from "@hotwired/stimulus"

/**
 * OutletButton Stimulus Controller
 * 
 * Manages inter-controller communication through data passing and event dispatching.
 * Provides a button component that can collect data from other controllers and
 * dispatch custom events for complex workflow coordination.
 * 
 * Features:
 * - Data collection and validation
 * - Conditional button state management
 * - Custom event dispatching for controller communication
 * - Required data validation with automatic button disabling
 * - Event listener management for workflow integration
 * - Outlet-based controller communication patterns
 * 
 * Values:
 * - btnData: Object - Data collected from other controllers
 * - requireData: Boolean - Whether data is required for button activation
 * 
 * Usage:
 * <button data-controller="outlet-btn"
 *         data-outlet-btn-require-data-value="true"
 *         data-action="click->outlet-btn#fireNotice">
 *   Submit
 * </button>
 * 
 * JavaScript integration:
 * controller.addBtnData({memberId: 123, action: 'assign'});
 */
class OutletButton extends Controller {
    static values = {
        btnData: Object,
        requireData: Boolean,
    }

    /**
     * Handle button data value changes
     * Updates button state based on data availability and requirements
     */
    btnDataValueChanged() {
        if (this.btnDataValue === null) {
            this.btnDataValue = {};
        }
        if (this.requireDataValue && Object.keys(this.btnDataValue).length === 0) {
            this.element.disabled = true;
        } else {
            this.element.disabled = false;
        }
    }

    /**
     * Add data to button for communication
     * Updates the button's data payload for event dispatching
     * 
     * @param {Object} data - Data object to associate with button
     */
    addBtnData(data) {
        this.btnDataValue = data;
    }

    /**
     * Fire custom event with button data
     * Dispatches outlet-button-clicked event with collected data
     * 
     * @param {Event} event - Click event from button
     */
    fireNotice(event) {
        let btnData = this.btnDataValue;
        this.dispatch("outlet-button-clicked", { detail: btnData });
    }

    /**
     * Add event listener for outlet button events
     * Registers callback for custom outlet button events
     * 
     * @param {Function} callback - Event handler function
     */
    addListener(callback) {
        this.element.addEventListener("outlet-btn:outlet-button-clicked", callback);
    }

    /**
     * Remove event listener for outlet button events
     * Unregisters callback to prevent memory leaks
     * 
     * @param {Function} callback - Event handler function to remove
     */
    removeListener(callback) {
        this.element.removeEventListener("outlet-btn:outlet-button-clicked", callback);
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["outlet-btn"] = OutletButton;