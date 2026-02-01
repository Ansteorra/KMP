
import { Controller } from "@hotwired/stimulus";

/**
 * Awards Recommendation Edit Form Controller
 *
 * Manages edit interface for award recommendations with state-driven form behavior,
 * dynamic field validation, member discovery, and Turbo Frame integration.
 *
 * Targets: scaMember, notFound, branch, externalLinks, domain, award, reason,
 *          gatherings, specialty, state, planToGiveBlock, planToGiveGathering,
 *          givenBlock, recId, turboFrame, givenDate, closeReason, closeReasonBlock,
 *          stateRulesBlock
 * Values: publicProfileUrl (String), awardListUrl (String), formUrl (String),
 *         turboFrameUrl (String), gatheringsUrl (String)
 * Outlets: outlet-btn
 *
 * State rules parsed from stateRulesBlock JSON control field Visible/Required/Disabled states.
 */
class AwardsRecommendationEditForm extends Controller {
    static targets = [
        "scaMember",
        "notFound",
        "branch",
        "externalLinks",
        "domain",
        "award",
        "reason",
        "gatherings",
        "specialty",
        "state",
        "planToGiveBlock",
        "planToGiveGathering",
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
        gatheringsUrl: String
    };
    static outlets = ['outlet-btn'];

    /** Set recommendation ID and update Turbo Frame source and form action URL. */
    setId(event) {
        this.turboFrameTarget.setAttribute("src", this.turboFrameUrlValue + "/" + event.detail.id);
        this.element.setAttribute("action", this.formUrlValue + "/" + event.detail.id);
    }

