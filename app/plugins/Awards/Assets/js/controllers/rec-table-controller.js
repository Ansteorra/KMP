

import { Controller } from "@hotwired/stimulus"

class AwardsRecommendationTable extends Controller {
    static targets = [
        "input"
    ];
    static values = {
        publicProfileUrl: String,
        awardListUrl: String,
        formUrl: String,
        turboFrameUrl: String,
    };
    static outlets = [];


    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    stateTargetConnected() {
        console.log("rec table connected");
        //this.setFieldRules();
    }

    getSelected(event) {

        let selected = []
        this.inputTargets.forEach(input => {
            if (input.checked) {
                selected.push(input.value)
            }
        });

        event.selected = selected;
        return selected
    }

    getClicked() {
        this.dispatch("getClicked")
        console.log('got clicked', this.inputTargets);
        this.inputTargets.forEach(input => {
            if (input.checked) {
                console.log(input.value)
            }
        });
    }

    connect() {
        console.log(this.inputTargets)
    }

}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-table"] = AwardsRecommendationTable;