

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

    /** Fetch gatherings that can give all selected awards via intersection. */
    async updateGatherings() {
        // Need both IDs and URL to fetch gatherings
        if (!this.bulkIdsValue || this.bulkIdsValue.length === 0 || !this.gatheringsUrlValue) {
            return;
        }

        const status = this.stateTarget.value;
        const currentSelection = this.planToGiveGatheringTarget.value;

        try {
            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            
            const response = await fetch(this.gatheringsUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    ids: this.bulkIdsValue,
                    status: status
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Clear existing options except the first (empty) one
            while (this.planToGiveGatheringTarget.options.length > 1) {
                this.planToGiveGatheringTarget.remove(1);
            }

            // Add new options
            if (data.gatherings && data.gatherings.length > 0) {
                data.gatherings.forEach(gathering => {
                    const option = document.createElement('option');
                    option.value = gathering.id;
                    option.textContent = gathering.display_name;
                    this.planToGiveGatheringTarget.appendChild(option);
                });

                // Restore previous selection if it still exists
                if (currentSelection) {
                    const optionExists = Array.from(this.planToGiveGatheringTarget.options).some(
                        opt => opt.value === currentSelection
                    );
                    if (optionExists) {
                        this.planToGiveGatheringTarget.value = currentSelection;
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching gatherings:', error);
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