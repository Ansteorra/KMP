import { Controller } from "@hotwired/stimulus"

/**
 * FilterGrid Stimulus Controller
 * 
 * Handles form submission for grid filtering and search functionality.
 * Provides automatic form submission when filter criteria are changed,
 * enabling dynamic grid updates without manual form submission.
 * 
 * Features:
 * - Automatic form submission on filter changes
 * - Grid refresh and pagination support
 * - Integration with CakePHP search forms
 * - Console logging for debugging
 * 
 * Usage:
 * <form data-controller="filter-grid" method="get">
 *   <input type="text" data-action="input->filter-grid#submitForm">
 *   <select data-action="change->filter-grid#submitForm">
 *     <option value="">All</option>
 *   </select>
 * </form>
 */
class FilterGrid extends Controller {
    /**
     * Submit the form to update grid results
     * Triggers form submission for filtering and pagination updates.
     * Uses feature detection to handle Turbo 8.0.21 compatibility:
     * - Tries requestSubmit() first if available
     * - Falls back to clicking an existing or temporary submit button
     * 
     * @param {Event} event - Input/change event from form elements
     */
    submitForm(event) {
        console.log("submitting form");
        this._safeSubmit(this.element);
    }

    /**
     * Safely submit a form with fallback for Turbo 8.0.21 compatibility
     * 
     * @param {HTMLFormElement} form - The form element to submit
     * @private
     */
    _safeSubmit(form) {
        // Try requestSubmit first if available
        if (typeof form.requestSubmit === 'function') {
            try {
                form.requestSubmit();
                return;
            } catch (e) {
                // requestSubmit failed, fall through to fallback
                console.warn("requestSubmit failed, using fallback:", e);
            }
        }

        // Fallback: find existing submit button or create a temporary one
        let submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        
        if (submitButton) {
            // Use existing submit button
            submitButton.click();
        } else {
            // Create temporary hidden submit button
            const tempButton = document.createElement('button');
            tempButton.type = 'submit';
            tempButton.style.display = 'none';
            form.appendChild(tempButton);
            tempButton.click();
            tempButton.remove();
        }
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["filter-grid"] = FilterGrid;