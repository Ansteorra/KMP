import { Controller } from "@hotwired/stimulus"

/**
 * **INTERNAL CODE DOCUMENTATION COMPLETE**
 * 
 * Select All Switch List Controller
 * 
 * A sophisticated Stimulus controller that provides automatic "Select All" functionality for
 * Bootstrap form-switch checkbox lists. Creates and manages a master checkbox that controls
 * all individual checkboxes with bidirectional synchronization.
 * 
 * Key Features:
 * - Automatic "Select All" header checkbox generation
 * - Bidirectional synchronization between master and individual checkboxes
 * - Bootstrap form-switch styling consistency
 * - Dynamic checkbox discovery and event management
 * - Accessibility support with ARIA labels
 * - Automatic state management for partial selections
 * 
 * @class SelectAllListController
 * @extends Controller
 * 
 * HTML Structure Example:
 * ```html
 * <div data-controller="select-all-switch">
 *   <!-- Individual checkboxes (Select All checkbox will be auto-generated) -->
 *   <div class="form-check form-switch">
 *     <input class="form-check-input" type="checkbox" id="item1" name="items[]" value="1">
 *     <label class="form-check-label" for="item1">Item 1</label>
 *   </div>
 *   
 *   <div class="form-check form-switch">
 *     <input class="form-check-input" type="checkbox" id="item2" name="items[]" value="2">
 *     <label class="form-check-label" for="item2">Item 2</label>
 *   </div>
 *   
 *   <div class="form-check form-switch">
 *     <input class="form-check-input" type="checkbox" id="item3" name="items[]" value="3">
 *     <label class="form-check-label" for="item3">Item 3</label>
 *   </div>
 * </div>
 * ```
 */
class SelectAllListController extends Controller {
    /** @type {NodeList} Collection of all checkboxes in the list including the master checkbox */
    allCheckboxes;

    /**
     * Initialize controller and generate Select All functionality
     * Creates master checkbox, sets up event listeners, and establishes synchronization
     */
    connect() {
        //copy the first form-check form-switch checkbox and make it a select all checkbox
        const selectAllCheckbox = this.element.querySelector('.form-check.form-switch').cloneNode(true);
        selectAllCheckbox.querySelector('input[type="checkbox"]').setAttribute('data-select-all', 'true');
        selectAllCheckbox.querySelector('input[type="checkbox"]').setAttribute('aria-label', 'Select All');
        selectAllCheckbox.querySelector('label').innerText = 'Select All';
        // get the first form-check form-switch checkbox and set the id to select-all
        const firstCheckbox = this.element.querySelector('.form-check.form-switch');
        firstCheckbox.parentNode.insertBefore(selectAllCheckbox, firstCheckbox);
        this.allCheckboxes = this.element.querySelectorAll('input[type="checkbox"]');
        this.allCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', this.updateSelectAll.bind(this));
        });
    }

    /**
     * Handle checkbox state changes and maintain synchronization
     * Manages bidirectional relationship between master and individual checkboxes
     * Updates master checkbox state based on individual selections
     * 
     * @param {Event} event - The change event from any checkbox in the list
     */
    updateSelectAll(event) {
        const selectAllCheckbox = this.element.querySelector('input[type="checkbox"][data-select-all]');
        if (event.target === selectAllCheckbox) {
            this.allCheckboxes.forEach((checkbox) => {
                if (checkbox !== selectAllCheckbox) {
                    checkbox.checked = selectAllCheckbox.checked;
                }
            });
        } else {
            const allChecked = Array.from(this.allCheckboxes).every((checkbox) => checkbox.checked && checkbox !== selectAllCheckbox);
            selectAllCheckbox.checked = allChecked;
        }
    }

}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["select-all-switch"] = SelectAllListController;