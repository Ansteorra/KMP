

import { Controller } from "@hotwired/stimulus";

/**
 * Awards Recommendation Quick Edit Form Controller
 *
 * Streamlined modal form for rapid recommendation updates with state-driven
 * field rules. Simplified version of rec-edit for administrative efficiency.
 *
 * Targets: domain, award, reason, gatherings, specialty, state, planToGiveBlock,
 *          planToGiveGathering, givenBlock, recId, memberId, turboFrame, givenDate,
 *          closeReason, closeReasonBlock, stateRulesBlock
 * Values: publicProfileUrl (String), awardListUrl (String), formUrl (String),
 *         turboFrameUrl (String), gatheringsUrl (String)
 * Outlets: outlet-btn
 */
class AwardsRecommendationQuickEditForm extends Controller {
    static targets = [
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
        "memberId",
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

    /** Set recommendation ID and update Turbo Frame source and form action. */
    setId(event) {
        if (event.detail.id) {
            this.turboFrameTarget.setAttribute("src", this.turboFrameUrlValue + "/" + event.detail.id);
            this.element.setAttribute("action", this.formUrlValue + "/" + event.detail.id);
        }
    }

    /** Register listener when outlet-btn connects. */
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }

    /** Remove listener when outlet-btn disconnects. */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }

    /** Close modal after form submission. */
    submit(event) {
        document.getElementById("recommendation_edit_close").click();
    }

    /** Handle award selection, populate specialties, and update gatherings. */
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
        let memberId = this.hasMemberIdTarget ? this.memberIdTarget.value : '';

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

                            // Restore checked state if it was previously selected
                            if (selectedValues.includes(gathering.id.toString())) {
                                input.checked = true;
                            }

                            const label = document.createElement('label');
                            label.className = 'form-check-label';
                            label.htmlFor = `gathering-${gathering.id}`;
                            label.textContent = gathering.display;

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

    /** Placeholder for member info loading (not used in quick edit). */
    loadScaMemberInfo(event) {
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
        this.planToGiveGatheringTarget.required = false;
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
window.Controllers["awards-rec-quick-edit"] = AwardsRecommendationQuickEditForm;