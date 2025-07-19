const { Controller } = require("@hotwired/stimulus");

/**
 * **INTERNAL CODE DOCUMENTATION COMPLETE**
 * 
 * App Setting Form Controller
 * 
 * A Stimulus controller that manages application settings forms with controlled submission
 * and button state management. Provides form validation workflow control and submit
 * button management for administrative configuration interfaces.
 * 
 * Key Features:
 * - Controlled form submission with event prevention
 * - Submit button state management and focus control
 * - Form validation integration support
 * - Administrative settings workflow management
 * - Bootstrap form integration patterns
 * 
 * @class AppSettingForm
 * @extends Controller
 * 
 * Targets:
 * - submitBtn: The form submit button element
 * - form: The form element to be submitted
 * 
 * HTML Structure Example:
 * ```html
 * <form data-controller="app-setting-form" 
 *       data-app-setting-form-target="form"
 *       method="post" 
 *       action="/admin/settings">
 *   <div class="mb-3">
 *     <label for="setting-key" class="form-label">Setting Key</label>
 *     <input type="text" 
 *            class="form-control" 
 *            id="setting-key" 
 *            name="key"
 *            data-action="input->app-setting-form#enableSubmit">
 *   </div>
 *   
 *   <div class="mb-3">
 *     <label for="setting-value" class="form-label">Setting Value</label>
 *     <input type="text" 
 *            class="form-control" 
 *            id="setting-value" 
 *            name="value"
 *            data-action="input->app-setting-form#enableSubmit">
 *   </div>
 *   
 *   <button type="submit" 
 *           data-app-setting-form-target="submitBtn"
 *           data-action="click->app-setting-form#submit"
 *           class="btn btn-primary" 
 *           disabled>
 *     Save Setting
 *   </button>
 * </form>
 * ```
 */
class AppSettingForm extends Controller {
    static targets = ["submitBtn", "form"]

    /**
     * Handle form submission with event prevention
     * Prevents default form submission behavior and manually triggers form submit
     * Allows for additional validation or processing before submission
     * 
     * @param {Event} event - The form submit event
     */
    submit(event) {
        event.preventDefault()
        this.formTarget.submit()
    }

    /**
     * Enable submit button and set focus
     * Called when form validation passes or content changes
     * Provides immediate user feedback for form readiness
     */
    enableSubmit() {
        this.submitBtnTarget.disabled = false;
        this.submitBtnTarget.focus();
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["app-setting-form"] = AppSettingForm;