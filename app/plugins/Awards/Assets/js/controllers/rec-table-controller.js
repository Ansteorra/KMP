

import { Controller } from "@hotwired/stimulus"

/**
 * Awards Recommendation Table Controller
 *
 * Manages recommendation table with multi-selection checkboxes for bulk operations.
 * Communicates selected IDs to outlet controllers for coordinated actions.
 *
 * Targets: rowCheckbox, CheckAllBox
 * Outlets: outlet-btn
 */
class AwardsRecommendationTable extends Controller {
    static targets = [
        "rowCheckbox",
        "CheckAllBox"
    ];

    static outlets = ["outlet-btn"];

    /** Collect checked IDs and send to outlet button for bulk operations. */
    checked(event) {
        console.log("Check button checked ", this.element);
        let idList = [];
        this.outletBtnOutlet.btnDataValue = {};
        this.rowCheckboxTargets.forEach(input => {
            if (input.checked) {
                idList.push(input.value);
            }
        });
        if (idList.length > 0) {
            this.outletBtnOutlet.btnDataValue = { "ids": idList };
        }
    }

    /** Initialize table controller. */
    connect() {
    }

    /** Toggle all checkboxes and update outlet with selected IDs. */
    checkAll(ele) {
        if (this.CheckAllBoxTarget.checked) {
            console.log("Checking All Checkboxes!", this.element);
            let idList = [];
            for (var i = 0; i < this.rowCheckboxTargets.length; i++) {
                this.rowCheckboxTargets[i].checked = true;
                idList.push(this.rowCheckboxTargets[i].value);
            }
            this.outletBtnOutlet.btnDataValue = { "ids": idList };
        }
        else {
            console.log("Unchecking All Checkboxes!", this.element);
            this.outletBtnOutlet.btnDataValue = {};
            for (var i = 0; i < this.rowCheckboxTargets.length; i++) {
                this.rowCheckboxTargets[i].checked = false;
            }
            this.outletBtnOutlet.btnDataValue = {};
        }
    }
}

// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-table"] = AwardsRecommendationTable;