    /** Register listener when outlet-btn connects. */
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }

    /** Remove listener when outlet-btn disconnects. */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }

    /** Enable disabled fields before form submission. */
    submit(event) {
        this.notFoundTarget.disabled = false;
        this.scaMemberTarget.disabled = false;
        this.specialtyTarget.disabled = false;
    }

    /** Handle award selection, populate specialties, and update gatherings list. */
    setAward(event) {
        let awardId = event.target.dataset.awardId;
        this.awardTarget.value = awardId;
        if (this.awardTarget.value != "") {
            this.populateSpecialties(event);
            this.updateGatherings(awardId);
        }
    }

    /** Fetch gatherings filtered by award and update checkboxes and dropdown. */
    updateGatherings(awardId) {
        if (!awardId) {
            return;
        }

        // Get member_id if available
        let memberId = this.hasScaMemberTarget ? this.scaMemberTarget.value : '';

        // Get status if available
        let status = this.hasStateTarget ? this.stateTarget.value : '';

        // Build URL with query params
        let url = this.gatheringsUrlValue + '/' + awardId;
        let params = new URLSearchParams();
        if (memberId) {
            params.append('member_id', memberId);
        }
        if (status) {
            params.append('status', status);
        }
        if (params.toString()) {
            url += '?' + params.toString();
        }

        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                if (data.gatherings) {
                    // Update the gatherings checkboxes
                    const gatheringsContainer = document.getElementById('recommendation__gathering_ids');
                    if (gatheringsContainer) {
                        // Save currently selected values
                        const selectedValues = [];
                        gatheringsContainer.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                            selectedValues.push(cb.value);
                        });

                        // Clear existing options
                        gatheringsContainer.innerHTML = '';

                        // Add new options as checkboxes
                        data.gatherings.forEach(gathering => {
                            const div = document.createElement('div');
                            div.className = 'form-check';

                            const input = document.createElement('input');
                            input.type = 'checkbox';
                            input.className = 'form-check-input';
                            input.name = 'gatherings[_ids][]';
                            input.value = gathering.id;
                            input.id = `gathering-${gathering.id}`;

                            // Disable cancelled gatherings
                            if (gathering.cancelled) {
                                input.disabled = true;
                            }

                            // Restore checked state if it was previously selected
                            if (selectedValues.includes(gathering.id.toString())) {
                                input.checked = true;
                            }

                            const label = document.createElement('label');
                            label.className = 'form-check-label';
                            label.htmlFor = `gathering-${gathering.id}`;
                            label.textContent = gathering.display;

                            // Style cancelled gatherings
                            if (gathering.cancelled) {
                                label.classList.add('text-danger');
                            }

                            div.appendChild(input);
                            div.appendChild(label);
                            gatheringsContainer.appendChild(div);
                        });
                    }

                    // Also update the planToGiveGathering dropdown if it exists
                    if (this.hasPlanToGiveGatheringTarget) {
                        // Try to get current value, fallback to initial value stored on connect
                        const currentValue = this.planToGiveGatheringTarget.value ||
                            this.planToGiveGatheringTarget.dataset.initialValue ||
                            '';

                        // Clear existing options
                        this.planToGiveGatheringTarget.innerHTML = '';

                        // Add default option
                        const defaultOption = document.createElement('option');
                        defaultOption.value = '';
                        defaultOption.textContent = 'Select Gathering';
                        this.planToGiveGatheringTarget.appendChild(defaultOption);

                        // Add gathering options
                        data.gatherings.forEach(gathering => {
                            const option = document.createElement('option');
                            option.value = gathering.id;
                            option.textContent = gathering.display;

                            // Disable cancelled gatherings (unless currently selected)
                            if (gathering.cancelled && gathering.id.toString() !== currentValue) {
                                option.disabled = true;
                            }

                            // Restore selected state if it matches current or initial value
                            if (gathering.id.toString() === currentValue) {
                                option.selected = true;
                            }

                            this.planToGiveGatheringTarget.appendChild(option);
                        });

                        // Update the stored value for next time
                        if (currentValue) {
                            this.planToGiveGatheringTarget.dataset.initialValue = currentValue;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching gatherings:', error);
            });
    }

    /** Fetch awards for domain and populate award selection with autocomplete. */
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

    /** Update specialty dropdown based on selected award's configuration. */
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

    /** Handle member field change, load profile or show branch field if not found. */
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

    /** Get standard fetch options with JSON headers. */
    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    /** Fetch and display member profile external links. */
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

    /** Load member info when scaMember target connects with existing value. */
    scaMemberTargetConnected() {
        if (this.scaMemberTarget.value != "") {
            this.loadScaMemberInfo({ target: { value: this.scaMemberTarget.value } });
        }
    }

    /** Apply field rules when state target connects. */
    stateTargetConnected() {
        console.log("status connected");
        this.setFieldRules();
    }

    /** Parse JSON state rules and apply Visible/Required/Disabled field states. */
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

        // Store the current givenDate value before potentially clearing it
        if (this.givenDateTarget.value && !this.givenDateTarget.dataset.initialValue) {
            this.givenDateTarget.dataset.initialValue = this.givenDateTarget.value;
        }

        // Only clear givenDate if it doesn't have an initial value stored
        if (!this.givenDateTarget.dataset.initialValue) {
            this.givenDateTarget.value = "";
        } else {
            // Restore the initial value if it was cleared
            if (!this.givenDateTarget.value) {
                this.givenDateTarget.value = this.givenDateTarget.dataset.initialValue;
            }
        }

        this.domainTarget.disabled = false;
        this.awardTarget.disabled = false;
        this.specialtyTarget.disabled = this.specialtyTarget.hidden;
        this.scaMemberTarget.disabled = false;
        this.planToGiveGatheringTarget.required = false;
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

        // Update gatherings when state changes (e.g., to/from "Given")
        if (this.hasAwardTarget && this.awardTarget.value) {
            this.updateGatherings(this.awardTarget.value);
        }
    }

    /** Store initial gathering value on connect for persistence through updates. */
    connect() {
        // Store the initial gathering_id value so it persists through option updates
        if (this.hasPlanToGiveGatheringTarget && this.planToGiveGatheringTarget.value) {
            this.planToGiveGatheringTarget.dataset.initialValue = this.planToGiveGatheringTarget.value;
        }
    }

    /** Update form action URL when recId target connects. */
    recIdTargetConnected() {
        let recId = this.recIdTarget.value;
        let actionUrl = this.element.getAttribute("action");
        //trim the last / off of the end of the action url
        actionUrl = actionUrl.replace(/\/\d+$/, "");
        actionUrl = actionUrl + "/" + recId;
        this.element.setAttribute("action", actionUrl);
    }

    /** Store initial gathering value on target connect. */
    planToGiveGatheringTargetConnected() {
        // Store the initial value from the server-rendered form
        if (this.planToGiveGatheringTarget.value) {
            this.planToGiveGatheringTarget.dataset.initialValue = this.planToGiveGatheringTarget.value;
        }
    }

    /** Store initial given date value on target connect. */
    givenDateTargetConnected() {
        // Store the initial value from the server-rendered form
        if (this.givenDateTarget.value) {
            this.givenDateTarget.dataset.initialValue = this.givenDateTarget.value;
        }
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-edit"] = AwardsRecommendationEditForm;