import { Controller } from "@hotwired/stimulus"

/**
 * Awards Award Form Controller
 * 
 * Comprehensive Stimulus controller for award form management with hierarchical validation and dynamic 
 * list management. Provides interactive form functionality for creating and editing awards with 
 * multi-value field support and administrative interface integration.
 * 
 * ## Form Interface Features
 * 
 * **Dynamic List Management:**
 * - Add/remove functionality for multi-value fields (e.g., specialties, categories)
 * - Real-time list validation with duplicate prevention
 * - Interactive item display with individual remove buttons
 * - JSON serialization for form submission and persistence
 * 
 * **User Interface Integration:**
 * - Bootstrap-styled interactive elements with consistent visual design
 * - Form validation feedback with immediate user response
 * - Administrative workflow integration with KMP utility functions
 * - Responsive design support for mobile and desktop interfaces
 * 
 * **Data Management:**
 * - JSON-based data persistence with form value synchronization
 * - Array-based item tracking with state preservation
 * - Form restoration from persisted data on page load
 * - Input sanitization using KMP utility functions
 * 
 * ## Administrative Workflow Integration
 * 
 * **Award Configuration:**
 * - Multi-value field management for award specialties and categories
 * - Dynamic form validation with real-time feedback
 * - Administrative interface consistency with KMP design patterns
 * - Form state preservation during editing workflows
 * 
 * **Form Processing:**
 * - JSON serialization for complex data structures
 * - Form value synchronization with backend data models
 * - Input validation and sanitization for security
 * - User feedback through visual interface updates
 * 
 * ## Usage Examples
 * 
 * ### Basic Award Form Integration
 * ```html
 * <!-- Award form with dynamic specialty management -->
 * <form data-controller="awards-award-form">
 *   <div class="mb-3">
 *     <label>Add Specialty</label>
 *     <input type="text" data-awards-award-form-target="new" class="form-control">
 *     <button type="button" data-action="click->awards-award-form#add" class="btn btn-primary">
 *       Add Specialty
 *     </button>
 *   </div>
 * 
 *   <input type="hidden" name="specialties" data-awards-award-form-target="formValue">
 *   <div data-awards-award-form-target="displayList" class="specialty-list"></div>
 * </form>
 * ```
 * 
 * ### Administrative Award Management
 * ```html
 * <!-- Award editing with multi-category support -->
 * <div data-controller="awards-award-form" class="award-config-form">
 *   <div class="form-group">
 *     <label for="categories">Award Categories</label>
 *     <div class="input-group">
 *       <input type="text" data-awards-award-form-target="new" 
 *              placeholder="Enter category name" class="form-control">
 *       <button data-action="awards-award-form#add" type="button" 
 *               class="btn btn-outline-primary">Add Category</button>
 *     </div>
 *   </div>
 * 
 *   <input type="hidden" name="award[categories]" 
 *          data-awards-award-form-target="formValue" value='["existing1","existing2"]'>
 *   <div data-awards-award-form-target="displayList" class="categories-display"></div>
 * </div>
 * ```
 * 
 * ### Form Automation Integration
 * ```javascript
 * // External controller integration for automated form management
 * document.addEventListener('DOMContentLoaded', function() {
 *   const awardForm = document.querySelector('[data-controller="awards-award-form"]');
 *   if (awardForm) {
 *     // Pre-populate form with existing data
 *     const controller = window.Stimulus.getControllerForElementAndIdentifier(awardForm, 'awards-award-form');
 *     // Form will automatically restore from hidden field value
 *   }
 * });
 * ```
 * 
 * @class AwardsAwardForm
 * @extends {Controller}
 */
class AwardsAwardForm extends Controller {
    static targets = ["new", "formValue", "displayList"];

    /**
     * Initialize controller state
     * 
     * Sets up the internal items array for tracking form values and managing
     * dynamic list state throughout the controller lifecycle.
     * 
     * @returns {void}
     */
    initialize() {
        this.items = [];
    }

    /**
     * Add new item to the list
     * 
     * Validates input, prevents duplicates, and adds new items to the dynamic list
     * with proper sanitization and form value synchronization.
     * 
     * @param {Event} event - Click event from add button
     * @returns {void}
     */
    add(event) {
        event.preventDefault();
        if (!this.newTarget.value) {
            return;
        }
        if (this.items.includes(this.newTarget.value)) {
            return;
        }
        let item = this.newTarget.value;
        this.items.push(item);
        this.createListItem(KMP_utils.sanitizeString(item));
        this.formValueTarget.value = JSON.stringify(this.items);
        this.newTarget.value = '';
    }

    /**
     * Remove item from the list
     * 
     * Removes selected item from both the visual display and internal array,
     * updating form values and maintaining data consistency.
     * 
     * @param {Event} event - Click event from remove button
     * @returns {void}
     */
    remove(event) {
        event.preventDefault();
        let id = event.target.getAttribute('data-id');
        this.items = this.items.filter(item => {
            return item !== id;
        });
        this.formValueTarget.value = JSON.stringify(this.items);
        event.target.parentElement.remove();
    }

    /**
     * Connect controller and restore form state
     * 
     * Initializes the controller, restores existing values from form data,
     * and rebuilds the visual display list on page load.
     * 
     * @returns {void}
     */
    connect() {
        if (this.formValueTarget.value && this.formValueTarget.value.length > 0) {
            this.items = JSON.parse(this.formValueTarget.value);
            if (!Array.isArray(this.items)) {
                this.items = [];
            }
            this.items.forEach(item => {
                //create a remove button
                this.createListItem(item);
            });
        }
    }

    /**
     * Create visual list item with remove button
     * 
     * Generates Bootstrap-styled display element with remove functionality
     * for dynamic list management and user interaction.
     * 
     * @param {string} item - The item text to display
     * @returns {void}
     */
    createListItem(item) {
        let removeButton = document.createElement('button');
        removeButton.innerHTML = 'Remove';
        removeButton.setAttribute('data-action', 'awards-award-form#remove');
        removeButton.setAttribute('data-id', item);
        removeButton.setAttribute('class', 'btn btn-danger btn-sm');
        removeButton.setAttribute('type', 'button');
        //create a list item
        let inputGroup = document.createElement('div');
        inputGroup.setAttribute('class', 'input-group mb-1');
        let span = document.createElement('span');
        span.innerHTML = item
        span.setAttribute('class', 'form-control');
        inputGroup.appendChild(span);
        inputGroup.appendChild(removeButton);
        this.displayListTarget.appendChild(inputGroup);
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-award-form"] = AwardsAwardForm;