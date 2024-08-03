import { Controller } from "@hotwired/stimulus"

class AwardsRecommendationAddForm extends Controller {
    static targets = [
        "scaMember",
        "notFound",
        "branch",
        "callIntoCourt",
        "courtAvailability",
        "externalLinks",
        "awardDescriptions",
        "award",
        "reason",
        "events",
        "specialty",
    ];
    static values = {
        publicProfileUrl: String,
        awardListUrl: String
    };
    submit(event) {
        this.callIntoCourtTarget.disabled = false;
        this.courtAvailabilityTarget.disabled = false;
        this.notFoundTarget.disabled = false;
    }
    setAward(event) {
        let awardId = event.target.dataset.awardId;
        this.awardTarget.value = awardId;
        this.populateSpecialties(event);
    }
    populateAwardDescriptions(event) {
        let url = this.awardListUrlValue + "/" + event.target.value;
        fetch(url)
            .then(response => response.json())
            .then(data => {
                this.awardDescriptionsTarget.innerHTML = "";

                let tabButtons = document.createElement("ul");
                tabButtons.classList.add("nav", "nav-pills");
                tabButtons.setAttribute("role", "tablist");

                let tabContentArea = document.createElement("div");
                tabContentArea.classList.add("tab-content");
                tabContentArea.classList.add("border");
                tabContentArea.classList.add("border-light-subtle");
                tabContentArea.classList.add("p-2");

                tabContentArea.innerHTML = "";
                this.awardTarget.value = "";
                let active = "active";
                let show = "show";
                let selected = "true";
                let awardList = [];
                if (data.length > 0) {
                    data.forEach(function (award) {
                        //create list item
                        awardList.push({ value: award.id, text: award.name, data: award });
                        //create tab info
                        var tabButton = document.createElement("li");
                        tabButton.classList.add("nav-item");
                        tabButton.setAttribute("role", "presentation");
                        var button = document.createElement("button");
                        button.classList.add("nav-link");
                        if (active == "active") {
                            button.classList.add("active");
                        }
                        button.setAttribute("data-action", "click->awards-rec-add#setAward");
                        button.setAttribute("id", "award_" + award.id + "_btn");
                        button.setAttribute("data-bs-toggle", "tab");
                        button.setAttribute("data-bs-target", "#award_" + award.id);
                        button.setAttribute('data-award-id', award.id);
                        button.setAttribute("type", "button");
                        button.setAttribute("role", "tab");
                        button.setAttribute("aria-controls", "award_" + award.id);
                        button.setAttribute("aria-selected", selected);
                        button.innerHTML = award.name;
                        tabButton.appendChild(button);
                        var tabContent = document.createElement("div");
                        tabContent.classList.add("tab-pane");
                        tabContent.classList.add("fade");
                        if (show == "show") {
                            tabContent.classList.add("show");
                        }
                        if (active == "active") {
                            tabContent.classList.add("active");
                        }
                        tabContent.setAttribute("id", "award_" + award.id);
                        tabContent.setAttribute("role", "tabpanel");
                        tabContent.setAttribute("aria-labelledby", "award_" + award.id + "_btn");
                        tabContent.innerHTML = award.name + ": " + award.description;
                        active = "";
                        show = "";
                        selected = "false";
                        tabButtons.append(tabButton);
                        tabContentArea.append(tabContent);

                    });
                    this.awardDescriptionsTarget.appendChild(tabButtons);
                    this.awardDescriptionsTarget.appendChild(tabContentArea);
                    this.awardTarget.options = awardList;
                    this.awardTarget.disabled = false;
                } else {
                    this.awardTarget.options = [{ value: "No awards available", text: "No awards available" }];
                    this.awardTarget.value = "No awards available";
                    this.awardTarget.disabled = true;
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
        } else {
            this.specialtyTarget.options = [{ value: "No specialties available", text: "No specialties available" }];
            this.specialtyTarget.value = "No specialties available";
            this.specialtyTarget.disabled = true
            this.specialtyTarget.hidden = true;
        }
    }

    loadScaMemberInfo(event) {
        //reset member metadata area
        this.externalLinksTarget.innerHTML = "";
        this.courtAvailabilityTarget.value = "";
        this.callIntoCourtTarget.value = "";
        this.callIntoCourtTarget.disabled = false;
        this.courtAvailabilityTarget.disabled = false;

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

    loadMember(memberId) {
        let url = this.publicProfileUrlValue + "/" + memberId;
        fetch(url)
            .then(response => response.json())
            .then(data => {
                this.callIntoCourtTarget.value = data.additional_info.CallIntoCourt;
                this.courtAvailabilityTarget.value = data.additional_info.CourtAvailability;
                if (this.callIntoCourtTarget.value != "") {
                    this.callIntoCourtTarget.disabled = true;
                } else {
                    this.callIntoCourtTarget.disabled = false;
                }
                if (this.courtAvailabilityTarget.value != "") {
                    this.courtAvailabilityTarget.disabled = true;
                } else {
                    this.courtAvailabilityTarget.disabled = false;
                }
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
    acConnected(event) {
        var target = event.detail["awardsRecAddTarget"];
        switch (target) {
            case "branch":
                this.branchTarget.disabled = true;
                this.branchTarget.hidden = true;
                this.branchTarget.value = "";
                break;
            case "award":
                this.awardTarget.disabled = true;
                this.awardTarget.value = "Select Award Type First";
                break;
            case "scaMember":
                this.scaMemberTarget.value = "";
                break;
            case "specialty":
                this.specialtyTarget.value = "Select Award First";
                this.specialtyTarget.disabled = true;
                this.specialtyTarget.hidden = true;
                break;
            default:
                event.target.value = "";
                break;
        }
    }
    connect() {
        this.notFoundTarget.checked = false;
        this.notFoundTarget.disabled = true;
        this.reasonTarget.value = "";
        this.eventsTargets.forEach((element) => {
            element.checked = false;
        });
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-add"] = AwardsRecommendationAddForm;