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
     * Triggers form submission for filtering and pagination updates
     * 
     * @param {Event} event - Input/change event from form elements
     */
    submitForm(event) {
        console.log("submitting form");
        this.element.requestSubmit();
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["filter-grid"] = FilterGrid;