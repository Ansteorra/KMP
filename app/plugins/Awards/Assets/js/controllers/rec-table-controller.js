

import { Controller } from "@hotwired/stimulus"



class AwardsRecommendationTable extends Controller {
    static targets = [
        "rowCheckbox",
        "CheckAllBox"
    ];

    static outlets = ["outlet-btn"];

    checked(event) {
        console.log("Check button checked ", this.element);
        // debugger;
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

    connect() {
    }

    checkAll(ele) {

        if (this.CheckAllBoxTarget.checked) {
            console.log("Checking All Checkboxes!", this.element);
            // debugger;
            let idList = [];

            for (var i = 0; i < this.rowCheckboxTargets.length; i++) {
                this.rowCheckboxTargets[i].checked = true; // Check all checkboxes
                idList.push(this.rowCheckboxTargets[i].value);

            }
            this.outletBtnOutlet.btnDataValue = { "ids": idList };

        }
        else {
            console.log("Unchecking All Checkboxes!", this.element);
            //debugger;
            this.outletBtnOutlet.btnDataValue = {};
            for (var i = 0; i < this.rowCheckboxTargets.length; i++) {
                this.rowCheckboxTargets[i].checked = false; // Uncheck all checkboxes
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