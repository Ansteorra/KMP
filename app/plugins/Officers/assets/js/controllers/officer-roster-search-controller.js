

import { Controller } from "@hotwired/stimulus"

class OfficerRosterSearchForm extends Controller {
    static targets = [
        "warrantPeriods",
        "departments",
        "showBtn"
    ];

    checkEnable() {
        if (this.warrantPeriodsTarget.value > 0 && this.departmentsTarget.value > 0) {
            this.showBtnTarget.disabled = false;
        } else {
            this.showBtnTarget.disabled = true;
        }
    }

    connect() {
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["officer-roster-search"] = OfficerRosterSearchForm;