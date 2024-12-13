

import { Controller } from "@hotwired/stimulus"

class AwardsRecommendationTable extends Controller {
    static targets = [
        "rowCheckbox",
    ];

    static outlets = ["outlet-btn"];

    checked(event) {

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

}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-table"] = AwardsRecommendationTable;