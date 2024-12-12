

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
        publicProfileUrl: String,
        awardListUrl: String,
        formUrl: String,
        turboFrameUrl: String,
        bulkIds: Array,
    };
    static outlets = ['grid-btn'];

    setId(event) {
        if (!event.detail.id) {
            this.turboFrameTarget.setAttribute("src", this.turboFrameUrlValue);
            this.element.setAttribute("action", this.formUrlValue);

        }
    }
    gridBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }
    gridBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }


    submit(event) {
        document.getElementById("recommendation_bulk_edit_close").click();
    }





    loadScaMemberInfo(event) {
    }

    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    stateTargetConnected() {
        console.log("bulk status connected");
        this.setFieldRules();
        const event = this.dispatch("stateTargetConnected", { detail: { content: 'hellow data' } })
        if (event.selected.length) {
            this.bulkIdsValue = event.selected;
            this.bulkIdsTarget.value = event.selected;
            let actionUrl = this.element.getAttribute("action");
            //repalce url
            actionUrl = actionUrl.replace(/update-states/, "updateStates");
            this.element.setAttribute("action", actionUrl);
        } else {
            //TODO better handle feedback about empty form
        }
    }

    setFieldRules() {
        console.log("bulk setting field rules");
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