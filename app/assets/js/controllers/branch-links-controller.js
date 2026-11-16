import { createIcon, setIcon } from '../services/icon-service.js';
const linkIcon = type => type === 'link' ? 'link' : `brand-${type === 'twitter-x' ? 'x' : type}`;
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
 * - Link type selection with catalog icons
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
 *   <button type="button" data-branch-links-target="linkType" data-value="link" aria-label="Link type: Link"><kmp-icon name="link" aria-hidden="true"></kmp-icon></button>
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
        let linkType = event.currentTarget.getAttribute('data-value');
        setIcon(this.linkTypeTarget.querySelector('kmp-icon'), linkIcon(linkType));
        this.linkTypeTarget.dataset.value = linkType;
        const label = event.currentTarget.textContent?.trim() || linkType.replace(/^./, letter => letter.toUpperCase());
        this.linkTypeTarget.setAttribute('aria-label', `Link type: ${label}`);
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
        this.linkTypeTarget.setAttribute('aria-label', 'Link type: Link');
        setIcon(this.linkTypeTarget.querySelector('kmp-icon'), 'link');
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
        iconSpan.className = 'input-group-text';
        iconSpan.append(createIcon(linkIcon(item.type)));
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