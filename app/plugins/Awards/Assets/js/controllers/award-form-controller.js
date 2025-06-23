import { Controller } from "@hotwired/stimulus"

class AwardsAwardForm extends Controller {
    static targets = ["new", "formValue", "displayList"];
    initialize() {
        this.items = [];
    }

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
    remove(event) {
        event.preventDefault();
        let id = event.target.getAttribute('data-id');
        this.items = this.items.filter(item => {
            return item !== id;
        });
        this.formValueTarget.value = JSON.stringify(this.items);
        event.target.parentElement.remove();
    }

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