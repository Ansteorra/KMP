import { Controller } from "@hotwired/stimulus"

class SelectAllListController extends Controller {

    allCheckboxes;
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