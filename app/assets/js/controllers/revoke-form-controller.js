import { Controller } from "@hotwired/stimulus"

/**
 * **INTERNAL CODE DOCUMENTATION COMPLETE**
 * 
 * Revoke Form Controller
 * 
 * A specialized Stimulus controller that manages revocation workflows with outlet communication
 * and form validation. Provides seamless integration between trigger elements and revocation
 * forms with real-time validation and submit control.
 * 
 * Key Features:
 * - Inter-controller communication through outlet pattern
 * - Dynamic ID management from external triggers
 * - Real-time form validation with submit control
 * - Automatic event listener management and cleanup
 * - Bootstrap form integration with disabled state management
 * 
 * @class RevokeForm
 * @extends Controller
 * 
 * HTML Structure Example:
 * ```html
 * <!-- Revocation form with outlet communication -->
 * <form data-controller="revoke-form" 
 *       data-revoke-form-url-value="/revoke"
 *       data-revoke-form-outlet-btn-outlet="[data-controller*='outlet-btn']">
 *   <input type="hidden" data-revoke-form-target="id">
 *   
 *   <div class="mb-3">
 *     <label for="reason" class="form-label">Revocation Reason</label>
 *     <textarea data-revoke-form-target="reason" 
 *               data-action="input->revoke-form#checkReadyToSubmit"
 *               class="form-control" 
 *               rows="3" 
 *               required></textarea>
 *   </div>
 *   
 *   <button type="submit" 
 *           data-revoke-form-target="submitBtn"
 *           class="btn btn-danger">
 *     Revoke Access
 *   </button>
 * </form>
 * 
 * <!-- Trigger button with outlet communication -->
 * <button data-controller="outlet-btn" 
 *         data-outlet-btn-id-value="123"
 *         data-action="click->outlet-btn#fireNotice">
 *   Revoke User 123
 * </button>
 * ```
 */
class RevokeForm extends Controller {
    static values = {
        url: String,
    }
    static targets = ["submitBtn", "reason", "id"]

    static outlets = ["outlet-btn"]

    /**
     * Handle ID setting from outlet communication
     * Receives ID from outlet button clicks and updates hidden form field
     * 
     * @param {CustomEvent} event - Custom event containing ID details
     * @param {Object} event.detail - Event details object
     * @param {String} event.detail.id - The ID value to set in the form
     */
    setId(event) {
        this.idTarget.value = event.detail.id;
    }

    /**
     * Handle outlet button connection
     * Sets up event listener for ID communication from outlet buttons
     * 
     * @param {Controller} outlet - The connected outlet button controller
     * @param {HTMLElement} element - The outlet button element
     */
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }

    /**
     * Handle outlet button disconnection
     * Removes event listener to prevent memory leaks
     * 
     * @param {Controller} outlet - The disconnected outlet button controller
     */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }

    /**
     * Validate form readiness and control submit button state
     * Enables submit button only when reason field contains valid content
     * Provides real-time feedback for form completion status
     */
    checkReadyToSubmit() {
        let reasonValue = this.reasonTarget.value;
        if (reasonValue.length > 0) {
            this.submitBtnTarget.disabled = false;
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }


    /**
     * Initialize controller state and disable form submission
     * Sets initial form state with disabled submit button until validation passes
     */
    connect() {
        this.submitBtnTarget.disabled = true;
    }

}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["revoke-form"] = RevokeForm;