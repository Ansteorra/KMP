import { Controller } from "@hotwired/stimulus"

class BrancheLinks extends Controller {
    static targets = ["new", "formValue", "displayList", "linkType"];
    initialize() {
        this.items = [];
    }

    setLinkType(event) {
        event.preventDefault();
        let linkType = event.target.getAttribute('data-value');
        let previousLinkType = this.linkTypeTarget.dataset.value;
        if (previousLinkType !== linkType) {
            this.linkTypeTarget.classList.remove('bi-' + previousLinkType);
            this.linkTypeTarget.classList.add('bi-' + linkType);
        }
        this.linkTypeTarget.dataset.value = linkType;
    }

    add(event) {
        event.preventDefault();
        if (!this.newTarget.value) {
            return;
        }
        let url = KMP_utils.sanitizeString(this.newTarget.value);
        let type = this.linkTypeTarget.dataset.value;
        //check urls for duplicate url and type
        if (this.items.find(item => item.url === url && item.type === type)) {
            return;
        }
        let item = { "url": KMP_utils.sanitizeString(this.newTarget.value), "type": this.linkTypeTarget.dataset.value };
        this.items.push(item);
        this.createListItem(item);
        this.formValueTarget.value = JSON.stringify(this.items);
        this.newTarget.value = '';
        this.linkTypeTarget.value = 'link';
        this.linkTypeTarget.classList.remove('bi-' + type);
        this.linkTypeTarget.classList.add('bi-link');
    }
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

    connect() {
        if (this.formValueTarget.value && this.formValueTarget.value.length > 0) {
            this.items = JSON.parse(this.formValueTarget.value);
            this.items.forEach(item => {
                //create a remove button
                this.createListItem(item);
            });
        }
    }

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