import { Controller } from "@hotwired/stimulus"

/**
 * BranchLinks Stimulus Controller
 * 
 * Manages dynamic branch link collection with URL validation, link type categorization,
 * and form integration. Provides a user-friendly interface for adding, removing, and
 * organizing branch-related links with Bootstrap UI components.
 * 
 * Features:
 * - Dynamic link addition with URL sanitization
 * - Link type selection with Bootstrap Icons
 * - Duplicate prevention and validation
 * - Real-time form value synchronization
 * - Bootstrap-styled UI components
 * 
 * Targets:
 * - new: Input field for new link URLs
 * - formValue: Hidden field containing JSON array of all links
 * - displayList: Container for displaying added links
 * - linkType: Element for link type selection with icon display
 * 
 * Usage:
 * <div data-controller="branch-links">
 *   <input data-branch-links-target="new" type="url" placeholder="Enter URL">
 *   <div data-branch-links-target="linkType" data-value="link" class="bi bi-link"></div>
 *   <button data-action="click->branch-links#add">Add Link</button>
 *   <div data-branch-links-target="displayList"></div>
 *   <input data-branch-links-target="formValue" type="hidden" name="links">
 * </div>
 */
class BrancheLinks extends Controller {
    static targets = ["new", "formValue", "displayList", "linkType"];

    /**
     * Initialize controller state
     * Sets up empty items array for link management
     */
    initialize() {
        this.items = [];
    }

    /**
     * Set the link type for new link additions
     * Updates the link type icon and stores the selected type
     * 
     * @param {Event} event - Click event from link type selector
     */
    setLinkType(event) {
        event.preventDefault();
        let linkType = event.target.getAttribute('data-value');
        let previousLinkType = this.linkTypeTarget.dataset.value;
        this.linkTypeTarget.classList.remove('bi-' + previousLinkType);
        this.linkTypeTarget.classList.add('bi-' + linkType);
        this.linkTypeTarget.dataset.value = linkType;
    }

    /**
     * Add a new link to the collection
     * Validates input, sanitizes URL, prevents duplicates, and updates display
     * 
     * @param {Event} event - Click event from add button
     */
    add(event) {
        event.preventDefault();
        if (!this.newTarget.checkValidity()) {
            this.newTarget.reportValidity();
            return;
        }
        if (!this.newTarget.value) {
            return;
        }
        let url = KMP_utils.sanitizeUrl(this.newTarget.value);
        let type = this.linkTypeTarget.dataset.value;
        //check urls for duplicate url and type
        if (this.items.find(item => item.url === url && item.type === type)) {
            return;
        }
        let item = { "url": KMP_utils.sanitizeUrl(this.newTarget.value), "type": this.linkTypeTarget.dataset.value };
        this.items.push(item);
        this.createListItem(item);
        this.formValueTarget.value = JSON.stringify(this.items);
        this.newTarget.value = '';
        this.linkTypeTarget.dataset.value = 'link';
        this.linkTypeTarget.classList.remove('bi-' + type);
        this.linkTypeTarget.classList.add('bi-link');
    }

    /**
     * Remove a link from the collection
     * Filters out the specified item and updates the display
     * 
     * @param {Event} event - Click event from remove button
     */
    remove(event) {
        event.preventDefault();
        let id = event.target.getAttribute('data-id');
        let removeItem = JSON.parse(id);
        this.items = this.items.filter(item => {
            return item.url !== removeItem.url || item.type !== removeItem.type;
        });
        this.formValueTarget.value = JSON.stringify(this.items);
        event.target.parentElement.remove();
    }

    /**
     * Connect controller to DOM
     * Loads existing links from form value and recreates the display
     */
    connect() {
        if (this.formValueTarget.value && this.formValueTarget.value.length > 0) {
            this.items = JSON.parse(this.formValueTarget.value);
            this.items.forEach(item => {
                //create a remove button
                this.createListItem(item);
            });
        }
    }

    /**
     * Create a visual list item for a link
     * Generates Bootstrap input group with icon, URL display, and remove button
     * 
     * @param {Object} item - Link object with url and type properties
     */
    createListItem(item) {
        let removeButton = document.createElement('button');
        removeButton.innerHTML = 'Remove';
        removeButton.setAttribute('data-action', 'branch-links#remove');
        removeButton.setAttribute('data-id', JSON.stringify(item));
        removeButton.setAttribute('class', 'btn btn-danger btn-sm');
        removeButton.setAttribute('type', 'button');
        //create a list item
        let inputGroup = document.createElement('div');
        inputGroup.setAttribute('class', 'input-group mb-1');
        let iconSpan = document.createElement('span');
        iconSpan.setAttribute('class', 'input-group-text bi bi-' + item.type);
        inputGroup.appendChild(iconSpan);
        let span = document.createElement('span');
        span.innerHTML = item.url
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
window.Controllers["branch-links"] = BrancheLinks;