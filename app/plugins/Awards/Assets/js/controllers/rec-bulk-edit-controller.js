

import { Controller } from "@hotwired/stimulus"

class AwardsRecommendationBulkEditForm extends Controller {
    static targets = [
        "award",
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
    };
    static outlets = ['grid-btn'];

    setId(event) {
        this.turboFrameTarget.setAttribute("src", this.turboFrameUrlValue + "/" + event.detail.id);
        this.element.setAttribute("action", this.formUrlValue + "/" + event.detail.id);
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
    setAward(event) {
        let awardId = event.target.dataset.awardId;
        this.awardTarget.value = awardId;
        if (this.awardTarget.value != "") {
            this.populateSpecialties(event);
        }
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
    }

    setFieldRules() {
        console.log("bulk setting field rules");
        var rulesstring = this.stateRulesBlockTarget.textContent;
        var rules = JSON.parse(rulesstring);
        console.log(rules);
        if (this.specialtyTarget.options.length == 0) {
            this.specialtyTarget.hidden = true;
            this.specialtyTarget.disabled = true;
        }

        this.planToGiveBlockTarget.style.display = "none";
        this.givenBlockTarget.style.display = "none";
        this.domainTarget.disabled = false;
        this.awardTarget.disabled = false;
        this.specialtyTarget.disabled = this.specialtyTarget.hidden;
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
    recIdTargetConnected() {
        let recId = this.recIdTarget.value;
        let actionUrl = this.element.getAttribute("action");
        //trim the last / off of the end of the action url
        actionUrl = actionUrl.replace(/\/\d+$/, "");
        actionUrl = actionUrl + "/" + recId;
        this.element.setAttribute("action", actionUrl);
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-bulk-edit"] = AwardsRecommendationBulkEditForm;