

import { Controller } from "@hotwired/stimulus"

class AwardsRecommendationEditForm extends Controller {
    static targets = [
        "scaMember",
        "notFound",
        "branch",
        "externalLinks",
        "domain",
        "award",
        "reason",
        "events",
        "specialty",
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
    static outlets = ['outlet-btn'];

    setId(event) {
        this.turboFrameTarget.setAttribute("src", this.turboFrameUrlValue + "/" + event.detail.id);
        this.element.setAttribute("action", this.formUrlValue + "/" + event.detail.id);
    }
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }


    submit(event) {
        this.notFoundTarget.disabled = false;
        this.scaMemberTarget.disabled = false;
        this.specialtyTarget.disabled = false;
    }
    setAward(event) {
        let awardId = event.target.dataset.awardId;
        this.awardTarget.value = awardId;
        if (this.awardTarget.value != "") {
            this.populateSpecialties(event);
        }
    }
    populateAwardDescriptions(event) {
        let url = this.awardListUrlValue + "/" + event.target.value;
        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                this.awardTarget.value = "";
                let active = "active";
                let show = "show";
                let selected = "true";
                let awardList = [];
                if (data.length > 0) {
                    data.forEach(function (award) {
                        awardList.push({ value: award.id, text: award.name, data: award });
                    });
                    this.awardTarget.options = awardList;
                    this.awardTarget.disabled = false;
                    if (this.awardTarget.dataset.acInitSelectionValue) {
                        let val = JSON.parse(this.awardTarget.dataset.acInitSelectionValue);
                        this.awardTarget.value = val.value;
                        if (this.awardTarget.value != "") {
                            this.populateSpecialties({ target: { value: val.value } });
                        }
                    }
                } else {
                    this.awardTarget.options = [{ value: "No awards available", text: "No awards available" }];
                    this.awardTarget.value = "No awards available";
                    this.awardTarget.disabled = true;
                    this.specialtyTarget.options = [{ value: "No specialties available", text: "No specialties available" }];
                    this.specialtyTarget.value = "No specialties available";
                    this.specialtyTarget.disabled = true
                    this.specialtyTarget.hidden = true;
                }
            });
    }
    populateSpecialties(event) {
        let awardId = this.awardTarget.value;
        let options = this.awardTarget.options;
        let award = this.awardTarget.options.find(award => award.value == awardId);
        let specialtyArray = [];
        if (award.data.specialties != null && award.data.specialties.length > 0) {
            award.data.specialties.forEach(function (specialty) {
                specialtyArray.push({ value: specialty, text: specialty });
            });
            this.specialtyTarget.options = specialtyArray;
            this.specialtyTarget.value = "";
            this.specialtyTarget.disabled = false;
            this.specialtyTarget.hidden = false;
            if (this.specialtyTarget.dataset.acInitSelectionValue) {
                let val = JSON.parse(this.specialtyTarget.dataset.acInitSelectionValue);
                this.specialtyTarget.value = val.value;
            }
        } else {
            this.specialtyTarget.options = [{ value: "No specialties available", text: "No specialties available" }];
            this.specialtyTarget.value = "No specialties available";
            this.specialtyTarget.disabled = true
            this.specialtyTarget.hidden = true;
        }
    }

    loadScaMemberInfo(event) {
        this.externalLinksTarget.innerHTML = "";

        let memberId = Number(event.target.value.replace(/_/g, ""));
        if (memberId > 0) {
            this.notFoundTarget.checked = false;
            this.branchTarget.hidden = true;
            this.branchTarget.disabled = true;
            this.loadMember(memberId);
        } else {
            this.notFoundTarget.checked = true;
            this.branchTarget.hidden = false;
            this.branchTarget.disabled = false;
            this.branchTarget.focus();
        }

    }

    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    loadMember(memberId) {
        let url = this.publicProfileUrlValue + "/" + memberId;
        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                this.externalLinksTarget.innerHTML = "";
                let keys = Object.keys(data.external_links);
                if (keys.length > 0) {
                    var LinksTitle = document.createElement("div");
                    LinksTitle.innerHTML = "<h5>Public Links</h5>";
                    LinksTitle.classList.add("col-12");
                    this.externalLinksTarget.appendChild(LinksTitle);
                    for (let key in data.external_links) {
                        let div = document.createElement("div");
                        div.classList.add("col-12");
                        let a = document.createElement("a");
                        a.href = data.external_links[key];
                        a.text = key;
                        a.target = "_blank";
                        div.appendChild(a);
                        this.externalLinksTarget.appendChild(div);
                    }
                } else {
                    var noLink = document.createElement("div");
                    noLink.innerHTML = "<h5>No links available</h5>";
                    noLink.classList.add("col-12");
                    this.externalLinksTarget.appendChild(noLink);
                }
            });
    }
    scaMemberTargetConnected() {
        if (this.scaMemberTarget.value != "") {
            this.loadScaMemberInfo({ target: { value: this.scaMemberTarget.value } });
        }
    }
    stateTargetConnected() {
        console.log("status connected");
        this.setFieldRules();
    }

    setFieldRules() {
        console.log("setting field rules");
        var rulesstring = this.stateRulesBlockTarget.textContent;
        var rules = JSON.parse(rulesstring);
        if (this.specialtyTarget.options.length == 0) {
            this.specialtyTarget.hidden = true;
            this.specialtyTarget.disabled = true;
        }

        this.planToGiveBlockTarget.style.display = "none";
        this.givenBlockTarget.style.display = "none";
        this.domainTarget.disabled = false;
        this.awardTarget.disabled = false;
        this.specialtyTarget.disabled = this.specialtyTarget.hidden;
        this.scaMemberTarget.disabled = false;
        this.planToGiveEventTarget.required = false;
        this.givenDateTarget.required = false;
        this.closeReasonBlockTarget.style.display = "none";
        this.closeReasonTarget.required = false;
        if (this.notFoundTarget.checked) {
            this.branchTarget.disabled = false;
            this.branchTarget.hidden = false;
        } else {
            this.branchTarget.disabled = true;
            this.branchTarget.hidden = true;
        }

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
window.Controllers["awards-rec-edit"] = AwardsRecommendationEditForm;