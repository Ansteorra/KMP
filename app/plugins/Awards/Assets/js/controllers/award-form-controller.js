import { Controller } from "@hotwired/stimulus"

/**
 * Awards Award Form Controller
 *
 * Manages dynamic multi-value field lists (e.g., specialties) with add/remove
 * functionality and JSON serialization for form submission.
 *
 * Targets: new, formValue, displayList
 */
class AwardsAwardForm extends Controller {
    static targets = ["new", "formValue", "displayList"];

    /** Initialize empty items array for list tracking. */
    initialize() {
        this.items = [];
    }

    /** Add new item to list, preventing duplicates and syncing form value. */
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

    /** Remove item from list and update form value. */
    remove(event) {
        event.preventDefault();
        let id = event.target.getAttribute('data-id');
        this.items = this.items.filter(item => {
            return item !== id;
        });
        this.formValueTarget.value = JSON.stringify(this.items);
        event.target.parentElement.remove();
    }

    /** Restore list from form value on connect. */
    connect() {
        if (this.formValueTarget.value && this.formValueTarget.value.length > 0) {
            this.items = JSON.parse(this.formValueTarget.value);
            if (!Array.isArray(this.items)) {
                this.items = [];
            }
            this.items.forEach(item => {
                this.createListItem(item);
            });
        }
    }

    /** Create Bootstrap-styled list item with remove button. */
    createListItem(item) {
        let removeButton = document.createElement('button');
        removeButton.innerHTML = 'Remove';
        removeButton.setAttribute('data-action', 'awards-award-form#remove');
        removeButton.setAttribute('data-id', item);
        removeButton.setAttribute('class', 'btn btn-danger btn-sm');
        removeButton.setAttribute('type', 'button');
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