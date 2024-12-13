

import { Controller } from "@hotwired/stimulus"

class AwardsRecommendationBulkEditForm extends Controller {
    static targets = [
        "bulkIds",
        "events",
        "state",
        "planToGiveBlock",
        "planToGiveEvent",
        "givenBlock",
        "recId",
        "turboFrame",
        "givenDate",
        "closeReason",
        "closeReasonBlock",
        "stateRulesBlock",
    ];
    static values = {
        formUrl: String,
        turboFrameUrl: String,
        bulkIds: Array,
    };
    static outlets = ['outlet-btn'];

    setId(event) {
        let selected = event.detail.ids;
        if (!selected) {
            return;
        }
        if (!selected.length) {
            return;
        }
        this.bulkIdsValue = selected;
        this.bulkIdsTarget.value = selected;
        let actionUrl = this.element.getAttribute("action");
        //repalce url
        actionUrl = actionUrl.replace(/update-states/, "updateStates");
        this.element.setAttribute("action", actionUrl);
        return
    }
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }


    submit(event) {
        document.getElementById("recommendation_bulk_edit_close").click();
    }

    stateTargetConnected() {
        this.setFieldRules();
    }

    setFieldRules() {
        var rulesstring = this.stateRulesBlockTarget.textContent;
        var rules = JSON.parse(rulesstring);
        this.planToGiveBlockTarget.style.display = "none";
        this.givenBlockTarget.style.display = "none";
        this.planToGiveEventTarget.required = false;
        this.givenDateTarget.required = false;
        this.closeReasonBlockTarget.style.display = "none";
        this.closeReasonTarget.required = false;
        var state = this.stateTarget.value;

        //check status rules for the status
        if (rules[state]) {
            var statusRules = rules[state];
            var controller = this;
            if (statusRules["Visible"]) {
                statusRules["Visible"].forEach(function (field) {
                    if (controller[field]) {
                        controller[field].style.display = "block";
                    }
                });
            }
            if (statusRules["Disabled"]) {
                statusRules["Disabled"].forEach(function (field) {
                    if (controller[field]) {
                        controller[field].disabled = true;
                    }
                });
            }
            if (statusRules["Required"]) {
                statusRules["Required"].forEach(function (field) {
                    if (controller[field]) {
                        controller[field].required = true;
                    }
                });
            }
        }
    }
    connect() {

    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-bulk-edit"] = AwardsRecommendationBulkEditForm;