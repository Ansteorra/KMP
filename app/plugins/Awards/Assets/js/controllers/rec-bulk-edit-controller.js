

import { Controller } from "@hotwired/stimulus";

/**
 * Awards Recommendation Bulk Edit Controller
 *
 * Modal form for batch state updates on multiple recommendations with
 * state-driven field rules and gathering intersection logic.
 *
 * Targets: bulkIds, gatherings, state, planToGiveBlock, planToGiveGathering,
 *          givenBlock, recId, turboFrame, givenDate, closeReason, closeReasonBlock,
 *          stateRulesBlock
 * Values: formUrl (String), turboFrameUrl (String), bulkIds (Array), gatheringsUrl (String)
 * Outlets: outlet-btn
 */
class AwardsRecommendationBulkEditForm extends Controller {
    static targets = [
        "bulkIds",
        "gatherings",
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
        formUrl: String,
        turboFrameUrl: String,
        bulkIds: Array,
        gatheringsUrl: String,
        gatheringsLookupUrl: String,
    };
    static outlets = ['outlet-btn'];

    /** Receive bulk IDs from table selection and update form action URL. */
    setId(event) {

        console.log("setId called", event.detail);
        //debugger;

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
        //replace url
        actionUrl = actionUrl.replace(/update-states/, "updateStates");
        this.element.setAttribute("action", actionUrl);
        console.log("setId", this.element["action"]);

        // Update gatherings list based on selected recommendations
        this.updateGatherings();

        return
    }

    /** Update backend lookup URL for gathering autocomplete in bulk edit. */
    updateGatherings() {
        if (!this.hasPlanToGiveGatheringTarget || !this.hasGatheringsLookupUrlValue) {
            return;
        }

        const selectedIds = (this.bulkIdsValue || []).filter(Boolean);
        const idsKey = selectedIds.join(',');
        if (
            this.planToGiveGatheringTarget.dataset.lookupIdsKey !== undefined &&
            this.planToGiveGatheringTarget.dataset.lookupIdsKey !== idsKey
        ) {
            this.planToGiveGatheringTarget.value = '';
            this.planToGiveGatheringTarget.dataset.initialValue = '';
        }
        this.planToGiveGatheringTarget.dataset.lookupIdsKey = idsKey;

        const currentSelection = this.planToGiveGatheringTarget.value ||
            this.planToGiveGatheringTarget.dataset.initialValue ||
            '';

        const params = new URLSearchParams();
        if (selectedIds.length > 0) {
            params.append('ids', selectedIds.join(','));
        }
        if (this.hasStateTarget && this.stateTarget.value) {
            params.append('status', this.stateTarget.value);
        }
        if (currentSelection) {
            params.append('selected_id', currentSelection);
            this.planToGiveGatheringTarget.dataset.initialValue = currentSelection;
        }

        let lookupUrl = this.gatheringsLookupUrlValue;
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
        document.getElementById("recommendation_bulk_edit_close").click();
    }

    /** Apply field rules when state target connects. */
    stateTargetConnected() {
        this.setFieldRules();
    }

    /** Parse JSON state rules and apply Visible/Required/Disabled field states. */
    setFieldRules() {
        var rulesstring = this.stateRulesBlockTarget.textContent;
        var rules = JSON.parse(rulesstring);
        this.planToGiveBlockTarget.style.display = "none";
        this.givenBlockTarget.style.display = "none";
        this.setPlanToGiveRequired(false);
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
        this.setPlanToGiveRequired(!!this.planToGiveGatheringTarget.required);

        // Update gatherings list when status changes (affects future vs all gatherings)
        this.updateGatherings();
    }

    /** Initialize bulk edit controller and set up event listeners. */
    connect() {
        // Listen for bulk action events from grid-view controller
        this.boundHandleGridBulkAction = this.handleGridBulkAction.bind(this);
        document.addEventListener('grid-view:bulk-action', this.boundHandleGridBulkAction);
    }

    /** Clean up event listeners on disconnect. */
    disconnect() {
        if (this.boundHandleGridBulkAction) {
            document.removeEventListener('grid-view:bulk-action', this.boundHandleGridBulkAction);
        }
    }

    /** Handle bulk action event from grid-view controller. */
    handleGridBulkAction(event) {
        // Create a synthetic event structure matching outlet-btn pattern
        this.setId({ detail: event.detail });
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-bulk-edit"] = AwardsRecommendationBulkEditForm;
