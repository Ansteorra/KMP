/**
 * Officer Roster Table Controller - Row selection and bulk operations
 *
 * Targets: rowCheckbox
 * Outlets: outlet-btn
 */

import { Controller } from "@hotwired/stimulus"

class OfficerRosterTableForm extends Controller {
    static targets = ["rowCheckbox"];
    static outlets = ['outlet-btn'];

    ids = [];
    submitBtn = null;

    /** Store submit button reference and enable if selections exist. */
    outletBtnOutletConnected(outlet, element) {
        this.submitBtn = outlet;
        if (this.ids.length > 0) {
            this.submitBtn.element.disabled = false;
        }
    }

    /** Clear submit button reference on disconnect. */
    outletBtnOutletDisconnected(outlet) {
        this.submitBtn = null;
    }

    /** Register checkbox ID on target connect. */
    rowCheckboxTargetConnected(element) {
        this.ids.push(element.value);
        console.log(this.ids);
    }

    /** Update selection array and enable/disable submit button on checkbox change. */
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

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["officer-roster-table"] = OfficerRosterTableForm;