
import { Controller } from "@hotwired/stimulus";

/**
 * Awards Bestowal Bulk Edit Controller
 *
 * Modal form for batch state updates on multiple bestowals with state-driven field rules.
 */
class AwardsBestowalBulkEditForm extends Controller {
    static targets = [
        "bulkIds",
        "state",
        "planToGiveBlock",
        "planToGiveGathering",
        "givenBlock",
        "givenDate",
        "closeReason",
        "closeReasonBlock",
        "stateRulesBlock",
        "submitButton",
    ];
    static values = {
        formUrl: String,
        turboFrameUrl: String,
        bulkIds: Array,
        gatheringsLookupUrl: String,
    };
    static outlets = ["outlet-btn"];

    /** Receive bulk IDs from grid selection and update form action URL. */
    setId(event) {
        const selected = event.detail.ids;
        if (!selected || !selected.length) {
            return;
        }
        this.bulkIdsValue = selected;
        if (this.hasBulkIdsTarget) {
            this.bulkIdsTarget.value = selected;
        }
        let actionUrl = this.element.getAttribute("action");
        actionUrl = actionUrl.replace(/update-states/, "updateStates");
        this.element.setAttribute("action", actionUrl);
        this.updateGatherings();
        this.updateSubmitState();
    }

    /** Update backend lookup URL for gathering autocomplete in bulk edit. */
    updateGatherings() {
        if (!this.hasPlanToGiveGatheringTarget || !this.hasGatheringsLookupUrlValue) {
            return;
        }

        const selectedIds = (this.bulkIdsValue || []).filter(Boolean);
        const idsKey = selectedIds.join(",");
        if (
            this.planToGiveGatheringTarget.dataset.lookupIdsKey !== undefined &&
            this.planToGiveGatheringTarget.dataset.lookupIdsKey !== idsKey
        ) {
            this.planToGiveGatheringTarget.value = "";
            this.planToGiveGatheringTarget.dataset.initialValue = "";
        }
        this.planToGiveGatheringTarget.dataset.lookupIdsKey = idsKey;

        const hiddenInput = this.planToGiveGatheringTarget.querySelector("[data-ac-target='hidden']");
        const currentSelection = (hiddenInput ? hiddenInput.value : "")
            || this.planToGiveGatheringTarget.dataset.initialValue
            || "";

        const params = new URLSearchParams();
        if (selectedIds.length > 0) {
            params.append("ids", selectedIds.join(","));
        }
        if (this.hasStateTarget && this.stateTarget.value) {
            params.append("status", this.stateTarget.value);
        }
        if (currentSelection) {
            params.append("selected_id", currentSelection);
            this.planToGiveGatheringTarget.dataset.initialValue = currentSelection;
        }

        let lookupUrl = this.gatheringsLookupUrlValue;
        if (params.toString()) {
            lookupUrl += `?${params.toString()}`;
        }
        this.planToGiveGatheringTarget.setAttribute("data-ac-url-value", lookupUrl);
    }

    /** Sync required state to autocomplete text input. */
    setGatheringRequired(required) {
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
    outletBtnOutletConnected(outlet) {
        outlet.addListener(this.setId.bind(this));
    }

    /** Remove listener when outlet-btn disconnects. */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }

    /** Clean up grid bulk action listener on connect. */
    connect() {
        this.boundHandleGridBulkAction = this.handleGridBulkAction.bind(this);
        document.addEventListener("grid-view:bulk-action", this.boundHandleGridBulkAction);
        this.updateSubmitState();
    }

    /** Clean up event listeners on disconnect. */
    disconnect() {
        if (this.boundHandleGridBulkAction) {
            document.removeEventListener("grid-view:bulk-action", this.boundHandleGridBulkAction);
        }
    }

    /** Handle bulk action event from grid-view controller. */
    handleGridBulkAction(event) {
        this.setId({ detail: event.detail });
    }

    /** Close modal after form submission. */
    submit(event) {
        if (!this.isFormSubmittable()) {
            event.preventDefault();
            event.stopPropagation();
            this.element.reportValidity?.();
            return;
        }

        document.getElementById("bestowal_bulk_edit_close").click();
    }

    /** @return {boolean} */
    isFormSubmittable() {
        if (this.getSelectedBulkIds().length === 0) {
            return false;
        }

        if (!this.hasStateTarget || this.stateTarget.value === "") {
            return false;
        }

        if (typeof this.element.checkValidity === "function") {
            return this.element.checkValidity();
        }

        return true;
    }

    /** Enable submit only when required fields are satisfied. */
    updateSubmitState() {
        if (!this.hasSubmitButtonTarget) {
            return;
        }

        this.submitButtonTarget.disabled = !this.isFormSubmittable();
    }

    /** @return {Array<string|number>} */
    getSelectedBulkIds() {
        if (Array.isArray(this.bulkIdsValue) && this.bulkIdsValue.length > 0) {
            return this.bulkIdsValue.filter(Boolean);
        }

        if (!this.hasBulkIdsTarget || this.bulkIdsTarget.value === "") {
            return [];
        }

        const rawValue = this.bulkIdsTarget.value;
        if (Array.isArray(rawValue)) {
            return rawValue.filter(Boolean);
        }

        if (typeof rawValue === "string") {
            try {
                const parsed = JSON.parse(rawValue);
                if (Array.isArray(parsed)) {
                    return parsed.filter(Boolean);
                }
            } catch (error) {
                return rawValue.split(",").map((id) => id.trim()).filter(Boolean);
            }
        }

        return [];
    }

    /** Apply field rules when state target connects. */
    stateTargetConnected() {
        this.setFieldRules();
    }

    /** Parse JSON state rules and apply Visible/Required/Disabled field states. */
    setFieldRules() {
        if (!this.hasStateRulesBlockTarget) {
            return;
        }

        const rules = JSON.parse(this.stateRulesBlockTarget.textContent);
        if (this.hasPlanToGiveBlockTarget) {
            this.planToGiveBlockTarget.style.display = "none";
        }
        if (this.hasGivenBlockTarget) {
            this.givenBlockTarget.style.display = "none";
        }
        if (this.hasCloseReasonBlockTarget) {
            this.closeReasonBlockTarget.style.display = "none";
        }

        this.setGatheringRequired(false);
        if (this.hasGivenDateTarget) {
            this.givenDateTarget.required = false;
        }
        if (this.hasCloseReasonTarget) {
            this.closeReasonTarget.required = false;
        }

        const state = this.stateTarget.value;
        const statusRules = rules[state];
        if (!statusRules) {
            this.updateSubmitState();
            return;
        }

        const visibleFields = [
            ...(statusRules.Visible ?? []),
            ...(statusRules.Optional ?? []),
        ];
        visibleFields.forEach((field) => {
            if (this[field]) {
                this[field].style.display = "block";
            }
        });
        if (statusRules.Required) {
            statusRules.Required.forEach((field) => {
                if (this[field]) {
                    this[field].required = true;
                }
            });
        }

        this.setGatheringRequired(!!this.planToGiveGatheringTarget?.required);
        this.updateGatherings();
        this.updateSubmitState();
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-bestowal-bulk-edit"] = AwardsBestowalBulkEditForm;
