/**
 * Officer Roster Search Controller - Search form validation
 *
 * Targets: warrantPeriods, departments, showBtn
 */

import { Controller } from "@hotwired/stimulus"

class OfficerRosterSearchForm extends Controller {
    static targets = ["warrantPeriods", "departments", "showBtn"];

    /** Enable search button when both warrant period and department are selected. */
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

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["officer-roster-search"] = OfficerRosterSearchForm;