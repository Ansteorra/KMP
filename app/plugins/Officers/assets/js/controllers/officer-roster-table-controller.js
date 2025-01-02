

import { Controller } from "@hotwired/stimulus"

class OfficerRosterTableForm extends Controller {
    static targets = [
        "rowCheckbox",
    ];

    ids = [];

    submitBtn = null;

    static outlets = ['outlet-btn'];

    outletBtnOutletConnected(outlet, element) {
        this.submitBtn = outlet;
        if (this.ids.length > 0) {
            this.submitBtn.element.disabled = false;
        }
    }
    outletBtnOutletDisconnected(outlet) {
        this.submitBtn = null;
    }

    rowCheckboxTargetConnected(element) {
        this.ids.push(element.value);
        console.log(this.ids);
    }


    rowChecked(event) {
        if (event.target.checked) {
            this.ids.push(event.target.value);
        } else {
            this.ids = this.ids.filter(id => id != event.target.value);
        }
        this.submitBtn.element.disabled = true;
        if (this.ids.length > 0) {
            this.submitBtn.element.disabled = false;
        }
        console.log(this.ids);
    }

    connect() {
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["officer-roster-table"] = OfficerRosterTableForm;