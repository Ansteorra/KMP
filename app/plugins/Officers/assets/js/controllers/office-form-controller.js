

import { Controller } from "@hotwired/stimulus"

class OfficeFormController extends Controller {
    static targets = [
        "reportsTo",
        "reportsToBlock",
        "deputyTo",
        "deputyToBlock",
        "isDeputy",
    ];

    toggleIsDeputy() {
        //if the iSDepuy is checked, show the deputyTo select box
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

    connect() {
        console.log("connected");
        this.toggleIsDeputy();
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["office-form"] = OfficeFormController;