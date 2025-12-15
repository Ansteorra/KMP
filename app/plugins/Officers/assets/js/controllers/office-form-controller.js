/**
 * Office Form Controller - Manages deputy/reporting structure toggle
 *
 * Targets: reportsTo, reportsToBlock, deputyTo, deputyToBlock, isDeputy
 */

import { Controller } from "@hotwired/stimulus"

class OfficeFormController extends Controller {
    static targets = [
        "reportsTo",
        "reportsToBlock",
        "deputyTo",
        "deputyToBlock",
        "isDeputy",
    ];

    /** Toggle between deputy and reports-to fields based on isDeputy checkbox. */
    toggleIsDeputy() {
        if (this.isDeputyTarget.checked) {
            this.deputyToBlockTarget.hidden = false;
            this.deputyToTarget.disabled = false;
            this.reportsToBlockTarget.hidden = true;
            this.reportsToTarget.disabled = true;
        } else {
            this.deputyToBlockTarget.hidden = true;
            this.deputyToTarget.disabled = true;
            this.deputyToTarget.value = "";
            this.reportsToBlockTarget.hidden = false;
            this.reportsToTarget.disabled = false;
        }
    }

    /** Initialize form state on controller connect. */
    connect() {
        console.log("connected");
        this.toggleIsDeputy();
    }
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["office-form"] = OfficeFormController;