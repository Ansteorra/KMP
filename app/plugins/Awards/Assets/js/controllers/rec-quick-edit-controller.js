

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
        "currentAwardId",
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
        gatheringsUrl: String,
        gatheringsLookupUrl: String
    };
    static outlets = ['outlet-btn'];

    /** Set recommendation ID and update Turbo Frame source and form action. */
    setId(event) {
        if (event.detail.id) {
            this.turboFrameTarget.setAttribute("src", this.turboFrameUrlValue + "/" + event.detail.id);
            const form = document.getElementById("recommendation_form");
            if (form) {
                form.action = this.formUrlValue + "/" + event.detail.id;
            }
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

    /** After turbo frame swap: apply state rules once all targets exist; sync submit lock. */
    onTurboFrameLoad() {
        const locked = this.turboFrameTarget.querySelector('[data-recommendation-locked]');
        const submitBtn = document.getElementById('recommendation_submit');
        if (submitBtn) {
            submitBtn.disabled = Boolean(locked);
        }
        window.dispatchEvent(new CustomEvent('page-context:sync'));
        this.scheduleFieldRules();
    }

    /** Defer field rules until turbo-injected targets have finished connecting. */
    scheduleFieldRules() {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => this.setFieldRules());
        });
    }

    /** Block submit when recommendation is locked by an active bestowal. */
    submit(event) {
        if (this.turboFrameTarget.querySelector('[data-recommendation-locked]')) {
            event.preventDefault();
        }
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
        if (!awardId || !this.hasPlanToGiveGatheringTarget || !this.hasGatheringsLookupUrlValue) {
            return;
        }

        // Get member_id if available
        let memberId = this.hasMemberIdTarget ? this.memberIdTarget.value : '';
        let status = this.hasStateTarget ? this.stateTarget.value : '';
        let params = new URLSearchParams();
        if (memberId) {
            params.append('member_id', memberId);
        }
        if (status) {
            params.append('status', status);
        }
        if (this.hasRecIdTarget && this.recIdTarget.value) {
            params.append('recommendation_id', this.recIdTarget.value);
        }
        const awardKey = String(awardId);
        if (
            this.planToGiveGatheringTarget.dataset.lookupAwardId &&
            this.planToGiveGatheringTarget.dataset.lookupAwardId !== awardKey
        ) {
            this.planToGiveGatheringTarget.value = '';
            this.planToGiveGatheringTarget.dataset.initialValue = '';
        }
        this.planToGiveGatheringTarget.dataset.lookupAwardId = awardKey;

        const currentValue = this.planToGiveGatheringTarget.value ||
            this.planToGiveGatheringTarget.dataset.initialValue ||
            '';
        if (currentValue) {
            params.append('selected_id', currentValue);
            this.planToGiveGatheringTarget.dataset.initialValue = currentValue;
        }

        let lookupUrl = `${this.gatheringsLookupUrlValue}/${awardId}`;
        if (params.toString()) {
            lookupUrl += `?${params.toString()}`;
        }
        this.planToGiveGatheringTarget.setAttribute('data-ac-url-value', lookupUrl);
    }

    /** Sync required state to autocomplete text input. */
    setPlanToGiveRequired(required) {
        if (!this.hasPlanToGiveGatheringTarget) {
            return;
        }
        this.planToGiveGatheringTarget.required = required;
        const input = this.planToGiveGatheringTarget.querySelector("[data-ac-target='input']");
        if (input) {
            input.required = required;
        }
    }

    /** Show or hide a field group without leaving hidden controls focusable or required. */
    setFieldGroupVisible(group, visible) {
        group.hidden = !visible;
        group.style.display = visible ? "block" : "none";
        group.querySelectorAll("input, select, textarea, button").forEach((control) => {
            control.disabled = !visible;
            if (!visible) {
                control.required = false;
            }
        });
    }

    /** Fetch awards for domain and populate award selection with autocomplete. */
    populateAwardDescriptions(event) {
        let url = this.awardListUrlValue + "/" + event.target.value;
        if (this.hasCurrentAwardIdTarget && this.currentAwardIdTarget.value) {
            url += `?current_award_id=${encodeURIComponent(this.currentAwardIdTarget.value)}`;
        }
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

    /** Apply field rules when state target connects (may run before sibling targets exist). */
    stateTargetConnected() {
        this.scheduleFieldRules();
    }

    /** Parse JSON state rules and apply Visible/Required/Disabled field states. */
    setFieldRules() {
        if (!this.hasStateRulesBlockTarget || !this.hasStateTarget) {
            return;
        }

        let rules = {};
        try {
            rules = JSON.parse(this.stateRulesBlockTarget.textContent.trim());
        } catch (error) {
            console.warn("Recommendation quick edit: could not parse state rules JSON.", error);
            return;
        }

        if (this.hasSpecialtyTarget) {
            if (!this.specialtyTarget.options || this.specialtyTarget.options.length === 0) {
                this.specialtyTarget.hidden = true;
                this.specialtyTarget.disabled = true;
            }
        }

        if (this.hasPlanToGiveBlockTarget) {
            this.setFieldGroupVisible(this.planToGiveBlockTarget, false);
        }
        if (this.hasGivenBlockTarget) {
            this.setFieldGroupVisible(this.givenBlockTarget, false);
        }

        if (this.hasGivenDateTarget) {
            if (this.givenDateTarget.value && !this.givenDateTarget.dataset.initialValue) {
                this.givenDateTarget.dataset.initialValue = this.givenDateTarget.value;
            }

            if (!this.givenDateTarget.dataset.initialValue) {
                this.givenDateTarget.value = "";
            } else if (!this.givenDateTarget.value) {
                this.givenDateTarget.value = this.givenDateTarget.dataset.initialValue;
            }
        }

        if (this.hasDomainTarget) {
            this.domainTarget.disabled = false;
        }
        if (this.hasAwardTarget) {
            this.awardTarget.disabled = false;
        }
        if (this.hasSpecialtyTarget) {
            this.specialtyTarget.disabled = this.specialtyTarget.hidden;
        }
        this.setPlanToGiveRequired(false);
        if (this.hasGivenDateTarget) {
            this.givenDateTarget.required = false;
        }
        if (this.hasCloseReasonBlockTarget) {
            this.setFieldGroupVisible(this.closeReasonBlockTarget, false);
        }
        if (this.hasCloseReasonTarget) {
            this.closeReasonTarget.required = false;
        }

        const state = this.stateTarget.value;
        const statusRules = rules[state];
        if (statusRules) {
            const visibleFields = (statusRules.Visible || []).concat(statusRules.Optional || []);
            visibleFields.forEach((field) => {
                if (this[field]) {
                    if (this[field] instanceof HTMLElement && this[field].querySelectorAll) {
                        this.setFieldGroupVisible(this[field], true);
                    } else {
                        this[field].style.display = "block";
                    }
                }
            });
            if (statusRules.Disabled) {
                statusRules.Disabled.forEach((field) => {
                    if (this[field]) {
                        this[field].disabled = true;
                    }
                });
            }
            if (statusRules.Required) {
                statusRules.Required.forEach((field) => {
                    if (this[field]) {
                        this[field].required = true;
                    }
                });
            }
        }

        if (this.hasPlanToGiveGatheringTarget) {
            this.setPlanToGiveRequired(!!this.planToGiveGatheringTarget.required);
        }

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
